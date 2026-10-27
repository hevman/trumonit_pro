from __future__ import annotations

import hashlib
from datetime import timedelta, timezone
from typing import Annotated

from fastapi import APIRouter, Depends, Header, HTTPException
from pydantic import BaseModel, Field
from sqlalchemy import select
from sqlalchemy.exc import IntegrityError
from sqlalchemy.orm import Session

from .db import get_db, now
from .account_security import require_delivery, issue_email_and_deliver
from .models import LoginAttempt, Membership, SessionToken, User, Workspace, AuditEvent
from .security import (
    Principal, check_password, create_session, hash_password,
    normalized_email, principal, token_hash,
)

router = APIRouter(prefix="/auth", tags=["accounts"])


class Registration(BaseModel):
    name: str = Field(min_length=2, max_length=100)
    email: str = Field(min_length=5, max_length=254)
    password: str = Field(min_length=12, max_length=128)
    workspace_name: str = Field(min_length=2, max_length=100)


class Login(BaseModel):
    email: str = Field(min_length=5, max_length=254)
    password: str = Field(min_length=1, max_length=128)


def public_account(pr: Principal) -> dict:
    return {
        "user_id": pr.user_id,
        "email": pr.email,
        "workspace_id": pr.workspace_id,
        "workspace_name": pr.workspace_name,
        "role": pr.role,
        "email_verified": True,
    }


@router.post("/register", status_code=201)
def register(payload: Registration, db: Session = Depends(get_db)):
    email = normalized_email(payload.email)
    if db.scalar(select(User.id).where(User.email == email)):
        raise HTTPException(409, "Email is already registered")
    user = User(
        email=email, display_name=payload.name.strip(),
        password_hash=hash_password(payload.password),
    )
    workspace = Workspace(name=payload.workspace_name.strip())
    db.add_all([user, workspace])
    db.flush()
    db.add(Membership(user_id=user.id, workspace_id=workspace.id, role="owner"))
    db.add(AuditEvent(
        workspace_id=workspace.id, actor_id=user.id,
        action="workspace.created", resource_id=workspace.id,
    ))
    require_delivery()
    try:
        db.commit()
    except IntegrityError as exc:
        db.rollback()
        raise HTTPException(409, "Account already exists") from exc
    issue_email_and_deliver(db, user, "verify")
    return {"verification_required": True, "email": email}


@router.post("/login")
def login(payload: Login, db: Session = Depends(get_db)):
    email = normalized_email(payload.email)
    key = token_hash(email)
    tracker = db.get(LoginAttempt, key)
    if tracker is None:
        tracker = LoginAttempt(identity_hash=key, attempt_count=0, window_started_at=now())
        db.add(tracker)
        db.flush()
    start = tracker.window_started_at
    if start.tzinfo is None:
        start = start.replace(tzinfo=timezone.utc)
    if start < now() - timedelta(minutes=15):
        tracker.attempt_count = 0
        tracker.window_started_at = now()
    if tracker.attempt_count >= 8:
        raise HTTPException(429, "Too many sign-in attempts; retry in 15 minutes")
    user = db.scalar(select(User).where(User.email == email))
    if user is None or not check_password(payload.password, user.password_hash):
        tracker.attempt_count += 1
        db.commit()
        raise HTTPException(401, "Invalid credentials")
    if user.email_verified_at is None:
        raise HTTPException(403, "Email verification required before sign-in")
    member = db.scalar(select(Membership).where(Membership.user_id == user.id))
    if member is None:
        raise HTTPException(401, "Account has no workspace")
    workspace = db.get(Workspace, member.workspace_id)
    if workspace is None:
        raise HTTPException(401, "Workspace no longer exists")
    tracker.attempt_count = 0
    tracker.window_started_at = now()
    token = create_session(db, user.id, workspace.id)
    db.commit()
    return {
        "session_token": token,
        "account": public_account(Principal(
            user.id, workspace.id, user.email, workspace.name, member.role
        )),
    }


@router.get("/me")
def me(current: Principal = Depends(principal)):
    return public_account(current)


@router.post("/logout")
def logout(
    current: Principal = Depends(principal),
    x_trumonit_session: str | None = Header(default=None),
    db: Session = Depends(get_db),
):
    if x_trumonit_session:
        entry = db.get(SessionToken, token_hash(x_trumonit_session))
        if entry is not None and entry.user_id == current.user_id:
            db.delete(entry)
            db.commit()
    return {"signed_out": True}
