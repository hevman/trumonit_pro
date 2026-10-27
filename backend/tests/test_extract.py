import io

import fitz
import pytest
from docx import Document as WordDocument
from fastapi import HTTPException

from trumonit.extract import candidate_segments


def test_pdf_evidence_preserves_page_number_and_does_not_make_claims():
    doc = fitz.open()
    page = doc.new_page()
    page.insert_text((65, 85), "The applicant must supply a current insurance certificate.")
    page.insert_text((65, 115), "Reference material is provided for information.")
    output = doc.tobytes()
    doc.close()
    candidates = candidate_segments("rfp.pdf", output)
    assert len(candidates) == 1
    assert candidates[0].locator == "page 1"
    assert candidates[0].category == "legal"
    assert "must supply" in candidates[0].evidence


def test_docx_and_unsupported_input():
    doc = WordDocument()
    doc.add_paragraph("Wykonawca musi przedstawić ważne zaświadczenie.")
    doc.add_paragraph("This line is general context.")
    buf = io.BytesIO()
    doc.save(buf)
    found = candidate_segments("specification.docx", buf.getvalue())
    assert len(found) == 1
    assert found[0].locator.startswith("paragraph")
    assert found[0].category == "legal"
    with pytest.raises(HTTPException) as exc:
        candidate_segments("payload.exe", b"not-a-document")
    assert exc.value.status_code == 415
