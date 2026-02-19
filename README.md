# TruMonit (wIDS MVP)

External (outside-in) monitoring for web anomalies with incident escalation.

## Dev quickstart

1. Copy env:
   - `copy .env.example .env`
2. Start stack:
   - `docker compose up -d --build`
3. Run migrations + seed:
   - `docker compose exec -T app php artisan migrate --force`
   - `docker compose exec -T app php artisan db:seed`
   - Login (seeded user): `test@example.com` / `TruMonit`
4. Open:
   - `http://localhost:%WEB_PORT%` (default `8090`)

## Services

- `nginx` (HTTP entrypoint)
- `app` (PHP-FPM)
- `queue` (Laravel queue worker)
- `scheduler` (Laravel scheduler)
- `playwright` (screenshot service for appearance monitoring)
- `db` (PostgreSQL)
- `redis` (Redis)
- `minio` (S3-compatible storage, optional)

## Auth

- Web panel: session-based auth (`/login`, `/register`)
- API: token auth via Laravel Sanctum (`/api/v1/...`, legacy `/api/...` optional)
- API v1 docs: `docs/API_V1.md`

## wIDS config (high level)

- Outbound safety: `WIDS_ALLOWED_PORTS` (default `80,443`) + SSRF hardening for private/metadata IPs
- Rate limits: `WIDS_TENANT_RPM`, `WIDS_HOST_RPM`
- Limits: `WIDS_LIMIT_MAX_URLS_PER_TENANT`, `WIDS_LIMIT_STORAGE_QUOTA_MB` (0 = unlimited)
- Plan tiers: `WIDS_DEFAULT_PLAN_TIER` + `WIDS_PLAN_<TIER>_*` for monitor/RPM/storage/retention quotas
- Optional multi-region outage confirmation: `WIDS_MULTI_REGION_*` (quorum-based DOWN confirmation)
- Optional private probes foundation: `WIDS_PRIVATE_PROBES_*` (owner-managed probes + heartbeat token auth)
- Private probe remote task contract: owner queues tasks, probe claims lease, signed result ingest
- Private probe auto-dispatch: monitors tagged `private_probe` are scheduled to online probes with fair routing (`probe-id:<id>`, `probe-region:<region>` optional tags)
- Retention: `WIDS_RETENTION_ENABLED`, `WIDS_RETENTION_DAYS` + cleanup command `php artisan wids:cleanup`
- Auto-resolve: `WIDS_AUTO_RESOLVE_ENABLED`, `WIDS_AUTO_RESOLVE_KINDS` (e.g. `fetch_error,unexpected_status,journey_failed`)
- Mute: per-monitor mute via UI, plus `WIDS_NOTIFICATIONS_RESPECT_MUTE`
- Maintenance windows: UI `/maintenance-windows`, plus `WIDS_NOTIFICATIONS_RESPECT_MAINTENANCE`
- Notifications: tenant-scoped webhook / Slack / Teams / Discord / email channels with per-channel severity routing (`/notification-channels`)
- Optional global fallback for notifications: `WIDS_NOTIFICATION_GLOBAL_FALLBACK_ENABLED=false` (default secure mode)
- Billing webhooks: for non-`manual` providers set `WIDS_BILLING_<PROVIDER>_WEBHOOK_SECRET`; missing secret causes webhook signature rejection

## Ops

- Health: `GET /up`
- Metrics (optional): `GET /ops/metrics` (Prometheus text format, see `.env.example` + `docs/OPS.md`)
- Security env checklist: `docs/ENV_SECURITY_CHECKLIST.md`
- SMB profile check: `php artisan wids:profile-check --format=json`
- Bundle technical playbook: `docs/BUNDLE_TECHNICAL_PLAYBOOK.md`
- Backup/restore notes: `docs/OPS.md`
- Queue autoscaling planner: `php artisan wids:queue-autoscale-plan`
- Storage lifecycle archive: `php artisan wids:archive-snapshots`
- Private probe auto-dispatch tick: `php artisan wids:private-probes-dispatch`
- Ops drill and tuning report: `php artisan wids:ops-drill --format=json --save`
- Ops tuning planner: `php artisan wids:ops-tune-plan --current-workers=4 --write-snippet=ops-drills/tune.env`
- Backfill legacy snapshot byte counters: `php artisan wids:backfill-storage-bytes`
- Tenant notification channels:
  - Show: `php artisan wids:tenant-notifications <tenant_id> --show`
  - Show (effective resolved channels): `php artisan wids:tenant-notifications <tenant_id> --show --effective`
  - Update channels: `php artisan wids:tenant-notifications <tenant_id> --slack_webhook_urls=https://... --teams_webhook_urls=https://... --discord_webhook_urls=https://... --email_recipients=ops@example.com`
  - Toggle webhook human-readable payload block: `php artisan wids:tenant-notifications <tenant_id> --webhook_human_readable=true`
  - Update routing: `php artisan wids:tenant-notifications <tenant_id> --slack_min_severity=40 --teams_min_severity=80`

## SIEM export (P1)

- Endpoint (requires API token + `owner` role): `GET /api/siem/incidents`
- Formats:
  - `?format=json` (default)
  - `?format=cef`
  - `?format=leef`
- Incremental pull: `?since=2026-02-11T00:00:00Z&limit=200` (filters by `updated_at`)

## Public status page

- Public URL by slug: `GET /status/{slug}`
- Optional custom domain per status page (serves on `/` for that host)
- Per-page cache controls via `cache_ttl_seconds` (response `Cache-Control` and `X-Status-Cache-TTL`)

## User journeys (P1)

Set `scan_mode=journey` on a monitored URL and provide `journey_steps` as JSON.
The Playwright service executes steps (goto/click/fill/wait/assert*) and returns final HTML + screenshot + browser errors.

## P0 onboarding and templates

- Owner quick-start wizard: `GET /onboarding`
- Monitor templates in UI:
  - `status_page`
  - `login_journey`
  - `api_health`
- Wizard creates monitor, dispatches first scan, and guides to set baseline after first snapshot.

## Advanced search and saved searches

- Incidents: advanced filters for severity, occurrences, date range, assignee, snooze, and comments.
- Monitored URLs: advanced filters for state (UP/DOWN/PAUSED/MUTED), mode, active/muted flags, and numeric ranges.
- Saved search management UI (owner): `GET /saved-views`
