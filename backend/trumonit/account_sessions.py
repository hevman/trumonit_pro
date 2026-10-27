"""Authenticated session inventory and password-confirmed remote revocation.

Session identifiers are random database IDs, never bearer credentials. A
session cannot revoke itself here; the explicit logout endpoint handles that.
"""
from __future__ import annotations

from datetime import timedelta, timezone

from fastapi import APIRouter, Depends, Header, HTTPException
from pydantic import BaseModel, Field
from sqlalchemy import delete, select
from sqlalchemy.orm import Session

from .db import get_db, now
from .models import AuditEvent, LoginAttempt, SessionToken, User, Workspace
from .security import Principal, check_password, principal, token_hash

router = APIRouter(prefix="/auth/sessions", tags=["Account session security"])


class PasswordConfirmation(BaseModel):
    password: str = Field(min_length=1, max_length=128)


def utc(value):
    return value.replace(tzinfo=timezone.utc) if value.tzinfo is None else value


def current_hash(raw: str | None) -> str:
    if not raw:
        raise HTTPException(401, "Session unavailable")
    return token_hash(raw)


def confirm_password(db: Session, user: Principal, password: str) -> None:
    """Bound password re-authentication attempts even for a stolen session."""
    key = token_hash("session-revoke:" + user.user_id)
    attempts = db.scalar(select(LoginAttempt).where(
        LoginAttempt.identity_hash == key,
    ).with_for_update())
    if attempts is None:
        attempts = LoginAttempt(
            identity_hash=key, attempt_count=0, window_started_at=now(),
        )
        db.add(attempts)
        db.flush()
    if utc(attempts.window_started_at) <= now() - timedelta(minutes=15):
        attempts.window_started_at = now()
        attempts.attempt_count = 0
    if attempts.attempt_count >= 5:
        raise HTTPException(429, "Too many password checks; try again later")
    account = db.get(User, user.user_id)
    if account is None or not check_password(password, account.password_hash):
        attempts.attempt_count += 1
        db.commit()
        raise HTTPException(403, "Invalid current password")
    attempts.attempt_count = 0
    attempts.window_started_at = now()


@router.get("")
def list_sessions(
    user: Principal = Depends(principal),
    x_trumonit_session: str | None = Header(default=None),
    db: Session = Depends(get_db),
):
    active_hash = current_hash(x_trumonit_session)
    rows = db.execute(select(SessionToken, Workspace.name)
        .join(Workspace, Workspace.id == SessionToken.workspace_id)
        .where(SessionToken.user_id == user.user_id,
               SessionToken.expires_at > now())
        .order_by(SessionToken.created_at.desc(), SessionToken.id)
        .limit(100)).all()
    return [{
        "id": session.id,
        "workspace_name": workspace,
        "created_at": session.created_at.isoformat(),
        "expires_at": session.expires_at.isoformat(),
        "current": session.token_hash == active_hash,
    } for session, workspace in rows]


@router.post("/revoke-others")
def revoke_other_sessions(
    payload: PasswordConfirmation,
    user: Principal = Depends(principal),
    x_trumonit_session: str | None = Header(default=None),
    db: Session = Depends(get_db),
):
    active_hash = current_hash(x_trumonit_session)
    confirm_password(db, user, payload.password)
    result = db.execute(delete(SessionToken).where(
        SessionToken.user_id == user.user_id,
        SessionToken.token_hash != active_hash,
    ))
    db.add(AuditEvent(
        workspace_id=user.workspace_id, actor_id=user.user_id,
        action="account.other_sessions_revoked", resource_id=user.user_id,
    ))
    db.commit()
    return {"revoked": result.rowcount, "current_session_active": True}


@router.post("/{session_id}/revoke")
def revoke_session(
    session_id: str,
    payload: PasswordConfirmation,
    user: Principal = Depends(principal),
    x_trumonit_session: str | None = Header(default=None),
    db: Session = Depends(get_db),
):
    active_hash = current_hash(x_trumonit_session)
    session = db.scalar(select(SessionToken).where(
        SessionToken.id == session_id,
        SessionToken.user_id == user.user_id,
    ))
    if session is None:
        raise HTTPException(404, "Session unavailable")
    if session.token_hash == active_hash:
        raise HTTPException(409, "Use Sign out to end the current session")
    confirm_password(db, user, payload.password)
    db.delete(session)
    db.add(AuditEvent(
        workspace_id=user.workspace_id, actor_id=user.user_id,
        action="account.session_revoked", resource_id=user.user_id,
    ))
    db.commit()
    return {"revoked": True}
