from __future__ import annotations

import hashlib
import hmac
import os
import re
import secrets
from dataclasses import dataclass
from datetime import timedelta, timezone, datetime

from fastapi import Depends, Header, HTTPException, status
from sqlalchemy import select
from sqlalchemy.orm import Session

from .db import get_db, now
from .models import Membership, SessionToken, User

_EMAIL = re.compile(r"^[^\s@]+@[^\s@]+\.[^\s@]+$")


def normalized_email(value: str) -> str:
    email = value.strip().lower()
    if len(email) > 254 or not _EMAIL.fullmatch(email):
        raise HTTPException(422, "Valid email address required")
    return email


def hash_password(raw: str) -> str:
    if len(raw) < 12 or len(raw.encode("utf-8")) > 128:
        raise HTTPException(422, "Password must be 12+ characters, max 128 UTF-8 bytes")
    salt = secrets.token_bytes(16)
    digest = hashlib.scrypt(raw.encode("utf-8"), salt=salt, n=2**14, r=8, p=1)
    return "scrypt$" + salt.hex() + "$" + digest.hex()


def check_password(raw: str, stored: str) -> bool:
    try:
        algorithm, salt_hex, digest_hex = stored.split("$", 2)
        if algorithm != "scrypt":
            return False
        candidate = hashlib.scrypt(
            raw.encode("utf-8"), salt=bytes.fromhex(salt_hex), n=2**14, r=8, p=1
        )
        return hmac.compare_digest(candidate, bytes.fromhex(digest_hex))
    except (ValueError, OverflowError):
        return False


def token_hash(raw: str) -> str:
    return hashlib.sha256(raw.encode("ascii")).hexdigest()


def create_session(db: Session, user_id: str, workspace_id: str) -> str:
    token = secrets.token_urlsafe(32)
    db.add(SessionToken(
        token_hash=token_hash(token), user_id=user_id, workspace_id=workspace_id,
        expires_at=now() + timedelta(days=7),
    ))
    db.flush()
    return token


def require_gateway(x_trumonit_gateway: str | None = Header(default=None)) -> None:
    secret = os.environ.get("TRUMONIT_GATEWAY_TOKEN", "")
    if len(secret) < 32 or secret.startswith("REPLACE_"):
        raise HTTPException(status.HTTP_503_SERVICE_UNAVAILABLE, "Gateway configuration missing")
    if x_trumonit_gateway is None or not hmac.compare_digest(secret, x_trumonit_gateway):
        raise HTTPException(status.HTTP_401_UNAUTHORIZED, "Gateway authorization required")


@dataclass(frozen=True)
class Principal:
    user_id: str
    workspace_id: str
    email: str
    workspace_name: str
    role: str


def principal(
    x_trumonit_session: str | None = Header(default=None),
    db: Session = Depends(get_db),
) -> Principal:
    if not x_trumonit_session or not re.fullmatch(r"[A-Za-z0-9_-]{43}", x_trumonit_session):
        raise HTTPException(401, "Sign in required")
    entry = db.get(SessionToken, token_hash(x_trumonit_session))
    if entry is None:
        raise HTTPException(401, "Invalid session")
    expiry = entry.expires_at
    if expiry.tzinfo is None:
        expiry = expiry.replace(tzinfo=timezone.utc)
    if expiry <= now():
        raise HTTPException(401, "Session expired")
    member = db.scalar(select(Membership).where(
        Membership.user_id == entry.user_id,
        Membership.workspace_id == entry.workspace_id,
    ))
    user = db.get(User, entry.user_id)
    from .models import Workspace
    workspace = db.get(Workspace, entry.workspace_id)
    if member is None or user is None or workspace is None:
        raise HTTPException(401, "Account access revoked")
    return Principal(user.id, workspace.id, user.email, workspace.name, member.role)
