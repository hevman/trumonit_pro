# Ops (backup/restore, DR, observability)

## Health

- Laravel health endpoint: `GET /up`
- SMB profile guardrail check: `php artisan wids:profile-check --format=json`

## Metrics (Prometheus text format)

1. Enable in `.env`:
   - `OPS_METRICS_ENABLED=true`
   - `OPS_METRICS_TOKEN=...` (recommended)
   - `OPS_METRICS_CACHE_TTL_SECONDS=5` (optional, reduce scrape computation)
2. Scrape:
   - `GET /ops/metrics` with header `Authorization: Bearer <token>`

Core metrics include:
- Scheduler lag: `TruMonit_scan_lag_seconds_p95`, `TruMonit_scan_lag_seconds_max`
- Queue wait: `TruMonit_queue_wait_ms_last_1h_avg`, `TruMonit_queue_wait_ms_last_1h_p95`
- Queue autoscaling: `TruMonit_queue_ready_jobs_weighted_total`, `TruMonit_queue_workers_recommended`, `TruMonit_queue_workers_max_configured`
- Multi-region anti-false-positive: `TruMonit_multi_region_verifications_last_1h_total`, `TruMonit_multi_region_suppressed_last_1h_total`
- Multi-region region SLA: `TruMonit_multi_region_region_attempts_last_1h{region}`, `TruMonit_multi_region_region_failures_last_1h{region}`, `TruMonit_multi_region_region_failure_ratio_last_1h{region}`, `TruMonit_multi_region_excluded_regions_last_1h_total`
- Error ratio: `TruMonit_error_rate_last_5m`, `TruMonit_error_rate_last_1h`
- Storage growth: `TruMonit_storage_bytes_total`, `TruMonit_storage_bytes_last_24h`
- Storage tiering: `TruMonit_storage_snapshots_total{tier}`, `TruMonit_storage_bytes_by_tier{tier}`
- Private probes: `TruMonit_private_probes_total`, `TruMonit_private_probes_online_total`, `TruMonit_private_probes_stale_total`
- Private probe policy: `TruMonit_private_probes_policy_auto_disabled_total`, `TruMonit_private_probes_residency_violations_total`
- Private probe tasks: `TruMonit_private_probe_scan_tasks_total{status}`, `TruMonit_private_probe_leases_expired_total`
- Private probe monitor routing: `TruMonit_private_probe_monitors_total`, `TruMonit_private_probe_monitors_due_total`, `TruMonit_private_probe_monitors_due_without_online_probe_total`
- Per-tenant storage: `TruMonit_storage_bytes_by_tenant{tenant_id,plan_tier}`

Reference files:
- Alert rules: `docs/observability/prometheus-alerts.yml`
- Dashboard seed: `docs/observability/grafana-dashboard.json`
- Queue lag runbook: `docs/observability/RUNBOOK_QUEUE_LAG.md`
- Queue autoscaling runbook: `docs/observability/RUNBOOK_QUEUE_AUTOSCALING.md`
- Multi-region verification runbook: `docs/observability/RUNBOOK_MULTI_REGION.md`
- Storage lifecycle runbook: `docs/observability/RUNBOOK_STORAGE_LIFECYCLE.md`
- Snapshot partition/archive runbook: `docs/observability/RUNBOOK_SNAPSHOT_PARTITION_ARCHIVE.md`
- Private probes runbook: `docs/observability/RUNBOOK_PRIVATE_PROBES.md`
- Ops drill runbook: `docs/observability/RUNBOOK_OPS_DRILL.md`
- Ops Go/No-Go validation record: `docs/observability/OPS_GO_NO_GO_2026-02-12.md`
- Error budget + on-call policy: `docs/observability/ERROR_BUDGET_ONCALL_ESCALATION.md`
- SSO OIDC foundation guide: `docs/SSO_OIDC.md`
- SSO SAML foundation+ guide: `docs/SSO_SAML.md`
- SCIM foundation guide: `docs/SCIM.md`

## Queue priority and autoscaling foundation

- Scan jobs are routed into priority queues:
  - high: `WIDS_SCAN_QUEUE_HIGH` (default: `wids-high`)
  - default: `WIDS_SCAN_QUEUE_DEFAULT` (default: `wids`)
  - low: `WIDS_SCAN_QUEUE_LOW` (default: `wids-low`)
- Worker queue order comes from `WIDS_WORKER_QUEUES` (default: `wids-high,wids,wids-low,default`).
- Scheduler fairness cap per tenant per minute:
  - `WIDS_SCAN_TENANT_DISPATCH_CAP_PER_TICK` (default: `250`)
- Manual/API/onboarding scans route to high queue by default.

Autoscaling planner command:
- `php artisan wids:queue-autoscale-plan`
- JSON output: `php artisan wids:queue-autoscale-plan --format=json`
- Compare against current scale:
  - `php artisan wids:queue-autoscale-plan --current-workers=4`
- Auto-apply command:
  - `php artisan wids:queue-autoscale-apply`
  - dry run: `php artisan wids:queue-autoscale-apply --dry-run --current-workers=4`
  - JSON output: `php artisan wids:queue-autoscale-apply --format=json`

Auto-apply envs:
- `WIDS_QUEUE_AUTOSCALING_CURRENT_WORKERS`
- `WIDS_QUEUE_AUTOSCALING_AUTO_APPLY_ENABLED`
- `WIDS_QUEUE_AUTOSCALING_AUTO_APPLY_COOLDOWN_MINUTES`
- `WIDS_QUEUE_AUTOSCALING_AUTO_APPLY_MAX_STEP`
- `WIDS_QUEUE_AUTOSCALING_AUTO_APPLY_COMMAND_TEMPLATE`
- `WIDS_QUEUE_AUTOSCALING_AUTO_APPLY_SAVE_ARTIFACT`
- Scheduler: when `WIDS_QUEUE_AUTOSCALING_AUTO_APPLY_ENABLED=true`, app scheduler runs `wids:queue-autoscale-apply --save` every minute.

## Multi-region outage confirmation foundation

- Optional anti-false-positive confirmation for DOWN states:
  - `WIDS_MULTI_REGION_ENABLED=true`
- Regions and tier-based scope:
  - `WIDS_MULTI_REGION_REGIONS=us-east,eu-west,ap-southeast`
  - `WIDS_MULTI_REGION_<TIER>_MAX_REGIONS`
- Quorum and timeout:
  - `WIDS_MULTI_REGION_QUORUM_FAILURES=2`
  - `WIDS_MULTI_REGION_PROBE_TIMEOUT_SECONDS=8`
- Health/SLA controls:
  - `WIDS_MULTI_REGION_HEALTH_ENABLED=true`
  - `WIDS_MULTI_REGION_HEALTH_WINDOW_HOURS=24`
  - `WIDS_MULTI_REGION_HEALTH_MIN_SAMPLES=12`
  - `WIDS_MULTI_REGION_HEALTH_MAX_FAILURE_RATIO=0.75`
  - `WIDS_MULTI_REGION_HEALTH_EXCLUDE_UNHEALTHY=true`
  - `WIDS_MULTI_REGION_HEALTH_MIN_REGIONS=2`
  - `WIDS_MULTI_REGION_HEALTH_STRICT_MIN_REGIONS=false`
  - `WIDS_MULTI_REGION_HEALTH_CACHE_PREFIX=wids:multi-region:health`

When enabled, DOWN from primary scan is verified in additional regions.
Incident is created only if failure quorum is met.
When strict health mode is enabled and too few healthy regions remain, verification is skipped with `reason=not_enough_healthy_regions`.

Region health snapshot command:
- `php artisan wids:multi-region-health`
- JSON output: `php artisan wids:multi-region-health --format=json`

## Storage tiering lifecycle foundation

- Optional hot -> archive storage transition for old snapshots:
  - `WIDS_STORAGE_LIFECYCLE_ENABLED=true`
- Archive path and scan batch:
  - `WIDS_STORAGE_ARCHIVE_PREFIX=wids/archive`
  - `WIDS_STORAGE_LIFECYCLE_CHUNK_SIZE=300`
- Per-tier archive threshold:
  - `WIDS_STORAGE_ARCHIVE_AFTER_DAYS_<TIER>`

Archive command:
- `php artisan wids:archive-snapshots`
- dry run: `php artisan wids:archive-snapshots --dry-run`
- per tenant: `php artisan wids:archive-snapshots --tenant_id=123`

Archive orphan cleanup (cost control):
- enable:
  - `WIDS_STORAGE_ORPHAN_CLEANUP_ENABLED=true`
- grace window:
  - `WIDS_STORAGE_ORPHAN_CLEANUP_GRACE_DAYS=3`
- scheduler time:
  - `WIDS_STORAGE_ORPHAN_CLEANUP_SCHEDULE_TIME=03:10`
- command:
  - `php artisan wids:storage-orphan-cleanup`
  - dry run: `php artisan wids:storage-orphan-cleanup --dry-run`
  - JSON output: `php artisan wids:storage-orphan-cleanup --format=json`
- scheduler: when enabled, app scheduler runs `wids:storage-orphan-cleanup` daily at `WIDS_STORAGE_ORPHAN_CLEANUP_SCHEDULE_TIME`.

Snapshot partition/archive table (DB growth control):
- enable:
  - `WIDS_SNAPSHOT_PARTITION_ENABLED=true`
- age threshold:
  - `WIDS_SNAPSHOT_PARTITION_AFTER_DAYS=30`
- batch size:
  - `WIDS_SNAPSHOT_PARTITION_CHUNK_SIZE=400`
- optional source purge after sync:
  - `WIDS_SNAPSHOT_PARTITION_PURGE_SOURCE=false`
- scheduler time:
  - `WIDS_SNAPSHOT_PARTITION_SCHEDULE_TIME=03:40`
- command:
  - `php artisan wids:snapshots-partition-archive`
  - purge source: `php artisan wids:snapshots-partition-archive --purge-source`
  - dry run: `php artisan wids:snapshots-partition-archive --dry-run --purge-source`
  - JSON output: `php artisan wids:snapshots-partition-archive --format=json`
- scheduler: when enabled, app scheduler runs `wids:snapshots-partition-archive` daily (and appends `--purge-source` when purge toggle is enabled).

## Private probes foundation

- Enable private probes:
  - `WIDS_PRIVATE_PROBES_ENABLED=true`
- Plan gating:
  - `WIDS_PRIVATE_PROBES_ALLOWED_TIERS=pro,enterprise`
- Residency allowlist by tier:
  - `WIDS_PRIVATE_PROBES_ALLOWED_REGIONS_LITE`
  - `WIDS_PRIVATE_PROBES_ALLOWED_REGIONS_PRO`
  - `WIDS_PRIVATE_PROBES_ALLOWED_REGIONS_ENTERPRISE`
  - `WIDS_PRIVATE_PROBES_ALLOWED_REGIONS_DEFAULT`
- Active fleet cap by tier:
  - `WIDS_PRIVATE_PROBES_MAX_ACTIVE_LITE`
  - `WIDS_PRIVATE_PROBES_MAX_ACTIVE_PRO`
  - `WIDS_PRIVATE_PROBES_MAX_ACTIVE_ENTERPRISE`
  - `WIDS_PRIVATE_PROBES_MAX_ACTIVE_DEFAULT`
- Stale threshold:
  - `WIDS_PRIVATE_PROBES_STALE_AFTER_MINUTES=10`
- Auto dispatch routing:
  - monitor tag: `WIDS_PRIVATE_PROBES_MONITOR_TAG=private_probe`
  - optional pin tag prefix: `WIDS_PRIVATE_PROBES_PIN_TAG_PREFIX=probe-id:`
  - optional region tag prefix: `WIDS_PRIVATE_PROBES_REGION_TAG_PREFIX=probe-region:`
  - fallback when region has no matching probe: `WIDS_PRIVATE_PROBES_REGION_FALLBACK=true`
  - dispatch caps: `WIDS_PRIVATE_PROBES_DISPATCH_MAX_PER_TICK`, `WIDS_PRIVATE_PROBES_DISPATCH_CAP_PER_TENANT`
  - dispatch only online probes: `WIDS_PRIVATE_PROBES_DISPATCH_REQUIRE_ONLINE=true`

API endpoints:
- Owner management: `GET/POST/PATCH /api/v1/private-probes`, `POST /api/v1/private-probes/{id}/rotate-token`
- Agent heartbeat: `POST /api/v1/private-probes/heartbeat` (Bearer probe token)
- Task contract:
  - owner queue/list: `GET/POST /api/v1/private-probes/{id}/tasks`
  - probe claim task: `POST /api/v1/private-probes/tasks/claim`
  - probe result ingest: `POST /api/v1/private-probes/tasks/{taskId}/result` + `X-Wids-Task-Signature`

Commands:
- `php artisan wids:private-probes-dispatch`
- Force all tagged active monitors: `php artisan wids:private-probes-dispatch --force`
- Tenant scoped run: `php artisan wids:private-probes-dispatch --tenant_id=123`
- Fleet policy enforcement:
  - `php artisan wids:private-probes-enforce`
  - dry run: `php artisan wids:private-probes-enforce --dry-run`
  - JSON output: `php artisan wids:private-probes-enforce --format=json`
  - tenant scope: `php artisan wids:private-probes-enforce --tenant_id=123`

Managed fleet policy automation:
- toggle: `WIDS_PRIVATE_PROBES_FLEET_AUTO_ENFORCE_ENABLED=true`
- schedule: `WIDS_PRIVATE_PROBES_FLEET_AUTO_ENFORCE_SCHEDULE_TIME=02:55`
- stale auto-disable threshold: `WIDS_PRIVATE_PROBES_FLEET_STALE_DISABLE_HOURS=72`
- event history cap in probe meta: `WIDS_PRIVATE_PROBES_FLEET_EVENT_HISTORY_LIMIT=20`
- scheduler runs `wids:private-probes-enforce` daily when auto-enforce toggle is enabled.

Behavior:
- `wids:scan` skips monitors tagged with `WIDS_PRIVATE_PROBES_MONITOR_TAG` when private probes are enabled.
- Those monitors are dispatched by `wids:private-probes-dispatch`.

## Ops drill and threshold tuning

Operational drill command:
- `php artisan wids:ops-drill`
- JSON: `php artisan wids:ops-drill --format=json`
- Save artifact: `php artisan wids:ops-drill --save`

Tuning planner command:
- `php artisan wids:ops-tune-plan --current-workers=4`
- JSON: `php artisan wids:ops-tune-plan --format=json --current-workers=4`
- Save `.env` snippet: `php artisan wids:ops-tune-plan --write-snippet=ops-drills/tune.env`
- Apply suggested overrides with backup: `php artisan wids:ops-tune-plan --current-workers=4 --apply --env-file=.env`
- Rollback from backup: `php artisan wids:ops-tune-plan --rollback-from="storage/app/ops-drills/env-backups/<file>.bak" --env-file=.env`

Notes:
- `--apply` writes a backup first under `storage/app/ops-drills/env-backups/`.
- JSON mode returns `apply_result` and `snippet_path` in the report payload.
- By default, local disk files are under `storage/app/private/ops-drills/` (`FILESYSTEM_DISK=local`).
- Owner Ops Center UI:
  - `GET /ops`
  - manual drill run: `POST /ops/drill/run`
  - tuning apply: `POST /ops/tune/apply`
  - tuning rollback: `POST /ops/tune/rollback`
  - drill artifact JSON view: `GET /ops/drills/{filename}`
  - trend charts from recent drill artifacts (status, queue lag p95, queue wait p95) with click-to-JSON drilldown
  - operational ownership panel (owner/on-call/escalation routes + readiness checks)
  - uses `OPS_TUNE_ENV_FILE` target and backup history from `storage/app/ops-drills/env-backups/`.
- Backup location can be overridden with `OPS_TUNE_BACKUP_DIR` (relative to `storage/app` unless absolute path).

Threshold envs:
- `OPS_DRILL_QUEUE_LAG_WARN_SECONDS`, `OPS_DRILL_QUEUE_LAG_FAIL_SECONDS`
- `OPS_DRILL_QUEUE_WAIT_WARN_MS`, `OPS_DRILL_QUEUE_WAIT_FAIL_MS`
- `OPS_DRILL_STORAGE_USAGE_WARN_PERCENT`, `OPS_DRILL_STORAGE_USAGE_FAIL_PERCENT`
- `OPS_DRILL_PRIVATE_PROBE_STALE_WARN_RATIO`, `OPS_DRILL_PRIVATE_PROBE_STALE_FAIL_RATIO`

Ownership and escalation envs:
- `OPS_SERVICE_OWNER`, `OPS_PRIMARY_ON_CALL`, `OPS_SECONDARY_ON_CALL`, `OPS_INCIDENT_COMMANDER`
- `OPS_ESCALATION_CHANNEL_SEV1`, `OPS_ESCALATION_CHANNEL_SEV2`, `OPS_ESCALATION_CHANNEL_STATUS`
- `OPS_ESCALATION_TARGET_CUSTOMER_UPDATES`, `OPS_ESCALATION_TARGET_INTERNAL_UPDATES`

## Weekly digest report packs (P2 analytics)

Weekly digest can persist per-tenant report packs (`JSON + CSV`) on each run.

Config:
- `WIDS_WEEKLY_DIGEST_STORE_REPORT=true`
- `WIDS_WEEKLY_DIGEST_REPORT_PACK_RETENTION_DAYS=180`

Command options:
- force store: `php artisan wids:weekly-digest --store-report`
- disable store: `php artisan wids:weekly-digest --no-store-report`
- override retention: `php artisan wids:weekly-digest --report-retention-days=90`

Storage path:
- `storage/app/reports/weekly-digest/tenant-<id>/...`

Owner Billing UI:
- list report packs: `GET /billing`
- download pack: `GET /billing/weekly-digest-reports/{weeklyDigestReport}/{format}`
  - supported formats: `json`, `csv`

## Backups

### Postgres (DB)

Backup:
- `docker compose exec -T db pg_dump -U TruMonit -d TruMonit > backup.sql`

Restore:
- `docker compose exec -T db psql -U TruMonit -d TruMonit < backup.sql`

### Storage (bodies/screenshots)

Snapshots store bodies/screenshots in `storage/` (local) or S3/MinIO (if configured).

- Local disk: back up `storage/app/` (plus any configured disks).
- MinIO/S3: back up the bucket (or enable bucket versioning + lifecycle).

Retention is enforced by `php artisan wids:cleanup` (see `.env.example`).
Retention days are applied per tenant plan tier (`lite/pro/enterprise`).

If `body_bytes_stored` / `screenshot_bytes_stored` were introduced after existing data, run:
- `php artisan wids:backfill-storage-bytes`
- dry run: `php artisan wids:backfill-storage-bytes --dry-run`

## Plan tier quotas

- Plan tier is stored in `tenants.plan_tier` (`lite` by default).
- Quotas can be configured in `.env` (`WIDS_PLAN_*` keys):
  - monitor count
  - tenant RPM
  - storage quota
  - retention days
- Global hard caps (`WIDS_LIMIT_MAX_URLS_PER_TENANT`, `WIDS_LIMIT_STORAGE_QUOTA_MB`) can still enforce stricter limits.

## SSO OIDC foundation

- Global feature toggle:
  - `WIDS_SSO_OIDC_ENABLED=true`
- Owner workspace configuration:
  - `GET /sso`
  - `PUT /sso`
- Login endpoints:
  - `GET /auth/oidc/redirect?tenant=<workspace-slug>`
  - `GET /auth/oidc/{tenantSlug}/callback`
- Related docs:
  - `docs/SSO_OIDC.md`
  - `docs/adr/ADR-0001-tenant-sso-oidc-foundation.md`

## SSO SAML foundation+

- Global feature toggle:
  - `WIDS_SSO_SAML_ENABLED=true`
- Validation toggles:
  - `WIDS_SSO_SAML_REQUIRE_DESTINATION=true`
  - `WIDS_SSO_SAML_REQUIRE_AUDIENCE=true`
  - `WIDS_SSO_SAML_REQUIRE_SIGNED_LOGOUT_REQUEST_DEFAULT=true`
  - `WIDS_SSO_SAML_REQUIRE_SIGNED_LOGOUT_RESPONSE_DEFAULT=false`
  - `WIDS_SSO_SAML_CLOCK_SKEW_SECONDS=120`
- Owner workspace configuration:
  - `GET /sso/saml`
  - `PUT /sso/saml`
- Auth endpoints:
  - `GET /auth/saml/redirect?tenant=<workspace-slug>`
  - `POST /auth/saml/{tenantSlug}/acs`
  - `POST /auth/saml/{tenantSlug}/slo`
  - `GET /auth/saml/{tenantSlug}/metadata`
- Logout behavior:
  - `POST /logout` starts SLO for SAML sessions when tenant has `slo_url` configured.
  - `POST /auth/saml/{tenantSlug}/slo` also supports IdP-initiated `SAMLRequest` and returns `LogoutResponse` to IdP.
  - Without `slo_url`, app falls back to local logout only.
- CSRF policy:
  - `auth/saml/*/acs` and `auth/saml/*/slo` are excluded from CSRF validation for IdP callbacks.
- Related docs:
  - `docs/SSO_SAML.md`
  - `docs/adr/ADR-0003-tenant-sso-saml-foundation-skeleton.md`

## SCIM foundation+

- Global feature toggle:
  - `WIDS_SCIM_ENABLED=true`
- Bulk controls:
  - `WIDS_SCIM_BULK_ENABLED=true`
  - `WIDS_SCIM_BULK_MAX_OPERATIONS=100`
  - `WIDS_SCIM_BULK_MAX_PAYLOAD_BYTES=1048576`
- Provisioning token management:
  - `php artisan wids:scim-token <tenant_id> --show`
  - `php artisan wids:scim-token <tenant_id> --enable --rotate`
  - `php artisan wids:scim-token <tenant_id> --disable`
  - role sync policy: `php artisan wids:scim-token <tenant_id> --group-role-sync=1 --group-role-sync-strict=1 --group-owner-groups="Owners" --group-member-groups="Members"`
- API base:
  - `GET /api/scim/v2/ServiceProviderConfig`
  - `GET /api/scim/v2/Users`
  - `POST /api/scim/v2/Users`
  - `PATCH /api/scim/v2/Users/{id}`
  - `GET /api/scim/v2/Groups`
  - `POST /api/scim/v2/Groups`
  - `PATCH /api/scim/v2/Groups/{id}`
  - `POST /api/scim/v2/Bulk`
- Related docs:
  - `docs/SCIM.md`
  - `docs/adr/ADR-0002-tenant-scim-foundation.md`

## Tenant notification channels

Notifications are tenant-scoped by default.

- Show channels:
  - `php artisan wids:tenant-notifications <tenant_id> --show`
- Show channels with effective fallback/default resolution:
  - `php artisan wids:tenant-notifications <tenant_id> --show --effective`
- Set channels:
  - `php artisan wids:tenant-notifications <tenant_id> --webhook_urls=https://... --slack_webhook_urls=https://... --teams_webhook_urls=https://... --discord_webhook_urls=https://... --email_recipients=ops@example.com`
- Toggle webhook localized human-readable block (`human_readable` in payload):
  - `php artisan wids:tenant-notifications <tenant_id> --webhook_human_readable=true`
- Set routing thresholds:
  - `php artisan wids:tenant-notifications <tenant_id> --webhook_min_severity=20 --slack_min_severity=30 --teams_min_severity=70 --discord_min_severity=70 --email_min_severity=60`
- Clear channels:
  - `php artisan wids:tenant-notifications <tenant_id> --clear`

Owner UI:
- `GET /notification-channels`

Optional compatibility fallback to global `.env` lists:
- `WIDS_NOTIFICATION_GLOBAL_FALLBACK_ENABLED=true` (default is `false`)

## DR notes (minimal)

- Keep **DB backup** + **storage backup** from the same time window.
- Restore order: storage (if needed) → DB → run `php artisan migrate --force`.
- After restore, start workers: `queue` and `scheduler` services.
