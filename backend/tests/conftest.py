import os
import re
from email.parser import BytesParser
from email import policy
import pytest
from fastapi.testclient import TestClient
from sqlalchemy import create_engine
from sqlalchemy.orm import sessionmaker
from sqlalchemy.pool import StaticPool

from trumonit.db import Base, get_db
from trumonit.main import app

GATEWAY = "integration-only-gateway-secret-value-0123456789"


@pytest.fixture(autouse=True)
def mail_setup(monkeypatch, tmp_path):
    monkeypatch.setenv("TRUMONIT_ENV", "test")
    monkeypatch.setenv("TRUMONIT_MAIL_MODE", "file")
    monkeypatch.setenv("TRUMONIT_MAIL_OUTBOX", str(tmp_path / "mail"))
    monkeypatch.setenv("TRUMONIT_PUBLIC_URL", "http://localhost")


def verify_mail(http, gateway, email, purpose="verify"):
    folder = os.environ["TRUMONIT_MAIL_OUTBOX"]
    messages = []
    for filename in __import__("pathlib").Path(folder).glob("*.eml"):
        msg = BytesParser(policy=policy.default).parsebytes(filename.read_bytes())
        if msg.get("To") == email and (msg.get("Subject", "").startswith("Verify") if purpose == "verify" else msg.get("Subject", "").startswith("Reset")):
            messages.append((filename.stat().st_mtime_ns, msg.get_content()))
    assert messages, f"No {purpose} mail delivered to {email}"
    body = max(messages)[1]
    matches = re.findall(r"#" + purpose + r"=([A-Za-z0-9_-]{43})", body)
    assert len(matches) == 1
    return matches[0]


@pytest.fixture
def client(monkeypatch):
    monkeypatch.setenv("TRUMONIT_GATEWAY_TOKEN", GATEWAY)
    engine = create_engine(
        "sqlite+pysqlite://",
        connect_args={"check_same_thread": False},
        poolclass=StaticPool,
    )
    Base.metadata.create_all(engine)
    factory = sessionmaker(bind=engine, expire_on_commit=False)

    def test_db():
        with factory() as session:
            yield session

    app.dependency_overrides[get_db] = test_db
    try:
        with TestClient(app) as http:
            yield http
    finally:
        app.dependency_overrides.clear()
        engine.dispose()


@pytest.fixture
def gateway():
    return {"X-Trumonit-Gateway": GATEWAY}


def register(http, gateway, email="owner@example.test", company="Example Company"):
    response = http.post("/api/auth/register", headers=gateway, json={
        "name": "Workspace owner", "email": email,
        "password": "VeryStrongPassword2026!",
        "workspace_name": company,
    })
    assert response.status_code == 201, response.text
    assert response.json()["verification_required"] is True
    token = verify_mail(http, gateway, email)
    verified = http.post("/api/auth/verify", headers=gateway, json={"token": token})
    assert verified.status_code == 200, verified.text
    return verified.json()


def authorized(gateway, token):
    return {**gateway, "X-Trumonit-Session": token}
