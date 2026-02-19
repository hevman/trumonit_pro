# Runbook: Queue autoscaling

## Goal

Keep scan lag and queue wait stable by adjusting `queue` worker replicas based on actual backlog.

## Inputs

- `TruMonit_redis_queue_jobs_total{queue,state}`
- `TruMonit_queue_ready_jobs_weighted_total`
- `TruMonit_queue_workers_recommended`
- `TruMonit_queue_workers_min_configured`
- `TruMonit_queue_workers_max_configured`
- `TruMonit_scan_lag_seconds_p95`
- `TruMonit_queue_wait_ms_last_1h_p95`

## Quick check

1. Generate scaling recommendation:
   - `php artisan wids:queue-autoscale-plan --current-workers=<N>`
   - `php artisan wids:queue-autoscale-plan --current-workers=<N> --format=json`
2. Inspect queue split:
   - high: `wids-high`
   - normal: `wids`
   - low: `wids-low`

## Manual scale action

- Scale to recommendation:
  - `docker compose up -d --scale queue=<recommended_workers>`
- Re-check after 5-10 minutes:
  - high queue backlog should decrease first
  - `scan lag p95` should trend down
  - `queue wait p95` should trend down

## Auto-apply mode

- Command:
  - `php artisan wids:queue-autoscale-apply`
  - dry run: `php artisan wids:queue-autoscale-apply --dry-run --current-workers=<N>`
  - machine format: `php artisan wids:queue-autoscale-apply --format=json`
- Guardrails:
  - cooldown between actions: `WIDS_QUEUE_AUTOSCALING_AUTO_APPLY_COOLDOWN_MINUTES`
  - max workers delta per action: `WIDS_QUEUE_AUTOSCALING_AUTO_APPLY_MAX_STEP`
  - runtime command template: `WIDS_QUEUE_AUTOSCALING_AUTO_APPLY_COMMAND_TEMPLATE` (must include `{workers}`)
  - scheduler toggle: `WIDS_QUEUE_AUTOSCALING_AUTO_APPLY_ENABLED=true`
- Scheduler behavior:
  - runs every minute when auto-apply toggle is enabled
  - skips action when there is no worker change, cooldown is active, or auto-apply is disabled

## Artifacts and traceability

- Auto-apply artifact (if enabled):
  - `storage/app/ops-drills/queue-autoscale/auto-*.json`
- Artifact includes:
  - queue metrics snapshot
  - planner recommendation
  - guarded target workers
  - runtime command and execution result
  - skipped/applied decision reason

## Guardrails

- Never scale below `WIDS_QUEUE_AUTOSCALING_MIN_WORKERS`.
- Treat sustained `recommended ~= max` as capacity warning:
  - increase throughput per worker (timeouts, bottlenecks)
  - reduce noisy tenant pressure (pause/noise cleanup)
  - consider increasing `WIDS_QUEUE_AUTOSCALING_MAX_WORKERS`
- Use `--force` only for emergency/manual override (bypasses toggle + cooldown).

## Incident notes

Capture in incident timeline:
- current workers
- recommended workers
- backlog by queue before/after
- lag and queue-wait before/after
