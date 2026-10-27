"""Integration checks against the real versioned PostgreSQL schema."""
import os

import pytest
from fastapi.testclient import TestClient
from sqlalchemy import create_engine, text
from sqlalchemy.orm import sessionmaker

from trumonit.db import get_db
from trumonit.main import app
from .conftest import authorized, register, verify_mail
from .test_workflow import docx_bytes


def test_new_installation_migrations_and_tenant_boundary(monkeypatch):
    url = os.getenv("TEST_DATABASE_URL")
    if not url:
        pytest.skip("PostgreSQL integration requires TEST_DATABASE_URL")
    monkeypatch.setenv("TRUMONIT_GATEWAY_TOKEN", "integration-only-gateway-secret-value-0123456789")
    db_engine = create_engine(url, pool_pre_ping=True)
    factory = sessionmaker(bind=db_engine, expire_on_commit=False)

    def real_db():
        with factory() as db:
            yield db

    with db_engine.connect() as conn:
        installed = conn.execute(text("SELECT version_num FROM alembic_version")).scalar_one()
        assert installed == "0006_spreadsheet_questions"
        assert conn.execute(text(
            "SELECT COUNT(*) FROM information_schema.tables "
            "WHERE table_schema='public' AND table_name IN "
            "('users', 'workspaces', 'answer_library', 'requirement_responses', 'team_invitations', 'requirement_tasks', 'account_action_tokens', 'account_mail_throttles')"
        )).scalar_one() == 8
        assert conn.execute(text(
            "SELECT COUNT(*) FROM information_schema.columns WHERE "
            "table_name='sessions' AND column_name IN ('id', 'created_at')"
        )).scalar_one() == 2

    with db_engine.connect() as conn:
        assert conn.execute(text(
            "SELECT COUNT(*) FROM information_schema.tables WHERE "
            "table_schema='public' AND table_name='spreadsheet_questions'"
        )).scalar_one() == 1

    app.dependency_overrides[get_db] = real_db
    try:
        with TestClient(app) as http:
            gate = {"X-Trumonit-Gateway": "integration-only-gateway-secret-value-0123456789"}
            a = authorized(gate, register(http, gate, "pgsql-a@example.test", "PgA")["session_token"])
            b = authorized(gate, register(http, gate, "pgsql-b@example.test", "PgB")["session_token"])

            session_inventory = http.get("/api/auth/sessions", headers=a)
            assert session_inventory.status_code == 200, session_inventory.text
            assert len(session_inventory.json()) == 1
            assert session_inventory.json()[0]["current"] is True
            assert len(session_inventory.json()[0]["id"]) == 36
            project = http.post("/api/projects", headers=a, json={"name": "Pg tender"}).json()
            project_id = project["id"]
            imported = http.post(
                "/api/library/import", headers=a,
                files={"file": ("company.csv", b"title,body\n"
                                b"Insurance scope,Our company holds active insurance documentation.\n")},
            )
            assert imported.status_code == 201, imported.text
            assert imported.json()["imported"] == 1
            assert http.get("/api/library", headers=b).json() == []
            assert http.get(f"/api/projects/{project_id}", headers=b).status_code == 404
            doc = http.post(
                f"/api/projects/{project_id}/documents", headers=a,
                files={"file": ("tender.docx", docx_bytes())},
            )
            assert doc.status_code == 201, doc.text
            req = http.get(f"/api/projects/{project_id}", headers=a).json()["requirements"][0]
            assert http.patch(
                f"/api/projects/{project_id}/requirements/{req['id']}/response",
                headers=b, json={"body": "A response from another organization's project."},
            ).status_code == 404
            draft = http.patch(
                f"/api/projects/{project_id}/requirements/{req['id']}/response",
                headers=a, json={"body": "The requested policy documentation is available."},
            )
            assert draft.status_code == 200, draft.text
            assert http.get(f"/api/projects/{project_id}/responses", headers=b).status_code == 404
            assert not any(event["action"] == "library.csv_imported"
                           for event in http.get("/api/audit", headers=b).json())
            assert any(event["action"] == "library.csv_imported"
                       for event in http.get("/api/audit", headers=a).json())

            invitation = http.post("/api/team/invitations", headers=a, json={
                "email": "pgsql-editor@example.test", "role": "editor",
            })
            assert invitation.status_code == 201, invitation.text
            claim = http.post("/api/team/claim", headers=gate, json={
                "token": invitation.json()["token"], "name": "PG Editor",
                "password": "SafePasswordForDbTest2026!",
            })
            assert claim.status_code == 201, claim.text
            confirmed = http.post("/api/auth/verify", headers=gate,
                json={"token": verify_mail(http, gate, "pgsql-editor@example.test")})
            assert confirmed.status_code == 200, confirmed.text
            editor = authorized(gate, confirmed.json()["session_token"])
            assignment = http.patch(
                f"/api/projects/{project_id}/tasks/{req['id']}",
                headers=editor, json={
                    "assignee_id": confirmed.json()["account"]["user_id"],
                    "due_date": "2030-06-01", "status": "in_progress",
                },
            )
            assert assignment.status_code == 200, assignment.text
            assert len(http.get(f"/api/projects/{project_id}/tasks", headers=a).json()) == 1
            assert http.get(f"/api/projects/{project_id}/tasks", headers=b).status_code == 404
            member_id = next(member["id"]
                for member in http.get("/api/team", headers=a).json()["members"]
                if member["email"] == "pgsql-editor@example.test")
            changed = http.patch(f"/api/team/members/{member_id}", headers=a,
                                 json={"role": "viewer"})
            assert changed.status_code == 200, changed.text
            assert http.patch(
                f"/api/projects/{project_id}/tasks/{req['id']}",
                headers=editor, json={"status": "done"},
            ).status_code == 403
    finally:
        app.dependency_overrides.clear()
        db_engine.dispose()
