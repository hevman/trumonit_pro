# TruMonit

TruMonit is an outside-in monitoring and incident response service for websites, APIs, WordPress estates, and business-critical browser flows.

The product focus is deliberately narrow:
- detect downtime, broken responses, slow endpoints, certificate/DNS changes, and failing user journeys;
- turn noisy checks into useful incidents with dedupe, cooldowns, mute windows, and auto-resolve;
- give teams a clear workspace for status pages, incident handling, notifications, and operational evidence.

TruMonit is not an SEO crawler, analytics suite, or backlink product. SEO and crawler-data work belongs in Techbool.

## Dev Quickstart

1. Copy env:
   - `copy .env.example .env`
2. Start the shared local reverse proxy:
   - `cd ../reverse-proxy`
   - `docker network create reverse_proxy_network` (run once; ignore if it already exists)
   - `docker compose up -d`
3. Start TruMonit:
   - `cd ../tru_monit`
   - `docker compose up -d --build`
4. Run migrations and seed:
   - `docker compose exec -T app php artisan migrate --force`
   - `docker compose exec -T app php artisan db:seed`
   - Login: `test@example.com` / `TruMonit`
5. Open:
   - `http://trumonit.localhost`

Production uses the same reverse-proxy architecture with `trumonit.pro` and `www.trumonit.pro` through `docker-compose.production.yml`.

## Services

- `nginx` - HTTP entrypoint
- `app` - Laravel/PHP-FPM application
- `queue` - scan, notification, and incident jobs
- `scheduler` - periodic scan dispatch and cleanup tasks
- `playwright` - screenshot and browser journey service
- `db` - PostgreSQL
- `redis` - cache, queue, and locks
- `minio` - optional S3-compatible snapshot storage

## Product Surface

- Web panel: dashboard, monitors, incidents, status pages, notification channels, billing
- Public status pages: `GET /status/{slug}` and optional custom-domain root pages
- Public status badge: `GET /status/{slug}/badge.svg` and `GET /badge.svg` on a custom status-page domain
- API v1: token-authenticated monitor, incident, SIEM, and private-probe endpoints
- WordPress plugin: `wordpress-plugin/trumonit-monitor`

## Monitoring Modes

- `http` - public HTTP/HTTPS checks with redirects, response metadata, content rules, DNS/TLS capture, and optional screenshot baseline
- `journey` - browser flow checks executed by the Playwright service
- `port` - TCP port reachability checks
- `heartbeat` - cron/job check-ins with missing-heartbeat incidents

## Configuration

The monitoring engine still uses the historical `WIDS_*` env namespace. Treat it as an internal technical namespace, not a separate product name.

Important groups:
- URL safety and scan limits: `WIDS_ALLOWED_PORTS`, `WIDS_HTTP_MAX_REDIRECTS`, `WIDS_HTTP_MAX_BODY_BYTES`
- Rate limits: `WIDS_TENANT_RPM`, `WIDS_HOST_RPM`, `WIDS_API_*`
- Plan quotas: `WIDS_DEFAULT_PLAN_TIER`, `WIDS_PLAN_<TIER>_*`, `WIDS_LIMIT_*`
- Queue routing and autoscaling: `WIDS_SCAN_*`, `WIDS_WORKER_QUEUES`, `WIDS_QUEUE_AUTOSCALING_*`
- Incident behavior: `WIDS_ALERT_*`, `WIDS_AUTO_RESOLVE_*`, `WIDS_INCIDENT_*`
- Notifications: `WIDS_WEBHOOK_*`, `WIDS_SLACK_*`, `WIDS_TEAMS_*`, `WIDS_DISCORD_*`, `WIDS_EMAIL_*`
- Optional advanced features: `WIDS_SSO_*`, `WIDS_SCIM_*`, `WIDS_PRIVATE_PROBES_*`
- Snapshot retention and storage lifecycle: `WIDS_RETENTION_*`, `WIDS_STORAGE_*`, `WIDS_SNAPSHOT_PARTITION_*`

## Operations

- Health: `GET /up`
- Security env checklist: `docs/ENV_SECURITY_CHECKLIST.md`
- API docs: `docs/API_V1.md`
- TruMonit technical playbook: `docs/TRUMONIT_TECHNICAL_PLAYBOOK.md`

Useful commands:
- `php artisan wids:scan`
- `php artisan wids:cleanup`
- `php artisan wids:profile-check --format=json`
- `php artisan wids:queue-autoscale-plan`
- `php artisan wids:archive-snapshots`
- `php artisan wids:private-probes-dispatch`

## Status Pages

- Public URL by slug: `GET /status/{slug}`
- Badge by slug: `GET /status/{slug}/badge.svg`
- Custom domain support: status page on `/`, badge on `/badge.svg`
- Cache behavior is controlled per status page with `cache_ttl_seconds`

## WordPress Integration

The only WordPress plugin shipped in this repository is `wordpress-plugin/trumonit-monitor`.

It is intentionally operational:
- connect a WordPress admin panel to TruMonit by API token or login;
- show monitor and incident health in wp-admin;
- trigger manual scans for selected monitors;
- link operators back to the TruMonit dashboard.

## Product Boundaries

Keep these boundaries clear:
- TruMonit: monitoring, incidents, status pages, uptime/API/journey checks, WordPress operations plugin.
- Techbool: SEO audit, crawler corpus, search visibility, link/domain intelligence.
- TruCookie: separate cookie/CMP product. TruMonit may load its consent banner as an external public-site dependency, but no TruCookie WordPress plugin belongs in this repository.

Historical docs and adjacent plugin code may still exist in the repository, but README and PLAN are the source of truth for the TruMonit service.
