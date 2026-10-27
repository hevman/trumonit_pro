"""Company teams: role-scoped membership and manually delivered single-use invites.

Invitations are handed to owners once and should be shared using a private
channel. Email delivery/verification and enterprise SSO are NOT implemented.
"""
from __future__ import annotations

import re
import secrets
from datetime import timedelta, timezone
from typing import Literal

from fastapi import APIRouter, Depends, Header, HTTPException
from pydantic import BaseModel, Field
from sqlalchemy import func, select, update
from sqlalchemy.exc import IntegrityError
from sqlalchemy.orm import Session

from .db import get_db, now
from .models import (AuditEvent, Membership, RequirementTask, SessionToken,
                     TeamInvitation, User, Workspace)
from .security import (Principal, create_session, hash_password,
                       normalized_email, principal, token_hash)

router = APIRouter(prefix="/team", tags=["Workspace collaboration"])
INVITE_RE = re.compile(r"[A-Za-z0-9_-]{43}")
TEAM_LIMIT = 25


def owner_only(user: Principal):
    if user.role != "owner":
        raise HTTPException(403, "Workspace owner permission required")


def utc(value):
    return value.replace(tzinfo=timezone.utc) if value.tzinfo is None else value


def clean_invite(db: Session, raw: str) -> TeamInvitation:
    if not INVITE_RE.fullmatch(raw):
        raise HTTPException(404, "Invitation unavailable")
    record = db.scalar(select(TeamInvitation).where(
        TeamInvitation.token_hash == token_hash(raw)
    ).with_for_update())
    if (record is None or record.revoked_at is not None
            or record.accepted_at is not None
            or utc(record.expires_at) <= now()):
        raise HTTPException(404, "Invitation unavailable")
    return record


def audit(db: Session, workspace_id: str, actor_id: str, action: str, resource_id: str):
    db.add(AuditEvent(
        workspace_id=workspace_id, actor_id=actor_id,
        action=action, resource_id=resource_id,
    ))


def member_count(db: Session, workspace_id: str):
    return db.scalar(select(func.count(Membership.id)).where(
        Membership.workspace_id == workspace_id,
    )) or 0


def member_data(row: Membership, user: User):
    return {
        "id": row.id, "user_id": user.id, "email": user.email,
        "display_name": user.display_name, "role": row.role,
    }


class InviteRequest(BaseModel):
    email: str = Field(min_length=5, max_length=254)
    role: Literal["editor", "viewer"] = "editor"


class InviteToken(BaseModel):
    token: str = Field(min_length=43, max_length=43)


class ClaimInvite(InviteToken):
    name: str = Field(min_length=2, max_length=100)
    password: str = Field(min_length=12, max_length=128)


class RoleUpdate(BaseModel):
    role: Literal["editor", "viewer"]


class SwitchWorkspace(BaseModel):
    workspace_id: str = Field(min_length=36, max_length=36)


@router.get("")
def list_team(user: Principal = Depends(principal), db: Session = Depends(get_db)):
    members = db.execute(select(Membership, User)
        .join(User, User.id == Membership.user_id)
        .where(Membership.workspace_id == user.workspace_id)
        .order_by(User.email).limit(TEAM_LIMIT)).all()
    pending = []
    if user.role == "owner":
        invitations = db.scalars(select(TeamInvitation).where(
            TeamInvitation.workspace_id == user.workspace_id,
            TeamInvitation.accepted_at.is_(None),
            TeamInvitation.revoked_at.is_(None),
            TeamInvitation.expires_at > now(),
        ).order_by(TeamInvitation.created_at.desc()).limit(TEAM_LIMIT)).all()
        pending = [{
            "id": inv.id, "email": inv.email, "role": inv.role,
            "expires_at": inv.expires_at.isoformat(),
        } for inv in invitations]
    return {"members": [member_data(row, u) for row, u in members],
            "invitations": pending}


@router.get("/workspaces")
def list_workspaces(user: Principal = Depends(principal),
                    db: Session = Depends(get_db)):
    rows = db.execute(select(Membership, Workspace)
        .join(Workspace, Workspace.id == Membership.workspace_id)
        .where(Membership.user_id == user.user_id)
        .order_by(Workspace.name).limit(TEAM_LIMIT)).all()
    return [{
        "id": space.id, "name": space.name, "role": member.role,
        "active": space.id == user.workspace_id,
    } for member, space in rows]


@router.post("/switch")
def switch_workspace(
    payload: SwitchWorkspace,
    user: Principal = Depends(principal),
    x_trumonit_session: str | None = Header(default=None),
    db: Session = Depends(get_db),
):
    member = db.scalar(select(Membership).where(
        Membership.workspace_id == payload.workspace_id,
        Membership.user_id == user.user_id,
    ))
    if member is None:
        raise HTTPException(404, "Workspace unavailable")
    session = db.get(SessionToken, token_hash(x_trumonit_session or ""))
    if session is None or session.user_id != user.user_id:
        raise HTTPException(401, "Session unavailable")
    session.workspace_id = payload.workspace_id
    db.commit()
    return {"workspace_id": payload.workspace_id, "role": member.role}


@router.post("/invitations", status_code=201)
def invite_member(payload: InviteRequest,
                  user: Principal = Depends(principal),
                  db: Session = Depends(get_db)):
    owner_only(user)
    email = normalized_email(payload.email)
    registered = db.scalar(select(User).where(User.email == email))
    if registered and db.scalar(select(Membership.id).where(
        Membership.user_id == registered.id,
        Membership.workspace_id == user.workspace_id,
    )):
        raise HTTPException(409, "User is already a workspace member")
    if member_count(db, user.workspace_id) >= TEAM_LIMIT:
        raise HTTPException(409, "Workspace member limit reached")
    pending = db.scalar(select(func.count(TeamInvitation.id)).where(
        TeamInvitation.workspace_id == user.workspace_id,
        TeamInvitation.accepted_at.is_(None),
        TeamInvitation.revoked_at.is_(None),
        TeamInvitation.expires_at > now(),
    )) or 0
    if pending >= TEAM_LIMIT:
        raise HTTPException(409, "Too many pending invitations")
    raw = secrets.token_urlsafe(32)
    invitation = TeamInvitation(
        workspace_id=user.workspace_id, email=email, role=payload.role,
        token_hash=token_hash(raw), invited_by=user.user_id,
        expires_at=now() + timedelta(days=3),
    )
    db.add(invitation)
    db.flush()
    audit(db, user.workspace_id, user.user_id, "team.invited", invitation.id)
    db.commit()
    # The bearer secret is never stored or readable again by an owner.
    return {"id": invitation.id, "token": raw, "email": email,
            "role": invitation.role, "expires_at": invitation.expires_at.isoformat()}


@router.post("/preview")
def preview_invitation(payload: InviteToken, db: Session = Depends(get_db)):
    invitation = clean_invite(db, payload.token)
    space = db.get(Workspace, invitation.workspace_id)
    if space is None:
        raise HTTPException(404, "Invitation unavailable")
    return {"workspace_name": space.name, "email": invitation.email,
            "role": invitation.role}


@router.post("/claim", status_code=201)
def claim_new_account(payload: ClaimInvite, db: Session = Depends(get_db)):
    invite = clean_invite(db, payload.token)
    if member_count(db, invite.workspace_id) >= TEAM_LIMIT:
        raise HTTPException(409, "Workspace member limit reached")
    if db.scalar(select(User.id).where(User.email == invite.email)):
        raise HTTPException(409, "Account exists. Sign in to accept this invitation.")
    space = db.get(Workspace, invite.workspace_id)
    if space is None:
        raise HTTPException(404, "Workspace unavailable")
    created = User(
        email=invite.email, display_name=payload.name.strip(),
        password_hash=hash_password(payload.password),
    )
    db.add(created)
    db.flush()
    db.add(Membership(
        user_id=created.id, workspace_id=space.id, role=invite.role
    ))
    invite.accepted_at = now()
    token = create_session(db, created.id, space.id)
    audit(db, space.id, created.id, "team.joined", invite.id)
    try:
        db.commit()
    except IntegrityError as exc:
        db.rollback()
        raise HTTPException(409, "Invitation was already accepted") from exc
    return {"session_token": token, "account": {
        "user_id": created.id, "email": created.email,
        "workspace_id": space.id, "workspace_name": space.name,
        "role": invite.role,
    }}


@router.post("/accept")
def accept_existing_account(
    payload: InviteToken,
    user: Principal = Depends(principal),
    x_trumonit_session: str | None = Header(default=None),
    db: Session = Depends(get_db),
):
    invite = clean_invite(db, payload.token)
    if invite.email != user.email:
        raise HTTPException(403, "Invitation is reserved for a different email")
    if member_count(db, invite.workspace_id) >= TEAM_LIMIT:
        raise HTTPException(409, "Workspace member limit reached")
    if db.scalar(select(Membership.id).where(
        Membership.user_id == user.user_id,
        Membership.workspace_id == invite.workspace_id,
    )):
        raise HTTPException(409, "Already a member")
    session = db.get(SessionToken, token_hash(x_trumonit_session or ""))
    if session is None or session.user_id != user.user_id:
        raise HTTPException(401, "Session unavailable")
    db.add(Membership(user_id=user.user_id,
                      workspace_id=invite.workspace_id, role=invite.role))
    session.workspace_id = invite.workspace_id
    invite.accepted_at = now()
    audit(db, invite.workspace_id, user.user_id, "team.joined", invite.id)
    try:
        db.commit()
    except IntegrityError as exc:
        db.rollback()
        raise HTTPException(409, "Invitation was already accepted") from exc
    return {"workspace_id": invite.workspace_id, "role": invite.role}


@router.post("/invitations/{invitation_id}/revoke")
def revoke_invitation(invitation_id: str,
                      user: Principal = Depends(principal),
                      db: Session = Depends(get_db)):
    owner_only(user)
    invite = db.scalar(select(TeamInvitation).where(
        TeamInvitation.id == invitation_id,
        TeamInvitation.workspace_id == user.workspace_id,
    ).with_for_update())
    if invite is None:
        raise HTTPException(404, "Invitation not found")
    if invite.accepted_at is not None:
        raise HTTPException(409, "Accepted invitation cannot be revoked")
    invite.revoked_at = now()
    audit(db, user.workspace_id, user.user_id, "team.invite_revoked", invite.id)
    db.commit()
    return {"revoked": True}


@router.patch("/members/{membership_id}")
def change_member_role(membership_id: str, payload: RoleUpdate,
                       user: Principal = Depends(principal),
                       db: Session = Depends(get_db)):
    owner_only(user)
    membership = db.scalar(select(Membership).where(
        Membership.id == membership_id,
        Membership.workspace_id == user.workspace_id,
    ))
    if membership is None:
        raise HTTPException(404, "Member not found")
    if membership.role == "owner":
        raise HTTPException(409, "An owner cannot be demoted using this endpoint")
    membership.role = payload.role
    audit(db, user.workspace_id, user.user_id, "team.role_changed", membership.id)
    db.commit()
    return {"id": membership.id, "role": membership.role}


@router.post("/members/{membership_id}/remove")
def remove_member(membership_id: str,
                  user: Principal = Depends(principal),
                  db: Session = Depends(get_db)):
    owner_only(user)
    member = db.scalar(select(Membership).where(
        Membership.id == membership_id,
        Membership.workspace_id == user.workspace_id,
    ))
    if member is None:
        raise HTTPException(404, "Member not found")
    if member.role == "owner":
        raise HTTPException(409, "Owner membership cannot be removed")
    # Existing sessions in other workspaces are retained. Sessions for this
    # workspace are revoked immediately.
    affected_user = member.user_id
    db.execute(update(RequirementTask).where(
        RequirementTask.workspace_id == user.workspace_id,
        RequirementTask.assignee_id == affected_user,
    ).values(assignee_id=None, updated_by=user.user_id, updated_at=now()))
    sessions = db.scalars(select(SessionToken).where(
        SessionToken.user_id == affected_user,
        SessionToken.workspace_id == user.workspace_id,
    )).all()
    for entry in sessions:
        db.delete(entry)
    db.delete(member)
    audit(db, user.workspace_id, user.user_id, "team.member_removed", membership_id)
    db.commit()
    return {"removed": True}
