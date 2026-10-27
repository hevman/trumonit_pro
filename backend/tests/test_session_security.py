"""Regression tests for device-session ownership, revocation and brute-force limits."""
from .conftest import authorized, register


def second_login(client, gateway, email):
    login = client.post("/api/auth/login", headers=gateway, json={
        "email": email, "password": "VeryStrongPassword2026!",
    })
    assert login.status_code == 200, login.text
    return authorized(gateway, login.json()["session_token"])


def test_session_inventory_and_remote_revocation(client, gateway):
    profile = register(client, gateway, "sessions-a@example.test", "Sessions A")
    current = authorized(gateway, profile["session_token"])
    remote = second_login(client, gateway, "sessions-a@example.test")
    another = authorized(gateway, register(
        client, gateway, "sessions-b@example.test", "Sessions B",
    )["session_token"])

    listed = client.get("/api/auth/sessions", headers=current)
    assert listed.status_code == 200, listed.text
    sessions = listed.json()
    assert len(sessions) == 2
    assert sum(1 for row in sessions if row["current"]) == 1
    remote_id = next(row["id"] for row in sessions if not row["current"])
    active_id = next(row["id"] for row in sessions if row["current"])
    assert len(remote_id) == 36
    assert all("token" not in row for row in sessions)
    assert all(row["workspace_name"] == "Sessions A" for row in sessions)
    assert all(row["created_at"] and row["expires_at"] for row in sessions)
    assert len(client.get("/api/auth/sessions", headers=another).json()) == 1

    # A different user cannot revoke someone else's session.
    assert client.post(f"/api/auth/sessions/{remote_id}/revoke", headers=another,
                       json={"password": "VeryStrongPassword2026!"}).status_code == 404
    assert client.post(f"/api/auth/sessions/{active_id}/revoke", headers=current,
                       json={"password": "VeryStrongPassword2026!"}).status_code == 409

    incorrect = client.post(f"/api/auth/sessions/{remote_id}/revoke",
                            headers=current, json={"password": "incorrect"})
    assert incorrect.status_code == 403
    assert client.get("/api/auth/me", headers=remote).status_code == 200

    terminated = client.post(f"/api/auth/sessions/{remote_id}/revoke",
                             headers=current, json={"password": "VeryStrongPassword2026!"})
    assert terminated.status_code == 200, terminated.text
    assert terminated.json()["revoked"] is True
    assert client.get("/api/auth/me", headers=remote).status_code == 401
    assert client.get("/api/auth/me", headers=current).status_code == 200
    assert client.get("/api/auth/sessions", headers=current).json()[0]["current"] is True
    assert any(ev["action"] == "account.session_revoked"
               for ev in client.get("/api/audit", headers=current).json())


def test_revoke_others_preserves_current_and_covers_all_workspaces(client, gateway):
    registered = register(client, gateway, "session-owner@example.test", "Acme")
    current = authorized(gateway, registered["session_token"])
    remote_a = second_login(client, gateway, "session-owner@example.test")
    remote_b = second_login(client, gateway, "session-owner@example.test")
    another_company = authorized(gateway, register(
        client, gateway, "unrelated@example.test", "Other Company",
    )["session_token"])

    before = client.get("/api/auth/sessions", headers=current).json()
    assert len(before) == 3
    assert client.post("/api/auth/sessions/revoke-others", headers=current,
                       json={"password": "invalid"}).status_code == 403
    all_others = client.post("/api/auth/sessions/revoke-others", headers=current,
                             json={"password": "VeryStrongPassword2026!"})
    assert all_others.status_code == 200, all_others.text
    assert all_others.json() == {"revoked": 2, "current_session_active": True}
    assert client.get("/api/auth/me", headers=remote_a).status_code == 401
    assert client.get("/api/auth/me", headers=remote_b).status_code == 401
    assert client.get("/api/auth/me", headers=another_company).status_code == 200
    assert client.get("/api/auth/me", headers=current).status_code == 200
    assert len(client.get("/api/auth/sessions", headers=current).json()) == 1
    assert any(ev["action"] == "account.other_sessions_revoked"
               for ev in client.get("/api/audit", headers=current).json())


def test_session_revocation_password_rate_limit(client, gateway):
    first = register(client, gateway, "sessions-throttle@example.test", "Throttle")
    current = authorized(gateway, first["session_token"])
    remote = second_login(client, gateway, "sessions-throttle@example.test")
    for index in range(5):
        result = client.post("/api/auth/sessions/revoke-others",
                             headers=current, json={"password": "wrong-password"})
        assert result.status_code == 403, (index, result.text)
    blocked = client.post("/api/auth/sessions/revoke-others",
                          headers=current, json={"password": "VeryStrongPassword2026!"})
    assert blocked.status_code == 429
    assert client.get("/api/auth/me", headers=remote).status_code == 200
