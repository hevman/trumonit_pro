"""One-time email verification and password recovery, no tokens in API responses.

Tokens are stored only as SHA-256 digests, scoped to purpose, bounded by
expiry, consumed once under row locking, and never logged.
"""
from __future__ import annotations

import secrets
from datetime import timedelta, timezone
from typing import Literal

from fastapi import APIRouter, Depends, HTTPException
from pydantic import BaseModel, Field
from sqlalchemy import select, update
from sqlalchemy.orm import Session

from .account_mail import delivery_config, send_account_link
from .db import get_db, now
from .models import (
    AccountActionToken, AccountMailThrottle, AuditEvent,
    Membership, SessionToken, User, Workspace,
)
from .security import create_session, hash_password, normalized_email, token_hash

router = APIRouter(prefix="/auth", tags=["Account security"])


class EmailRequest(BaseModel):
    email: str = Field(min_length=5, max_length=254)


class SecretRequest(BaseModel):
    token: str = Field(min_length=43, max_length=43, pattern=r"^[A-Za-z0-9_-]{43}$")


class PasswordReset(SecretRequest):
    password: str = Field(min_length=12, max_length=128)


def as_utc(value):
    return value.replace(tzinfo=timezone.utc) if value.tzinfo is None else value


def require_delivery():
    try:
        delivery_config()
    except (RuntimeError, ValueError) as exc:
        raise HTTPException(503, "Account email delivery is not configured") from exc


def reserve_mail_slot(db: Session, email: str, purpose: str) -> bool:
    """At most three attempted messages per identity/purpose in a rolling hour."""
    key = token_hash(email)
    row = db.scalar(select(AccountMailThrottle).where(
        AccountMailThrottle.identity_hash == key,
        AccountMailThrottle.purpose == purpose,
    ).with_for_update())
    if row is None:
        row = AccountMailThrottle(identity_hash=key, purpose=purpose,
                                  sent_count=0, window_started_at=now())
        db.add(row)
        db.flush()
    if as_utc(row.window_started_at) <= now() - timedelta(hours=1):
        row.sent_count = 0
        row.window_started_at = now()
    if row.sent_count >= 3:
        return False
    row.sent_count += 1
    return True


def issue_token(db: Session, user: User, purpose: Literal["verify", "reset"]) -> str:
    """Caller sends link separately after its enclosing transaction commits."""
    db.execute(update(AccountActionToken).where(
        AccountActionToken.user_id == user.id,
        AccountActionToken.purpose == purpose,
        AccountActionToken.consumed_at.is_(None),
    ).values(consumed_at=now()))
    raw = secrets.token_urlsafe(32)
    db.add(AccountActionToken(
        user_id=user.id, purpose=purpose, digest=token_hash(raw),
        expires_at=now() + (timedelta(hours=24) if purpose == "verify"
                             else timedelta(minutes=30)),
    ))
    db.flush()
    return raw


def consume_token(db: Session, raw: str, purpose: str) -> AccountActionToken:
    token = db.scalar(select(AccountActionToken).where(
        AccountActionToken.digest == token_hash(raw),
        AccountActionToken.purpose == purpose,
    ).with_for_update())
    if (token is None or token.consumed_at is not None
            or as_utc(token.expires_at) <= now()):
        raise HTTPException(400, "Token expired or already used")
    return token


def first_membership(db: Session, user: User):
    member = db.scalar(select(Membership).where(
        Membership.user_id == user.id
    ).order_by(Membership.id))
    if not member:
        raise HTTPException(409, "Account has no organization membership")
    space = db.get(Workspace, member.workspace_id)
    if space is None:
        raise HTTPException(409, "Organization unavailable")
    return member, space


def issue_email_and_deliver(db: Session, user: User, purpose: Literal["verify", "reset"]):
    """Issue after validating config, commit token then send via selected transport."""
    raw = issue_token(db, user, purpose)
    db.commit()
    try:
        send_account_link(user.email, purpose, raw)
    except Exception as exc:
        # Never include raw bearer token or SMTP exception in HTTP output.
        raise HTTPException(503, "Email delivery failed; retry later") from exc


@router.post("/verification/request", status_code=202)
def request_verification(payload: EmailRequest, db: Session = Depends(get_db)):
    email = normalized_email(payload.email)
    require_delivery()
    allowed = reserve_mail_slot(db, email, "verify")
    user = db.scalar(select(User).where(User.email == email))
    if allowed and user is not None and user.email_verified_at is None:
        issue_email_and_deliver(db, user, "verify")
    else:
        db.commit()
    return {"message": "If the account needs verification, a message was sent."}


@router.post("/verify")
def verify_email(payload: SecretRequest, db: Session = Depends(get_db)):
    token = consume_token(db, payload.token, "verify")
    user = db.get(User, token.user_id)
    if user is None:
        raise HTTPException(400, "Token expired or already used")
    member, space = first_membership(db, user)
    if user.email_verified_at is None:
        user.email_verified_at = now()
    token.consumed_at = now()
    db.execute(update(AccountActionToken).where(
        AccountActionToken.user_id == user.id,
        AccountActionToken.purpose == "verify",
        AccountActionToken.consumed_at.is_(None),
    ).values(consumed_at=now()))
    db.add(AuditEvent(
        actor_id=user.id, workspace_id=space.id,
        action="account.email_verified", resource_id=user.id,
    ))
    session = create_session(db, user.id, space.id)
    db.commit()
    return {"session_token": session, "account": {
        "user_id": user.id, "email": user.email,
        "workspace_id": space.id, "workspace_name": space.name,
        "role": member.role, "email_verified": True,
    }}


@router.post("/password/forgot", status_code=202)
def forgot_password(payload: EmailRequest, db: Session = Depends(get_db)):
    email = normalized_email(payload.email)
    require_delivery()
    allowed = reserve_mail_slot(db, email, "reset")
    user = db.scalar(select(User).where(User.email == email))
    if allowed and user is not None and user.email_verified_at is not None:
        issue_email_and_deliver(db, user, "reset")
    else:
        db.commit()
    return {"message": "If that verified account exists, recovery instructions were sent."}


@router.post("/password/reset")
def reset_password(payload: PasswordReset, db: Session = Depends(get_db)):
    token = consume_token(db, payload.token, "reset")
    user = db.get(User, token.user_id)
    if user is None or user.email_verified_at is None:
        raise HTTPException(400, "Token expired or already used")
    user.password_hash = hash_password(payload.password)
    token.consumed_at = now()
    db.execute(update(AccountActionToken).where(
        AccountActionToken.user_id == user.id,
        AccountActionToken.purpose == "reset",
        AccountActionToken.consumed_at.is_(None),
    ).values(consumed_at=now()))
    db.execute(SessionToken.__table__.delete().where(SessionToken.user_id == user.id))
    for member in db.scalars(select(Membership).where(Membership.user_id == user.id)):
        db.add(AuditEvent(
            actor_id=user.id, workspace_id=member.workspace_id,
            action="account.password_reset", resource_id=user.id,
        ))
    db.commit()
    return {"password_reset": True, "sessions_revoked": True}
