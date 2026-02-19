# API v1

Base path:
- `/api/v1`

Legacy compatibility:
- Unversioned routes (`/api/...`) can stay enabled with `WIDS_API_LEGACY_ENABLED=true`.
- Default current version is controlled by `WIDS_API_CURRENT_VERSION` (`v1`).

Authentication:
- Token auth via Sanctum bearer token.
- Register: `POST /api/v1/auth/register`
- Login: `POST /api/v1/auth/login`
- Current actor: `GET /api/v1/me`
- Logout current token: `POST /api/v1/auth/logout`

Rate limits:
- Public auth endpoints: `WIDS_API_PUBLIC_RPM` per IP (+email key when present).
- Authenticated endpoints use limiter `api-v1`:
  - Tenant budget derived from plan tier (`tenant_rpm` in `wids.plan_tiers`).
  - Effective tenant API budget = `tenant_rpm * WIDS_API_TENANT_RPM_SHARE_PERCENT / 100`.
  - Per-user budget = tenant budget * `WIDS_API_PER_USER_SHARE_PERCENT / 100`.
  - If tenant has unlimited RPM (`0`), fallback to `WIDS_API_UNLIMITED_TENANT_RPM`.
  - Floor for authenticated calls: `WIDS_API_MIN_AUTH_RPM`.

Common response headers for throttling:
- `X-RateLimit-Limit`
- `X-RateLimit-Remaining`
- `Retry-After` (for `429`)

## Endpoints

Monitors:
- `GET /api/v1/monitored-urls`
- `GET /api/v1/monitored-urls/{id}`
- `POST /api/v1/monitored-urls` (owner)
- `PUT/PATCH /api/v1/monitored-urls/{id}` (owner)
- `DELETE /api/v1/monitored-urls/{id}` (owner)
- `POST /api/v1/monitored-urls/{id}/scan` (owner)
- `POST /api/v1/monitored-urls/{id}/baseline` (owner)

Incidents:
- `GET /api/v1/incidents`
- `PATCH /api/v1/incidents/{id}` (owner)
- `POST /api/v1/incidents/{id}/comments`

SIEM:
- `GET /api/v1/siem/incidents` (owner)
- Formats: `json`, `cef`, `leef` via `?format=...`

Private probes:
- `GET /api/v1/private-probes` (owner)
- `POST /api/v1/private-probes` (owner)
- `PATCH /api/v1/private-probes/{id}` (owner)
- `POST /api/v1/private-probes/{id}/rotate-token` (owner)
- `GET /api/v1/private-probes/{id}/tasks` (owner)
- `POST /api/v1/private-probes/{id}/tasks` (owner, queue monitor scan task)
- `POST /api/v1/private-probes/tasks/claim` (probe token, lease one queued task)
- `POST /api/v1/private-probes/tasks/{taskId}/result` (probe token, submit signed result)
- `POST /api/v1/private-probes/heartbeat` (probe token)
- Scheduler auto-dispatch:
  - tag monitor with `private_probe` (configurable via `WIDS_PRIVATE_PROBES_MONITOR_TAG`)
  - scheduler command queues due tagged monitors: `php artisan wids:private-probes-dispatch`

Signed result ingest:
- Header: `X-Wids-Task-Signature`
- Algorithm: `HMAC-SHA256(raw_request_body, lease_token)`
- Optional `sha256=<hex>` prefix is accepted.

## Quick example

```bash
curl -X POST http://localhost:8090/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"TruMonit"}'
```

```bash
curl http://localhost:8090/api/v1/incidents \
  -H "Authorization: Bearer <TOKEN>"
```
