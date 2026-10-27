import io
from datetime import date, timedelta

from docx import Document as WordDocument

from .conftest import authorized, register
from .test_workflow import docx_bytes


def setup_bid(client, gateway, email, workspace):
    credentials = register(client, gateway, email, workspace)
    headers = authorized(gateway, credentials["session_token"])
    project = client.post("/api/projects", headers=headers,
                          json={"name": workspace + " tender"}).json()
    document = client.post(
        f"/api/projects/{project['id']}/documents",
        headers=headers, files={"file": ("requirements.docx", docx_bytes())},
    )
    assert document.status_code == 201, document.text
    detail = client.get(f"/api/projects/{project['id']}", headers=headers).json()
    return headers, project["id"], detail["requirements"][0]["id"]


def test_reusable_library_needs_explicit_approval_and_docx_export(client, gateway):
    headers, project, req_id = setup_bid(client, gateway, "bid@example.test", "Bid team")

    draft = client.post("/api/library", headers=headers, json={
        "title": "Insurance confirmation",
        "category": "legal",
        "body": "We hold professional indemnity insurance and can supply the certificate.",
        "evidence_reference": "Company insurance file 2026",
        "valid_until": (date.today() + timedelta(days=30)).isoformat(),
    })
    assert draft.status_code == 201, draft.text
    entry = draft.json()
    assert entry["status"] == "draft"
    assert entry["usable"] is False

    blocked = client.patch(
        f"/api/projects/{project}/requirements/{req_id}/response",
        headers=headers, json={"library_entry_id": entry["id"]},
    )
    assert blocked.status_code == 409

    approved = client.post(f"/api/library/{entry['id']}/approve", headers=headers)
    assert approved.status_code == 200, approved.text
    assert approved.json()["usable"] is True

    copied = client.patch(
        f"/api/projects/{project}/requirements/{req_id}/response",
        headers=headers, json={"library_entry_id": entry["id"]},
    )
    assert copied.status_code == 200, copied.text
    assert copied.json()["status"] == "draft"
    assert copied.json()["body"] == entry["body"]

    assert client.get(f"/api/projects/{project}/response-pack", headers=headers).status_code == 409
    assert client.post(
        f"/api/projects/{project}/requirements/{req_id}/response/approve",
        headers=headers,
    ).status_code == 409  # Requirement itself has not been marked Met yet.

    reviewed = client.patch(
        f"/api/projects/{project}/requirements/{req_id}", headers=headers,
        json={"status": "met", "note": "Insurance document checked."},
    )
    assert reviewed.status_code == 200

    accepted = client.post(
        f"/api/projects/{project}/requirements/{req_id}/response/approve",
        headers=headers,
    )
    assert accepted.status_code == 200, accepted.text
    assert accepted.json()["status"] == "approved"

    export = client.get(f"/api/projects/{project}/response-pack", headers=headers)
    assert export.status_code == 200, export.text
    assert export.headers["content-type"].startswith(
        "application/vnd.openxmlformats-officedocument.wordprocessingml.document"
    )
    word = WordDocument(io.BytesIO(export.content))
    paragraphs = "\n".join(p.text for p in word.paragraphs)
    assert "INTERNAL REVIEW COPY" in paragraphs
    assert entry["body"] in paragraphs
    assert "Approved items: 1" in paragraphs

    # Any changed wording must be approved again and removed from export.
    edited = client.patch(
        f"/api/projects/{project}/requirements/{req_id}/response",
        headers=headers,
        json={"body": "The insurance certificate is attached, subject to verification."},
    )
    assert edited.status_code == 200
    assert edited.json()["status"] == "draft"
    assert client.get(f"/api/projects/{project}/response-pack", headers=headers).status_code == 409

    # Editing a shared library entry also resets its separate library approval.
    changed_template = client.patch(f"/api/library/{entry['id']}", headers=headers, json={
        "title": "Insurance confirmation updated",
        "body": "We can provide our current professional insurance documentation.",
    })
    assert changed_template.status_code == 200
    assert changed_template.json()["status"] == "draft"
    assert changed_template.json()["usable"] is False
    assert len(client.get("/api/library", headers=headers).json()) == 1


def test_library_and_bid_responses_never_cross_company_boundaries(client, gateway):
    first, project_a, req_a = setup_bid(client, gateway, "first@example.test", "Alpha")
    second, project_b, req_b = setup_bid(client, gateway, "second@example.test", "Beta")
    item = client.post("/api/library", headers=first, json={
        "title": "Approved process",
        "body": "Our quality management process is checked and documented.",
    }).json()
    assert client.post(f"/api/library/{item['id']}/approve", headers=first).status_code == 200
    assert client.get("/api/library", headers=second).json() == []
    assert client.post(f"/api/library/{item['id']}/approve", headers=second).status_code == 404
    assert client.patch(f"/api/library/{item['id']}", headers=second,
                        json={"title": "Unauthorized", "body": "A valid but stolen example body."}
                        ).status_code == 404
    assert client.patch(
        f"/api/projects/{project_b}/requirements/{req_b}/response",
        headers=second, json={"library_entry_id": item["id"]},
    ).status_code == 404
    assert client.patch(
        f"/api/projects/{project_a}/requirements/{req_a}/response",
        headers=second, json={"body": "We can fulfill the requested requirements."},
    ).status_code == 404
    assert client.get(f"/api/projects/{project_a}/responses", headers=second).status_code == 404
    assert client.get(f"/api/projects/{project_a}/response-pack", headers=second).status_code == 404
    assert client.get(f"/api/projects/{project_b}/responses", headers=first).status_code == 404


def test_expired_template_cannot_be_approved(client, gateway):
    user = register(client, gateway)
    headers = authorized(gateway, user["session_token"])
    expired = client.post("/api/library", headers=headers, json={
        "title": "Outdated policy",
        "body": "This certificate expired and cannot be safely reused.",
        "valid_until": "2020-01-01",
    }).json()
    assert client.post(f"/api/library/{expired['id']}/approve",
                       headers=headers).status_code == 409
    assert client.get("/api/library", headers=headers).json()[0]["usable"] is False
