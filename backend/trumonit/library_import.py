"""Import a customer's existing knowledge base without AI or data sharing.

Every imported row is a *draft* until an owner approves it. No external source,
search engine, language model, or pre-existing Trumonit database is consulted.
"""
from __future__ import annotations

import csv
import io
from datetime import date

from fastapi import APIRouter, Depends, File, HTTPException, UploadFile
from fastapi.responses import Response
from sqlalchemy import select
from sqlalchemy.orm import Session

from .db import get_db
from .models import AnswerLibraryEntry
from .projects import record
from .security import Principal, principal

router = APIRouter(prefix="/library", tags=["Company answer library"])
MAX_CSV_BYTES = 256 * 1024
MAX_LIBRARY_ENTRIES = 200
EXPECTED = {"title", "body"}
ALLOWED = {"title", "category", "body", "evidence_reference", "valid_until"}


@router.get("/template")
def download_template(user: Principal = Depends(principal)):
    example = (
        "title,category,body,evidence_reference,valid_until\r\n"
        '"Proof of coverage",legal,"Our company maintains professional '
        'indemnity insurance. Confirm coverage and attach a current '
        'certificate.","Policy document controlled by the company",\r\n'
    )
    return Response(
        example.encode("utf-8-sig"), media_type="text/csv; charset=utf-8",
        headers={
            "Content-Disposition": 'attachment; filename="trumonit-answer-template.csv"',
            "Cache-Control": "no-store", "X-Content-Type-Options": "nosniff",
        },
    )


@router.post("/import", status_code=201)
async def import_library(
    file: UploadFile = File(...),
    user: Principal = Depends(principal),
    db: Session = Depends(get_db),
):
    if not (file.filename or "").lower().endswith(".csv"):
        raise HTTPException(415, "Upload a UTF-8 CSV file")
    raw = await file.read(MAX_CSV_BYTES + 1)
    if not raw or len(raw) > MAX_CSV_BYTES:
        raise HTTPException(413, "CSV must be 1 byte to 256 KiB")
    try:
        decoded = raw.decode("utf-8-sig")
    except UnicodeDecodeError as exc:
        raise HTTPException(422, "CSV must use UTF-8 encoding") from exc
    try:
        rows = csv.DictReader(io.StringIO(decoded, newline=""), strict=True)
        if not rows.fieldnames:
            raise HTTPException(422, "CSV header missing")
        columns = [c.strip() for c in rows.fieldnames]
        if len(set(columns)) != len(columns):
            raise HTTPException(422, "Duplicate CSV header")
        if not EXPECTED.issubset(columns) or not set(columns).issubset(ALLOWED):
            raise HTTPException(422, "CSV requires title,body and optional category,evidence_reference,valid_until")
        staged: list[dict] = []
        names: set[str] = set()
        duplicates = 0
        for row_number, source in enumerate(rows, 2):
            if row_number > MAX_LIBRARY_ENTRIES + 1:
                raise HTTPException(422, "CSV may contain no more than 200 rows")
            if None in source or any(value is None for value in source.values()):
                raise HTTPException(422, f"Invalid CSV structure at row {row_number}")
            row = {k.strip(): v.strip() for k, v in source.items()}
            title = row.get("title", "")
            body = row.get("body", "")
            category = row.get("category") or "general"
            evidence = row.get("evidence_reference", "")
            valid = row.get("valid_until", "")
            if not 3 <= len(title) <= 150 or not 15 <= len(body) <= 10000:
                raise HTTPException(422, f"Invalid title or answer length at row {row_number}")
            if len(category) > 40 or len(evidence) > 400:
                raise HTTPException(422, f"Category or reference too long at row {row_number}")
            if valid:
                try:
                    date.fromisoformat(valid)
                except ValueError as exc:
                    raise HTTPException(422, f"Invalid valid_until date at row {row_number}") from exc
            key = title.casefold()
            if key in names:
                duplicates += 1
                continue
            names.add(key)
            staged.append({
                "title": title, "body": body, "category": category,
                "evidence_reference": evidence, "valid_until": valid or None,
            })
    except csv.Error as exc:
        raise HTTPException(422, "Malformed CSV quoting") from exc

    existing = db.scalars(select(AnswerLibraryEntry).where(
        AnswerLibraryEntry.workspace_id == user.workspace_id,
    )).all()
    existing_names = {entry.title.casefold() for entry in existing}
    accepted = [row for row in staged if row["title"].casefold() not in existing_names]
    duplicates += len(staged) - len(accepted)
    if len(existing) + len(accepted) > MAX_LIBRARY_ENTRIES:
        raise HTTPException(409, "Answer library limit of 200 entries reached")
    for row in accepted:
        db.add(AnswerLibraryEntry(
            workspace_id=user.workspace_id, title=row["title"],
            body=row["body"], category=row["category"],
            evidence_reference=row["evidence_reference"],
            valid_until=row["valid_until"], status="draft",
        ))
    db.flush()
    if accepted:
        record(db, user, "library.csv_imported", user.workspace_id)
    db.commit()
    return {
        "imported": len(accepted), "skipped_duplicates": duplicates,
        "status": "draft", "review_required": True,
    }
