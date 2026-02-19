# Runbook: Queue backlog / scheduler lag

## Signals

- `TruMonit_redis_queue_jobs_total{queue="wids-high",state="ready"}`
- `TruMonit_redis_queue_jobs_total{queue="wids",state="ready"}`
- `TruMonit_redis_queue_jobs_total{queue="wids-low",state="ready"}`
- `TruMonit_scan_lag_seconds_p95`
- `TruMonit_queue_wait_ms_last_1h_p95`
- `TruMonit_error_rate_last_5m`
- `TruMonit_queue_workers_recommended`

## First response

1. Check queue worker count and status:
   - `docker compose ps queue`
2. Check worker logs:
   - `docker compose logs --tail=200 queue`
3. Check scheduler logs:
   - `docker compose logs --tail=200 scheduler`

## Mitigation

1. Scale workers temporarily:
   - `docker compose up -d --scale queue=4`
   - (or use planner) `php artisan wids:queue-autoscale-plan --current-workers=4`
2. If backlog is from one noisy tenant, use:
   - `php artisan wids:scan --tenant_id=<id>`
   - pause noisy monitors in UI.
3. If Redis saturation is suspected:
   - check Redis memory and latency,
   - restart Redis only after queue drain plan.

## Recovery criteria

- `wids-high` backlog trends down first, then `wids`, then `wids-low`.
- `scan lag p95` returns below alert threshold.
- `error_rate_last_5m` returns to baseline.
