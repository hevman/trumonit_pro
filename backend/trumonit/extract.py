"""Transparent, source-linked RFP requirement CANDIDATES, not legal conclusions.

No external LLM or network call: text-only PDF and DOCX are supported.
The reviewer must confirm every candidate and its relevance.
"""
from __future__ import annotations

import io
import re
import zipfile
from dataclasses import dataclass

import fitz
from docx import Document as WordDocument
from fastapi import HTTPException

MAX_DOCUMENT_BYTES = 8 * 1024 * 1024
MAX_EXTRACTED_CHARS = 1_000_000
MAX_REQUIREMENTS = 250
KEYWORDS = re.compile(
    r"\b(musi|muszą|należy|wymaga(?:ne|ny|ją)?|obowiązkow[ey]|"
    r"warunek|kryterium|termin|doświadczenie|zaświadczenie|"
    r"shall|must|required|mandatory|deadline|qualification|"
    r"provide|submit|evidence|certificate|compliance)\b",
    re.IGNORECASE,
)
DEADLINE = re.compile(r"\b(termin|deadline|do\s+dnia|submit\s+by)\b", re.I)
LEGAL = re.compile(r"\b(ubezpieczen|certyfikat|zaświadczen|oświadczen|licencj|"
                   r"certificate|insurance|licen[cs]e|declaration)\w*", re.I)
COMMERCIAL = re.compile(r"\b(cen[ayę]|wycen|ofert[ayę]|price|pricing|budget|"
                        r"commercial|płatnoś)\w*", re.I)


@dataclass(frozen=True)
class Candidate:
    locator: str
    evidence: str
    category: str


def category(text: str) -> str:
    if DEADLINE.search(text):
        return "deadline"
    if LEGAL.search(text):
        return "legal"
    if COMMERCIAL.search(text):
        return "commercial"
    return "technical"


MAX_DOCX_MEMBERS = 500
MAX_DOCX_UNCOMPRESSED_BYTES = 40 * 1024 * 1024
MAX_DOCX_MEMBER_BYTES = 16 * 1024 * 1024
MAX_DOCX_RATIO = 200


def validate_docx_container(content: bytes) -> None:
    """Inspect compressed member metadata before WordDocument expands any XML.

    This is only an archive resource bound, not an antivirus scan. Processing
    untrusted DOCX still requires isolated workers and malware scanning.
    """
    try:
        with zipfile.ZipFile(io.BytesIO(content)) as archive:
            members = archive.infolist()
            if not members or len(members) > MAX_DOCX_MEMBERS:
                raise HTTPException(422, "DOCX archive member count exceeds limit")
            names = set()
            total = 0
            for member in members:
                name = member.filename.replace("\\", "/")
                if (not name or name.startswith("/") or
                        any(segment in (".", "..") for segment in name.split("/"))):
                    raise HTTPException(422, "DOCX archive has invalid paths")
                if name in names:
                    raise HTTPException(422, "DOCX archive has duplicate entries")
                names.add(name)
                if member.flag_bits & 1:
                    raise HTTPException(422, "Encrypted DOCX archive is unsupported")
                if member.file_size > MAX_DOCX_MEMBER_BYTES:
                    raise HTTPException(422, "DOCX archive member exceeds size limit")
                total += member.file_size
                if total > MAX_DOCX_UNCOMPRESSED_BYTES:
                    raise HTTPException(422, "DOCX uncompressed size exceeds limit")
                if (member.file_size > 1024 * 1024 and
                        member.file_size > max(1, member.compress_size) * MAX_DOCX_RATIO):
                    raise HTTPException(422, "DOCX archive compression ratio exceeds limit")
            if not {"[Content_Types].xml", "word/document.xml"}.issubset(names):
                raise HTTPException(422, "DOCX is missing required document parts")
    except HTTPException:
        raise
    except (zipfile.BadZipFile, ValueError, OSError) as exc:
        raise HTTPException(422, "Invalid DOCX archive") from exc


def candidate_segments(filename: str, content: bytes) -> list[Candidate]:
    if len(content) > MAX_DOCUMENT_BYTES or not content:
        raise HTTPException(413, "Document must be 1 byte to 8 MiB")
    suffix = filename.lower().rsplit(".", 1)[-1] if "." in filename else ""
    segments: list[tuple[str, str]] = []
    if suffix == "pdf":
        try:
            with fitz.open(stream=content, filetype="pdf") as document:
                if document.page_count > 80:
                    raise HTTPException(422, "Maximum 80 PDF pages per upload")
                for index, page in enumerate(document):
                    text = page.get_text("text", sort=True)
                    for line in text.splitlines():
                        segments.append((f"page {index + 1}", line))
        except HTTPException:
            raise
        except Exception as exc:
            raise HTTPException(422, "Cannot read PDF (scans need OCR)") from exc
    elif suffix == "docx":
        validate_docx_container(content)
        try:
            doc = WordDocument(io.BytesIO(content))
            if len(doc.paragraphs) > 3000 or len(doc.tables) > 100:
                raise HTTPException(422, "DOCX is too long")
            segments.extend(
                (f"paragraph {i + 1}", p.text) for i, p in enumerate(doc.paragraphs)
            )
            for table_index, table in enumerate(doc.tables):
                if len(table.rows) > 300:
                    raise HTTPException(422, "DOCX table too large")
                for row_index, row in enumerate(table.rows):
                    cells = [cell.text for cell in row.cells[:12]]
                    segments.append((f"table {table_index+1}, row {row_index+1}", " | ".join(cells)))
        except HTTPException:
            raise
        except Exception as exc:
            raise HTTPException(422, "Cannot read DOCX") from exc
    else:
        raise HTTPException(415, "Only text PDFs and DOCX documents are supported")
    result: list[Candidate] = []
    char_count = 0
    for locator, raw in segments:
        char_count += len(raw)
        if char_count > MAX_EXTRACTED_CHARS:
            raise HTTPException(422, "Document text exceeds supported limit")
        normalized = " ".join(raw.split())
        if len(normalized) >= 12 and KEYWORDS.search(normalized):
            result.append(Candidate(locator, normalized[:700], category(normalized)))
            if len(result) >= MAX_REQUIREMENTS:
                break
    return result
