from __future__ import annotations

import csv
import hashlib
import io
import re
from datetime import date
from pathlib import PurePath

from fastapi import APIRouter, Depends, File, HTTPException, UploadFile
from fastapi.responses import Response
from pydantic import BaseModel, Field
from sqlalchemy import func, select
from sqlalchemy.orm import Session

from .db import get_db
from .extract import MAX_DOCUMENT_BYTES, candidate_segments
from .models import AuditEvent, Document, Project, Requirement
from .security import Principal, principal, require_editor

router = APIRouter(prefix="/projects", tags=["RFP projects"])


class ProjectNew(BaseModel):
    name: str = Field(min_length=3, max_length=140)
    buyer: str = Field(default="", max_length=140)
    deadline: date | None = None


class ProjectStage(BaseModel):
    stage: str = Field(pattern=r"^(review|preparing|submitted|won|lost|archived)$")


class RequirementReview(BaseModel):
    status: str = Field(pattern=r"^(unreviewed|met|gap|needs_review|not_applicable)$")
    note: str = Field(default="", max_length=1000)


def owned_project(db: Session, project_id: str, user: Principal) -> Project:
    project = db.scalar(select(Project).where(
        Project.id == project_id, Project.workspace_id == user.workspace_id,
    ))
    if project is None:
        raise HTTPException(404, "Project not found")
    return project


def project_data(db: Session, project: Project) -> dict:
    counts = dict(db.execute(
        select(Requirement.review_status, func.count(Requirement.id))
        .where(Requirement.project_id == project.id,
               Requirement.workspace_id == project.workspace_id)
        .group_by(Requirement.review_status)
    ).all())
    return {
        "id": project.id, "name": project.name, "buyer": project.buyer,
        "deadline": project.deadline, "stage": project.stage,
        "created_at": project.created_at.isoformat(),
        "requirement_counts": counts,
        "requirements_total": sum(counts.values()),
    }


def record(db: Session, user: Principal, action: str, resource_id: str):
    db.add(AuditEvent(
        workspace_id=user.workspace_id, actor_id=user.user_id,
        action=action, resource_id=resource_id,
    ))


@router.post("", status_code=201)
def create_project(
    payload: ProjectNew,
    user: Principal = Depends(principal),
    db: Session = Depends(get_db),
):
    require_editor(user)
    project = Project(
        workspace_id=user.workspace_id, name=payload.name.strip(),
        buyer=payload.buyer.strip(),
        deadline=payload.deadline.isoformat() if payload.deadline else None,
    )
    db.add(project)
    db.flush()
    record(db, user, "project.created", project.id)
    db.commit()
    return project_data(db, project)


@router.get("")
def list_projects(
    user: Principal = Depends(principal),
    db: Session = Depends(get_db),
):
    rows = db.scalars(
        select(Project).where(Project.workspace_id == user.workspace_id)
        .order_by(Project.created_at.desc()).limit(100)
    ).all()
    return [project_data(db, item) for item in rows]


@router.get("/{project_id}")
def get_project(
    project_id: str,
    user: Principal = Depends(principal),
    db: Session = Depends(get_db),
):
    project = owned_project(db, project_id, user)
    documents = db.scalars(select(Document).where(
        Document.project_id == project.id, Document.workspace_id == user.workspace_id,
    ).order_by(Document.created_at)).all()
    requirements = db.scalars(select(Requirement).where(
        Requirement.project_id == project.id, Requirement.workspace_id == user.workspace_id,
    ).order_by(Requirement.created_at, Requirement.id).limit(500)).all()
    return {
        **project_data(db, project),
        "documents": [
            {
                "id": item.id, "filename": item.filename, "sha256": item.sha256,
                "byte_count": item.byte_count, "uploaded_at": item.created_at.isoformat(),
            } for item in documents
        ],
        "requirements": [
            {
                "id": item.id, "document_id": item.document_id,
                "locator": item.locator, "evidence": item.evidence,
                "category": item.category, "status": item.review_status,
                "note": item.note,
            } for item in requirements
        ],
        "requirements_truncated": sum(project_data(db, project)["requirement_counts"].values()) > 500,
    }


@router.patch("/{project_id}")
def update_project(
    project_id: str,
    payload: ProjectStage,
    user: Principal = Depends(principal),
    db: Session = Depends(get_db),
):
    project = owned_project(db, project_id, user)
    require_editor(user)
    project.stage = payload.stage
    record(db, user, "project.stage_changed", project.id)
    db.commit()
    return project_data(db, project)


@router.post("/{project_id}/documents", status_code=201)
async def upload_document(
    project_id: str,
    file: UploadFile = File(...),
    user: Principal = Depends(principal),
    db: Session = Depends(get_db),
):
    project = owned_project(db, project_id, user)
    require_editor(user)
    filename = (file.filename or "").replace("\\", "/").split("/")[-1]
    if not filename or len(filename) > 180 or "\x00" in filename:
        raise HTTPException(422, "Invalid document filename")
    if not filename.lower().endswith((".pdf", ".docx")):
        raise HTTPException(415, "Only PDF and DOCX supported")
    content = await file.read(MAX_DOCUMENT_BYTES + 1)
    if not content or len(content) > MAX_DOCUMENT_BYTES:
        raise HTTPException(413, "Maximum file size is 8 MiB")
    document_count = db.scalar(select(func.count(Document.id)).where(
        Document.project_id == project.id, Document.workspace_id == user.workspace_id
    ))
    if document_count is not None and document_count >= 25:
        raise HTTPException(422, "Maximum 25 documents per project")
    digest = hashlib.sha256(content).hexdigest()
    exists = db.scalar(select(Document.id).where(
        Document.project_id == project.id, Document.workspace_id == user.workspace_id,
        Document.sha256 == digest,
    ))
    if exists:
        raise HTTPException(409, "This exact document is already imported")
    candidates = candidate_segments(filename, content)
    document = Document(
        workspace_id=user.workspace_id, project_id=project.id,
        filename=filename, sha256=digest, byte_count=len(content),
        content_type="application/pdf" if filename.lower().endswith(".pdf")
        else "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
        content=content,
    )
    db.add(document)
    db.flush()
    for item in candidates:
        db.add(Requirement(
            workspace_id=user.workspace_id, project_id=project.id,
            document_id=document.id, locator=item.locator,
            evidence=item.evidence, category=item.category,
        ))
    record(db, user, "document.uploaded", document.id)
    db.commit()
    return {
        "id": document.id, "filename": document.filename,
        "requirements_created": len(candidates),
        "note": "Extracted clauses are unverified candidates; confirm them manually."
        if candidates else "No matching clauses found. Check whether the file contains selectable text.",
    }


@router.get("/{project_id}/documents/{document_id}")
def download_document(
    project_id: str, document_id: str,
    user: Principal = Depends(principal),
    db: Session = Depends(get_db),
):
    owned_project(db, project_id, user)
    doc = db.scalar(select(Document).where(
        Document.id == document_id, Document.project_id == project_id,
        Document.workspace_id == user.workspace_id,
    ))
    if doc is None:
        raise HTTPException(404, "Document not found")
    safe_name = re.sub(r"[^A-Za-z0-9_.-]", "_", doc.filename)
    return Response(
        content=doc.content, media_type="application/octet-stream",
        headers={"Content-Disposition": f'attachment; filename="{safe_name}"',
                 "X-Content-Type-Options": "nosniff"},
    )


@router.patch("/{project_id}/requirements/{requirement_id}")
def review_requirement(
    project_id: str, requirement_id: str, payload: RequirementReview,
    user: Principal = Depends(principal),
    db: Session = Depends(get_db),
):
    owned_project(db, project_id, user)
    require_editor(user)
    requirement = db.scalar(select(Requirement).where(
        Requirement.id == requirement_id,
        Requirement.project_id == project_id,
        Requirement.workspace_id == user.workspace_id,
    ))
    if requirement is None:
        raise HTTPException(404, "Requirement not found")
    requirement.review_status = payload.status
    requirement.note = payload.note.strip()
    if payload.status != "met":
        from .models import RequirementResponse
        associated = db.scalar(select(RequirementResponse).where(
            RequirementResponse.requirement_id == requirement.id,
            RequirementResponse.project_id == project_id,
            RequirementResponse.workspace_id == user.workspace_id,
        ))
        if associated is not None and associated.status == "approved":
            associated.status = "draft"
            associated.approved_at = None
            associated.approved_by = None
            record(db, user, "response.approval_revoked", associated.id)
    record(db, user, "requirement.reviewed", requirement.id)
    db.commit()
    return {"id": requirement.id, "status": requirement.review_status, "note": requirement.note}


def safe_csv(value: str) -> str:
    # Spreadsheet formula injection prevention.
    stripped = value.lstrip()
    if stripped.startswith(("=", "+", "-", "@")) or value[:1] in ("\t", "\r", "\n"):
        return "'" + value
    return value


@router.get("/{project_id}/export")
def export_requirements(
    project_id: str,
    user: Principal = Depends(principal),
    db: Session = Depends(get_db),
):
    owned_project(db, project_id, user)
    rows = db.scalars(select(Requirement).where(
        Requirement.project_id == project_id, Requirement.workspace_id == user.workspace_id,
    ).order_by(Requirement.document_id, Requirement.locator).limit(6250)).all()
    stream = io.StringIO()
    writer = csv.writer(stream)
    writer.writerow(["category", "source", "evidence", "status", "reviewer_note"])
    for requirement in rows:
        writer.writerow([
            safe_csv(requirement.category), safe_csv(requirement.locator),
            safe_csv(requirement.evidence), safe_csv(requirement.review_status),
            safe_csv(requirement.note),
        ])
    return Response(
        content=stream.getvalue().encode("utf-8-sig"), media_type="text/csv; charset=utf-8",
        headers={"Content-Disposition": 'attachment; filename="trumonit-requirements.csv"',
                 "X-Content-Type-Options": "nosniff"},
    )
