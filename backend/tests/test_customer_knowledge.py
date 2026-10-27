"""Customer-owned CSV library import and human approval control tests."""
import io

from .conftest import authorized, register
from .test_manual_responses import setup_bid


def test_import_company_csv_as_drafts_and_reject_duplicates(client, gateway):
    a = register(client, gateway, "procurement@example.test", "Procurement Co.")
    h = authorized(gateway, a["session_token"])
    csv_text = (
        "title,category,body,evidence_reference,valid_until\n"
        "Insurance statement,legal,We hold valid company insurance documentation.,Policy reference 2026,\n"
        "Quality process,technical,We have a documented quality assurance process.,Policy QA-22,\n"
        "Insurance statement,legal,Duplicate entries must be ignored.,Policy,\n"
    )
    imported = client.post("/api/library/import", headers=h,
                           files={"file": ("answers.csv", csv_text.encode("utf-8"), "text/csv")})
    assert imported.status_code == 201, imported.text
    assert imported.json()["imported"] == 2
    assert imported.json()["skipped_duplicates"] == 1
    entries = client.get("/api/library", headers=h).json()
    assert len(entries) == 2
    assert all(item["status"] == "draft" and not item["usable"] for item in entries)
    assert entries[0]["evidence_reference"] or entries[1]["evidence_reference"]

    duplicate = client.post("/api/library/import", headers=h,
                            files={"file": ("answers.csv", csv_text.encode(), "text/csv")})
    assert duplicate.status_code == 201
    assert duplicate.json()["imported"] == 0
    assert duplicate.json()["skipped_duplicates"] == 3

    template = client.get("/api/library/template", headers=h)
    assert template.status_code == 200
    assert "title,category,body" in template.text
    assert template.headers["x-content-type-options"] == "nosniff"


def test_import_is_atomic_and_rejects_bad_rows(client, gateway):
    registered = register(client, gateway)
    h = authorized(gateway, registered["session_token"])
    corrupted = (
        "title,body\n"
        "Good entry,A genuine supplier policy statement exists here.\n"
        "Bad entry,short\n"
    ).encode()
    result = client.post("/api/library/import", headers=h,
                         files={"file": ("bad.csv", corrupted)})
    assert result.status_code == 422
    assert client.get("/api/library", headers=h).json() == []

    invalid = client.post("/api/library/import", headers=h,
                          files={"file": ("bad.csv", b"title,body\nExtra,Not a very good answer" + b"\xff")})
    assert invalid.status_code == 422
    assert client.get("/api/library", headers=h).json() == []
    assert client.post("/api/library/import", headers=h,
                       files={"file": ("not.csv.exe", corrupted)}).status_code == 415


def test_imported_data_never_crosses_organizations_and_audit_is_scoped(client, gateway):
    a, project_a, requirement_a = setup_bid(client, gateway, "alpha@example.test", "Alpha")
    b, project_b, requirement_b = setup_bid(client, gateway, "beta@example.test", "Beta")
    content = b"title,body\nFirm process,This belongs only to the original company.\n"
    assert client.post("/api/library/import", headers=a,
                       files={"file": ("alpha.csv", content)}).status_code == 201
    alpha_entries = client.get("/api/library", headers=a).json()
    beta_entries = client.get("/api/library", headers=b).json()
    assert len(alpha_entries) == 1
    assert beta_entries == []
    alpha_audit = client.get("/api/audit", headers=a)
    beta_audit = client.get("/api/audit", headers=b)
    assert alpha_audit.status_code == 200
    assert beta_audit.status_code == 200
    assert any(e["action"] == "library.csv_imported" for e in alpha_audit.json())
    assert not any(e["action"] == "library.csv_imported" for e in beta_audit.json())
    assert all(e["actor_email"] == "alpha@example.test" for e in alpha_audit.json())
    assert all(e["actor_email"] == "beta@example.test" for e in beta_audit.json())


def test_demoting_requirement_revokes_previous_response_approval(client, gateway):
    headers, project, requirement = setup_bid(
        client, gateway, "controller@example.test", "Quality Control",
    )
    prepared = client.patch(
        f"/api/projects/{project}/requirements/{requirement}/response",
        headers=headers, json={"body": "We confirm fulfillment with current source evidence."},
    )
    assert prepared.status_code == 200, prepared.text
    assert client.patch(
        f"/api/projects/{project}/requirements/{requirement}",
        headers=headers, json={"status": "met", "note": "Source checked"},
    ).status_code == 200
    approved = client.post(
        f"/api/projects/{project}/requirements/{requirement}/response/approve",
        headers=headers,
    )
    assert approved.status_code == 200, approved.text
    assert client.get(f"/api/projects/{project}/response-pack", headers=headers).status_code == 200
    demoted = client.patch(
        f"/api/projects/{project}/requirements/{requirement}",
        headers=headers, json={"status": "gap", "note": "Certificate expired"},
    )
    assert demoted.status_code == 200
    rows = client.get(f"/api/projects/{project}/responses", headers=headers).json()
    assert len(rows) == 1
    assert rows[0]["status"] == "draft"
    assert rows[0]["approved_by"] is None
    assert client.get(f"/api/projects/{project}/response-pack", headers=headers).status_code == 409
    assert any(e["action"] == "response.approval_revoked"
               for e in client.get("/api/audit", headers=headers).json())
    assert client.patch(
        f"/api/projects/{project}/requirements/{requirement}",
        headers=headers, json={"status": "met", "note": "Rechecked"},
    ).status_code == 200
    assert client.get(f"/api/projects/{project}/response-pack", headers=headers).status_code == 409
