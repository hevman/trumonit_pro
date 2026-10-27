"""Organization-scoped retention and safe, explicit project deletion.

A dry-run reports what would be removed. Destructive operations require an
owner, an exact project-name confirmation, and write an audit record.
No automatic expiry or hidden deletion of customer documents.
"""
from __future__ import annotations

from fastapi import APIRouter, Depends, HTTPException
from pydantic import BaseModel, Field
from sqlalchemy import delete, func, select
from sqlalchemy.orm import Session

from .db import get_db
from .models import (
    AuditEvent, Document, Project, Requirement, RequirementResponse, RequirementTask,
)
from .projects import owned_project
from .security import Principal, principal

router = APIRouter(prefix="/projects", tags=["Data lifecycle"])


def require_owner(user: Principal):
    if user.role != "owner":
        raise HTTPException(403, "Workspace owner permission required")


class DeleteConfirmation(BaseModel):
    confirm_project_name: str = Field(min_length=1, max_length=140)


@router.get("/{project_id}/deletion-preview")
def deletion_preview(project_id: str, user: Principal = Depends(principal),
                     db: Session = Depends(get_db)):
    require_owner(user)
    project = owned_project(db, project_id, user)
    filters = (Requirement.workspace_id == user.workspace_id,
               Requirement.project_id == project.id)
    requirements = db.scalar(select(func.count(Requirement.id)).where(*filters)) or 0
    documents = db.scalar(select(func.count(Document.id)).where(
        Document.workspace_id == user.workspace_id, Document.project_id == project.id)) or 0
    tasks = db.scalar(select(func.count(RequirementTask.id)).where(
        RequirementTask.workspace_id == user.workspace_id,
        RequirementTask.project_id == project.id)) or 0
    responses = db.scalar(select(func.count(RequirementResponse.id)).where(
        RequirementResponse.workspace_id == user.workspace_id,
        RequirementResponse.project_id == project.id)) or 0
    bytes_total = db.scalar(select(func.coalesce(func.sum(Document.byte_count), 0)).where(
        Document.workspace_id == user.workspace_id, Document.project_id == project.id)) or 0
    return {"project_id": project.id, "project_name": project.name,
            "documents": documents, "requirements": requirements,
            "responses": responses, "tasks": tasks, "document_bytes": bytes_total,
            "audit_events_preserved": True}


@router.post("/{project_id}/delete")
def delete_project(project_id: str, payload: DeleteConfirmation,
                   user: Principal = Depends(principal),
                   db: Session = Depends(get_db)):
    require_owner(user)
    project = owned_project(db, project_id, user)
    if payload.confirm_project_name != project.name:
        raise HTTPException(409, "Project name confirmation does not match")
    counts = deletion_preview(project_id, user, db)
    filters = lambda model: (model.workspace_id == user.workspace_id,
                             model.project_id == project.id)
    # Remove dependent rows first; SQLAlchemy models intentionally use restrictive FKs.
    for model in (RequirementResponse, RequirementTask, Requirement, Document):
        db.execute(delete(model).where(*filters(model)))
    db.delete(project)
    db.add(AuditEvent(workspace_id=user.workspace_id, actor_id=user.user_id,
                      action="project.deleted", resource_id=project.id))
    db.commit()
    return {"deleted": True, "project_id": project_id,
            "removed": {key: counts[key] for key in
                        ("documents", "requirements", "responses", "tasks", "document_bytes")},
            "audit_events_preserved": True}
