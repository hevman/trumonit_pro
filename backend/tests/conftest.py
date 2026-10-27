import os
import pytest
from fastapi.testclient import TestClient
from sqlalchemy import create_engine
from sqlalchemy.orm import sessionmaker
from sqlalchemy.pool import StaticPool

from trumonit.db import Base, get_db
from trumonit.main import app

GATEWAY = "integration-only-gateway-secret-value-0123456789"


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
    return response.json()


def authorized(gateway, token):
    return {**gateway, "X-Trumonit-Session": token}
