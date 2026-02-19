# Runbook: Private probes

## Goal

Keep private probe fleet healthy for internal/private endpoint monitoring.

## Setup

- `WIDS_PRIVATE_PROBES_ENABLED=true`
- `WIDS_PRIVATE_PROBES_ALLOWED_TIERS=pro,enterprise`
- `WIDS_PRIVATE_PROBES_ALLOWED_REGIONS_<TIER>=*|comma,list`
- `WIDS_PRIVATE_PROBES_MAX_ACTIVE_<TIER>=<N>`
- `WIDS_PRIVATE_PROBES_STALE_AFTER_MINUTES=10`
- `WIDS_PRIVATE_PROBES_MONITOR_TAG=private_probe`
- `WIDS_PRIVATE_PROBES_DISPATCH_REQUIRE_ONLINE=true`

## Registration flow

1. Owner creates probe via API (`POST /api/v1/private-probes`).
2. Save returned token once (not retrievable later).
3. Agent sends heartbeat to:
   - `POST /api/v1/private-probes/heartbeat`
   - `Authorization: Bearer <probe_token>`
4. Owner queues scan task for probe:
   - `POST /api/v1/private-probes/{id}/tasks`
5. Agent claims and returns result:
   - `POST /api/v1/private-probes/tasks/claim`
   - `POST /api/v1/private-probes/tasks/{taskId}/result` + `X-Wids-Task-Signature`

## Auto dispatch flow

1. Tag monitor with `private_probe` (or value from `WIDS_PRIVATE_PROBES_MONITOR_TAG`).
2. Optional routing tags:
   - `probe-id:<id>` pin monitor to specific probe.
   - `probe-region:<region>` prefer probes with matching `region_hint`.
3. Scheduler runs:
   - `php artisan wids:private-probes-dispatch`
4. Command creates queued probe tasks with fair distribution by probe backlog.

## Managed fleet policy

1. Configure auto enforcement:
   - `WIDS_PRIVATE_PROBES_FLEET_AUTO_ENFORCE_ENABLED=true`
   - `WIDS_PRIVATE_PROBES_FLEET_AUTO_ENFORCE_SCHEDULE_TIME=02:55`
   - `WIDS_PRIVATE_PROBES_FLEET_STALE_DISABLE_HOURS=72`
2. Run command manually:
   - `php artisan wids:private-probes-enforce`
   - dry run: `php artisan wids:private-probes-enforce --dry-run`
3. Enforcement actions:
   - disable probes with region outside residency allowlist for tenant tier,
   - disable stale probes above fleet stale threshold,
   - enforce max active probes per tenant tier (keeps most recently seen probes).

## Health signals

- `TruMonit_private_probes_total`
- `TruMonit_private_probes_active_total`
- `TruMonit_private_probes_online_total`
- `TruMonit_private_probes_stale_total`
- `TruMonit_private_probes_policy_auto_disabled_total`
- `TruMonit_private_probes_residency_violations_total`
- `TruMonit_private_probe_scan_tasks_total{status}`
- `TruMonit_private_probe_leases_expired_total`
- `TruMonit_private_probe_monitors_total`
- `TruMonit_private_probe_monitors_due_total`
- `TruMonit_private_probe_monitors_due_without_online_probe_total`

## Recovery

1. If probe stale, verify network path to API endpoint.
2. Rotate token if suspected compromise:
   - `POST /api/v1/private-probes/{id}/rotate-token`
3. Disable probe if no longer trusted:
   - `PATCH /api/v1/private-probes/{id}` with `is_active=false`
4. If due monitor count with no online probes is non-zero, either:
   - restore heartbeat on at least one probe for tenant, or
   - remove/adjust monitor probe tags so it is not blocked on private probe routing.
5. If policy auto-disable happened unexpectedly:
   - inspect probe `meta.fleet_policy.last_reason`,
   - adjust residency allowlist / max-active limits for plan tier,
   - reactivate probe after policy fix (`PATCH /api/v1/private-probes/{id}` with `is_active=true`).
