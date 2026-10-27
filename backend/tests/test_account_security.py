"""Email ownership, recovery-token lifecycle and session invalidation."""
from datetime import timedelta
from pathlib import Path

from sqlalchemy import select

from trumonit.db import get_db, now
from trumonit.models import AccountActionToken, SessionToken, User

from .conftest import authorized, register, verify_mail
from .test_team_tasks import invite


def test_unverified_registration_requires_mail_and_verification(client, gateway, monkeypatch):
    monkeypatch.setenv("TRUMONIT_MAIL_MODE", "disabled")
    blocked = client.post("/api/auth/register", headers=gateway, json={
        "name": "No Email", "email": "no-mail@example.test",
        "password": "VeryStrongPassword2026!", "workspace_name": "No Mail",
    })
    assert blocked.status_code == 503

    monkeypatch.setenv("TRUMONIT_MAIL_MODE", "file")
    created = client.post("/api/auth/register", headers=gateway, json={
        "name": "New owner", "email": "verify@example.test",
        "password": "VeryStrongPassword2026!", "workspace_name": "Verify Org",
    })
    assert created.status_code == 201, created.text
    assert created.json() == {
        "verification_required": True, "email": "verify@example.test"
    }
    assert client.post("/api/auth/login", headers=gateway, json={
        "email": "verify@example.test", "password": "VeryStrongPassword2026!",
    }).status_code == 403
    raw = verify_mail(client, gateway, "verify@example.test")
    assert client.post("/api/auth/password/reset", headers=gateway,
                       json={"token": raw, "password": "OtherVeryStrongPassword2026!"}).status_code == 400
    verified = client.post("/api/auth/verify", headers=gateway, json={"token": raw})
    assert verified.status_code == 200, verified.text
    auth = authorized(gateway, verified.json()["session_token"])
    assert client.get("/api/auth/me", headers=auth).status_code == 200
    assert client.post("/api/auth/verify", headers=gateway, json={"token": raw}).status_code == 400


def test_password_reset_revokes_all_sessions_and_rejects_reuse(client, gateway):
    signup = register(client, gateway, "reset@example.test", "Reset Corp")
    first = authorized(gateway, signup["session_token"])
    logged = client.post("/api/auth/login", headers=gateway, json={
        "email": "reset@example.test", "password": "VeryStrongPassword2026!",
    })
    assert logged.status_code == 200
    second = authorized(gateway, logged.json()["session_token"])
    unknown = client.post("/api/auth/password/forgot", headers=gateway, json={
        "email": "absent@example.test",
    })
    known = client.post("/api/auth/password/forgot", headers=gateway, json={
        "email": "reset@example.test",
    })
    assert unknown.status_code == known.status_code == 202
    assert unknown.json() == known.json()
    raw = verify_mail(client, gateway, "reset@example.test", "reset")
    wrong = client.post("/api/auth/verify", headers=gateway, json={"token": raw})
    assert wrong.status_code == 400
    too_short = client.post("/api/auth/password/reset", headers=gateway, json={
        "token": raw, "password": "short",
    })
    assert too_short.status_code == 422
    success = client.post("/api/auth/password/reset", headers=gateway, json={
        "token": raw, "password": "RenewedStrongPassword2026!",
    })
    assert success.status_code == 200, success.text
    assert success.json()["sessions_revoked"] is True
    assert client.get("/api/auth/me", headers=first).status_code == 401
    assert client.get("/api/auth/me", headers=second).status_code == 401
    assert client.post("/api/auth/password/reset", headers=gateway, json={
        "token": raw, "password": "AgainStrongPassword2026!",
    }).status_code == 400
    assert client.post("/api/auth/login", headers=gateway, json={
        "email": "reset@example.test", "password": "VeryStrongPassword2026!",
    }).status_code == 401
    login = client.post("/api/auth/login", headers=gateway, json={
        "email": "reset@example.test", "password": "RenewedStrongPassword2026!",
    })
    assert login.status_code == 200, login.text


def test_mail_throttle_and_invalid_or_expired_tokens(client, gateway, monkeypatch):
    register(client, gateway, "throttle@example.test", "Throttle Corp")
    for _ in range(6):
        result = client.post("/api/auth/password/forgot", headers=gateway,
                             json={"email": "throttle@example.test"})
        assert result.status_code == 202
    folder = Path(__import__("os").environ["TRUMONIT_MAIL_OUTBOX"])
    # One verification message plus no more than three reset messages per hour.
    assert len(list(folder.glob("*.eml"))) == 4
    raw = verify_mail(client, gateway, "throttle@example.test", "reset")
    override = client.app.dependency_overrides[get_db]
    session = next(override())
    try:
        record = session.scalar(select(AccountActionToken).where(
            AccountActionToken.purpose == "reset",
            AccountActionToken.digest == __import__("hashlib").sha256(raw.encode()).hexdigest(),
        ))
        record.expires_at = now() - timedelta(seconds=5)
        session.commit()
    finally:
        session.close()
    expired = client.post("/api/auth/password/reset", headers=gateway,
                          json={"token": raw, "password": "ChangedStrongPassword2026!"})
    assert expired.status_code == 400


def test_invited_account_needs_mail_verification(client, gateway):
    owner = authorized(gateway, register(client, gateway)["session_token"])
    invitation = invite(client, owner, "invited@example.test", role="viewer")
    claim = client.post("/api/team/claim", headers=gateway, json={
        "token": invitation["token"], "name": "Invited",
        "password": "StrongInvitePassword2026!",
    })
    assert claim.status_code == 201
    assert "session_token" not in claim.json()
    assert client.post("/api/auth/login", headers=gateway, json={
        "email": "invited@example.test", "password": "StrongInvitePassword2026!",
    }).status_code == 403
    verify = client.post("/api/auth/verify", headers=gateway, json={
        "token": verify_mail(client, gateway, "invited@example.test"),
    })
    assert verify.status_code == 200
    assert verify.json()["account"]["role"] == "viewer"
    auth = authorized(gateway, verify.json()["session_token"])
    assert client.get("/api/auth/me", headers=auth).status_code == 200
