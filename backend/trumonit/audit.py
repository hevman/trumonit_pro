"""Tenant-bound activity history for owner review."""
from __future__ import annotations

from fastapi import APIRouter, Depends, HTTPException
from sqlalchemy import select
from sqlalchemy.orm import Session

from .db import get_db
from .models import AuditEvent, User
from .security import Principal, principal

router = APIRouter(tags=["Organization audit"])


@router.get("/audit")
def audit_history(user: Principal = Depends(principal),
                  db: Session = Depends(get_db)):
    if user.role != "owner":
        raise HTTPException(403, "Only organization owners can access audit history")
    rows = db.execute(
        select(AuditEvent, User.email)
        .join(User, User.id == AuditEvent.actor_id)
        .where(AuditEvent.workspace_id == user.workspace_id)
        .order_by(AuditEvent.created_at.desc(), AuditEvent.id.desc())
        .limit(250)
    ).all()
    return [
        {"id": event.id, "action": event.action, "resource_id": event.resource_id,
         "actor_email": email, "created_at": event.created_at.isoformat()}
        for event, email in rows
    ]
