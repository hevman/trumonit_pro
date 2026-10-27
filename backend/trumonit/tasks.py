"""Accountable per-requirement task assignments for a company's bid team."""
from __future__ import annotations

from datetime import date, datetime, timezone
from typing import Literal

from fastapi import APIRouter, Depends, HTTPException
from pydantic import BaseModel, Field
from sqlalchemy import select
from sqlalchemy.orm import Session

from .db import get_db, now
from .models import Membership, Requirement, RequirementTask, User
from .projects import owned_project, record
from .security import Principal, principal, require_editor

router = APIRouter(prefix="/projects", tags=["Bid team tasks"])


class TaskUpdate(BaseModel):
    assignee_id: str | None = Field(default=None, max_length=36)
    due_date: date | None = None
    status: Literal["open", "in_progress", "done"] = "open"
    note: str = Field(default="", max_length=1000)


def task_data(task: RequirementTask, assignee: User | None = None):
    return {
        "id": task.id, "requirement_id": task.requirement_id,
        "assignee_id": task.assignee_id,
        "assignee_email": assignee.email if assignee else None,
        "due_date": task.due_date, "status": task.status, "note": task.note,
        "updated_at": task.updated_at.isoformat(),
        "updated_by": task.updated_by,
        "overdue": bool(task.due_date and task.status != "done" and
                        task.due_date < date.today().isoformat()),
    }


@router.get("/{project_id}/tasks")
def list_tasks(project_id: str,
               user: Principal = Depends(principal),
               db: Session = Depends(get_db)):
    owned_project(db, project_id, user)
    rows = db.execute(
        select(RequirementTask, User)
        .outerjoin(User, User.id == RequirementTask.assignee_id)
        .where(
            RequirementTask.workspace_id == user.workspace_id,
            RequirementTask.project_id == project_id,
        ).order_by(RequirementTask.due_date, RequirementTask.updated_at)
        .limit(500)
    ).all()
    return [task_data(task, assignee) for task, assignee in rows]


@router.patch("/{project_id}/tasks/{requirement_id}")
def update_task(project_id: str, requirement_id: str, payload: TaskUpdate,
                user: Principal = Depends(principal),
                db: Session = Depends(get_db)):
    owned_project(db, project_id, user)
    requirement = db.scalar(select(Requirement).where(
        Requirement.id == requirement_id,
        Requirement.project_id == project_id,
        Requirement.workspace_id == user.workspace_id,
    ))
    if requirement is None:
        raise HTTPException(404, "Requirement not found")
    require_editor(user)
    assignee = None
    if payload.assignee_id is not None:
        membership = db.scalar(select(Membership).where(
            Membership.user_id == payload.assignee_id,
            Membership.workspace_id == user.workspace_id,
        ))
        if membership is None:
            raise HTTPException(422, "Assignee must be a member of this workspace")
        assignee = db.get(User, payload.assignee_id)
    task = db.scalar(select(RequirementTask).where(
        RequirementTask.requirement_id == requirement.id,
        RequirementTask.project_id == project_id,
        RequirementTask.workspace_id == user.workspace_id,
    ))
    if task is None:
        task = RequirementTask(
            workspace_id=user.workspace_id, project_id=project_id,
            requirement_id=requirement.id, updated_by=user.user_id,
        )
        db.add(task)
    task.assignee_id = payload.assignee_id
    task.due_date = payload.due_date.isoformat() if payload.due_date else None
    task.status = payload.status
    task.note = payload.note.strip()
    task.updated_by = user.user_id
    task.updated_at = now()
    db.flush()
    record(db, user, "task.updated", task.id)
    db.commit()
    return task_data(task, assignee)
