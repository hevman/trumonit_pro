"""Explicit deletion and data isolation; no silent expiry of customer data."""
from .conftest import authorized, register
from .test_workflow import docx_bytes


def test_owner_can_preview_and_delete_project_without_touching_other_tenants(client, gateway):
    a = authorized(gateway, register(client, gateway, "delete-a@example.test", "Delete A")["session_token"])
    b = authorized(gateway, register(client, gateway, "delete-b@example.test", "Delete B")["session_token"])
    p = client.post("/api/projects", headers=a, json={"name": "Confidential tender"}).json()["id"]
    other = client.post("/api/projects", headers=b, json={"name": "Other tender"}).json()["id"]
    uploaded = client.post(f"/api/projects/{p}/documents", headers=a,
                           files={"file": ("source.docx", docx_bytes())})
    assert uploaded.status_code == 201, uploaded.text
    requirement = client.get(f"/api/projects/{p}", headers=a).json()["requirements"][0]
    draft = client.patch(f"/api/projects/{p}/requirements/{requirement['id']}/response",
                         headers=a, json={"body": "Our own company response."})
    assert draft.status_code == 200, draft.text
    task = client.patch(f"/api/projects/{p}/tasks/{requirement['id']}",
                        headers=a, json={"status": "in_progress"})
    assert task.status_code == 200, task.text
    preview = client.get(f"/api/projects/{p}/deletion-preview", headers=a)
    assert preview.status_code == 200
    assert preview.json()["documents"] == 1
    assert preview.json()["requirements"] >= 1
    assert preview.json()["responses"] == 1
    assert preview.json()["tasks"] == 1
    assert preview.json()["document_bytes"] > 0
    assert client.get(f"/api/projects/{p}/deletion-preview", headers=b).status_code == 404
    assert client.post(f"/api/projects/{p}/delete", headers=b,
                       json={"confirm_project_name": "Confidential tender"}).status_code == 404
    assert client.post(f"/api/projects/{p}/delete", headers=a,
                       json={"confirm_project_name": "wrong"}).status_code == 409
    assert client.get(f"/api/projects/{p}", headers=a).status_code == 200
    deleted = client.post(f"/api/projects/{p}/delete", headers=a,
                          json={"confirm_project_name": "Confidential tender"})
    assert deleted.status_code == 200, deleted.text
    assert deleted.json()["removed"]["documents"] == 1
    assert client.get(f"/api/projects/{p}", headers=a).status_code == 404
    assert client.get(f"/api/projects/{other}", headers=b).status_code == 200
    assert any(event["action"] == "project.deleted" and event["resource_id"] == p
               for event in client.get("/api/audit", headers=a).json())


def test_delete_does_not_remove_shared_answer_library(client, gateway):
    owner = authorized(gateway, register(client, gateway)["session_token"])
    project = client.post("/api/projects", headers=owner,
                          json={"name": "Delete only project"}).json()["id"]
    item = client.post("/api/library", headers=owner,
                       json={"title": "Approved insurance", "body": "Company-owned insurance wording."})
    assert item.status_code == 201, item.text
    assert client.post(f"/api/projects/{project}/delete", headers=owner,
                       json={"confirm_project_name": "Delete only project"}).status_code == 200
    library = client.get("/api/library", headers=owner).json()
    assert len(library) == 1
