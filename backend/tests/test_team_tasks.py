"""Invitation lifecycle, owner/editor/viewer permissions and tenant-bound task tracking."""
from datetime import date, timedelta

from .conftest import authorized, register
from .test_workflow import docx_bytes


def invite(http, owner, email, role="editor"):
    result = http.post("/api/team/invitations", headers=owner, json={
        "email": email, "role": role,
    })
    assert result.status_code == 201, result.text
    assert len(result.json()["token"]) == 43
    return result.json()


def project_with_req(http, headers):
    project = http.post("/api/projects", headers=headers, json={
        "name": "Procurement qualification",
    })
    assert project.status_code == 201, project.text
    pid = project.json()["id"]
    uploaded = http.post(f"/api/projects/{pid}/documents", headers=headers,
                         files={"file": ("rfp.docx", docx_bytes())})
    assert uploaded.status_code == 201, uploaded.text
    requirement = http.get(f"/api/projects/{pid}", headers=headers).json()["requirements"][0]
    return pid, requirement["id"]


def test_new_member_invitation_task_and_explicit_role_enforcement(client, gateway):
    owner = authorized(gateway, register(client, gateway)["session_token"])
    pid, requirement_id = project_with_req(client, owner)
    target = invite(client, owner, "editor@example.test")
    assert client.get("/api/team", headers=owner).json()["invitations"][0]["email"] == "editor@example.test"
    assert "token" not in client.get("/api/team", headers=owner).text

    no_permission = client.post("/api/team/claim", headers=gateway, json={
        "token": target["token"] + "0", "name": "Editor",
        "password": "ComplexPasswordFor2026!",
    })
    assert no_permission.status_code in (404, 422)
    accepted = client.post("/api/team/claim", headers=gateway, json={
        "token": target["token"], "name": "Editor name",
        "password": "ComplexPasswordFor2026!",
    })
    assert accepted.status_code == 201, accepted.text
    editor = authorized(gateway, accepted.json()["session_token"])
    editor_id = accepted.json()["account"]["user_id"]
    assert accepted.json()["account"]["role"] == "editor"
    assert client.post("/api/team/claim", headers=gateway, json={
        "token": target["token"], "name": "Another user",
        "password": "ComplexPasswordFor2026!",
    }).status_code == 404

    task = client.patch(f"/api/projects/{pid}/tasks/{requirement_id}", headers=editor,
                        json={"assignee_id": editor_id,
                              "status": "in_progress",
                              "note": "Collect coverage details",
                              "due_date": "2020-01-01"})
    assert task.status_code == 200, task.text
    assert task.json()["overdue"] is True
    assert task.json()["assignee_email"] == "editor@example.test"
    assert client.get(f"/api/projects/{pid}/tasks", headers=owner).json()[0]["status"] == "in_progress"
    assert client.post(f"/api/projects/{pid}/requirements/{requirement_id}/response/approve",
                       headers=editor).status_code == 403
    assert client.post("/api/library", headers=editor, json={
        "title": "Editorial note", "body": "A verified and reused company process description.",
    }).status_code == 201
    assert client.get("/api/audit", headers=editor).status_code == 403

    members = client.get("/api/team", headers=owner).json()["members"]
    membership_id = next(x["id"] for x in members if x["email"] == "editor@example.test")
    changed = client.patch(f"/api/team/members/{membership_id}",
                           headers=owner, json={"role": "viewer"})
    assert changed.status_code == 200, changed.text
    assert client.get("/api/team", headers=editor).status_code == 200
    assert client.get(f"/api/projects/{pid}/tasks", headers=editor).status_code == 200
    assert client.get(f"/api/projects/{pid}", headers=editor).status_code == 200
    assert client.get("/api/library", headers=editor).status_code == 200
    assert client.post("/api/projects", headers=editor,
                       json={"name": "Forbidden tender"}).status_code == 403
    assert client.patch(f"/api/projects/{pid}/tasks/{requirement_id}", headers=editor,
                        json={"status": "done"}).status_code == 403
    assert client.patch(f"/api/projects/{pid}/requirements/{requirement_id}",
                        headers=editor, json={"status": "met"}).status_code == 403
    assert client.post("/api/library", headers=editor, json={
        "title": "Blocked note", "body": "A viewer should never save company content.",
    }).status_code == 403
    assert client.post("/api/team/invitations", headers=editor,
                       json={"email": "outsider@example.test"}).status_code == 403

    removed = client.post(f"/api/team/members/{membership_id}/remove", headers=owner)
    assert removed.status_code == 200
    assert client.get("/api/auth/me", headers=editor).status_code == 401
    assert client.get(f"/api/projects/{pid}/tasks", headers=owner).json()[0]["assignee_id"] is None


def test_workspace_boundaries_existing_account_join_and_switch(client, gateway):
    first_registration = register(client, gateway, "a@example.test", "Company A")
    second_registration = register(client, gateway, "b@example.test", "Company B")
    first = authorized(gateway, first_registration["session_token"])
    second = authorized(gateway, second_registration["session_token"])
    pid, req = project_with_req(client, first)

    # Cross-company user IDs are never valid assignees.
    wrong_id = second_registration["account"]["user_id"]
    assert client.patch(f"/api/projects/{pid}/tasks/{req}", headers=first,
                        json={"assignee_id": wrong_id}).status_code == 422
    assert client.get(f"/api/projects/{pid}/tasks", headers=second).status_code == 404
    assert client.patch(f"/api/projects/{pid}/tasks/{req}", headers=second,
                        json={"status": "done"}).status_code == 404

    invite_data = invite(client, first, "b@example.test", role="viewer")
    cannot_claim_again = client.post("/api/team/claim", headers=gateway, json={
        "token": invite_data["token"], "name": "Existing",
        "password": "ComplexPasswordFor2026!",
    })
    assert cannot_claim_again.status_code == 409
    joined = client.post("/api/team/accept", headers=second,
                         json={"token": invite_data["token"]})
    assert joined.status_code == 200, joined.text
    assert joined.json()["role"] == "viewer"
    assert client.get("/api/auth/me", headers=second).json()["workspace_id"] == first_registration["account"]["workspace_id"]
    workspaces = client.get("/api/team/workspaces", headers=second)
    assert workspaces.status_code == 200
    assert len(workspaces.json()) == 2
    assert client.get(f"/api/projects/{pid}", headers=second).status_code == 200

    switched = client.post("/api/team/switch", headers=second, json={
        "workspace_id": second_registration["account"]["workspace_id"],
    })
    assert switched.status_code == 200
    assert client.get(f"/api/projects/{pid}", headers=second).status_code == 404
    assert client.post("/api/team/switch", headers=second, json={
        "workspace_id": "00000000-0000-0000-0000-000000000000",
    }).status_code == 404


def test_revoked_invite_and_nonowner_access(client, gateway):
    owner = authorized(gateway, register(client, gateway)["session_token"])
    created = invite(client, owner, "new-person@example.test", role="viewer")
    revoked = client.post("/api/team/invitations/"+created["id"]+"/revoke",
                          headers=owner)
    assert revoked.status_code == 200
    assert client.post("/api/team/preview", headers=gateway,
                       json={"token": created["token"]}).status_code == 404
    assert client.post("/api/team/claim", headers=gateway, json={
        "token": created["token"], "name": "Invited user",
        "password": "ComplexPasswordFor2026!",
    }).status_code == 404
