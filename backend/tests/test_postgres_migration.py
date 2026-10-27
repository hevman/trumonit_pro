"""Integration checks against the real versioned PostgreSQL schema."""
import os

import pytest
from fastapi.testclient import TestClient
from sqlalchemy import create_engine, text
from sqlalchemy.orm import sessionmaker

from trumonit.db import get_db
from trumonit.main import app
from .conftest import authorized, register
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
        assert installed == "0002_bid_response_library"
        assert conn.execute(text(
            "SELECT COUNT(*) FROM information_schema.tables "
            "WHERE table_schema='public' AND table_name IN "
            "('users', 'workspaces', 'answer_library', 'requirement_responses')"
        )).scalar_one() == 4

    app.dependency_overrides[get_db] = real_db
    try:
        with TestClient(app) as http:
            gate = {"X-Trumonit-Gateway": "integration-only-gateway-secret-value-0123456789"}
            a = authorized(gate, register(http, gate, "pgsql-a@example.test", "PgA")["session_token"])
            b = authorized(gate, register(http, gate, "pgsql-b@example.test", "PgB")["session_token"])

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
    finally:
        app.dependency_overrides.clear()
        db_engine.dispose()
