# Runbook: Ops drill

## Goal

Validate operational readiness in a production-like environment and capture tuning recommendations.

## Command

- Text output:
  - `php artisan wids:ops-drill`
- JSON output:
  - `php artisan wids:ops-drill --format=json`
- Custom analysis window:
  - `php artisan wids:ops-drill --window-hours=72`
- Save artifact:
  - `php artisan wids:ops-drill --save`

Monthly scheduler also saves an artifact (default local path: `storage/app/private/ops-drills/*.json`).

## Checks included

- `queue_lag`:
  - due monitors, due without schedule, lag p95 vs thresholds
- `queue_wait`:
  - queue wait p95 from snapshot metadata in selected window
- `storage_quota`:
  - tenant storage usage vs configured plan quota thresholds
- `private_probe_coverage`:
  - stale probe ratio and due private-probe monitors without online probes

## Threshold tuning

Set in `.env`:

- `OPS_DRILL_QUEUE_LAG_WARN_SECONDS`
- `OPS_DRILL_QUEUE_LAG_FAIL_SECONDS`
- `OPS_DRILL_QUEUE_WAIT_WARN_MS`
- `OPS_DRILL_QUEUE_WAIT_FAIL_MS`
- `OPS_DRILL_STORAGE_USAGE_WARN_PERCENT`
- `OPS_DRILL_STORAGE_USAGE_FAIL_PERCENT`
- `OPS_DRILL_PRIVATE_PROBE_STALE_WARN_RATIO`
- `OPS_DRILL_PRIVATE_PROBE_STALE_FAIL_RATIO`

## Drill acceptance

For a healthy baseline:

1. `overall_status` should be `pass` or explicitly accepted `warn`.
2. `private_probe_coverage.due_without_online_probe_total` should be `0`.
3. Recommendations should be reviewed and converted into backlog/tasks.

## Tuning plan (next step)

Use planner command to convert drill + queue profile into actionable settings:

- `php artisan wids:ops-tune-plan --current-workers=4`
- JSON output: `php artisan wids:ops-tune-plan --format=json --current-workers=4`
- Save env snippet: `php artisan wids:ops-tune-plan --write-snippet=ops-drills/tune.env`
- Apply env overrides safely: `php artisan wids:ops-tune-plan --current-workers=4 --apply --env-file=.env`
- Rollback env from backup: `php artisan wids:ops-tune-plan --rollback-from="storage/app/ops-drills/env-backups/<file>.bak" --env-file=.env`

Typical outputs:

- suggested queue scale command (`docker compose up -d --scale queue=...`)
- proposed env overrides (e.g. autoscaling target, dispatch cap)

When `--apply` is used:
- command creates a backup before writing the env file (`storage/app/ops-drills/env-backups/`)
- JSON report includes `apply_result` with `backup_path` and `applied_keys`
- backup directory can be customized with `OPS_TUNE_BACKUP_DIR`

Operational guardrail:
- verify queue worker listens on priority queues from `WIDS_WORKER_QUEUES` (`wids-high,wids,wids-low,default`) before interpreting queue lag/wait trends.

Owner panel shortcut:
- Ops Center view (`/ops`) includes:
  - manual drill run and artifact listing
  - live drill checks + recommendations
  - tuning apply/rollback with backup history
  - artifact-based trend charts (status, queue lag p95, queue wait p95) with click-to-JSON drilldown
