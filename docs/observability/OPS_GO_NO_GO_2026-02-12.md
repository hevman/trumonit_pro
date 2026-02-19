# Ops Go/No-Go Validation Record (2026-02-12)

## Scope

Validation of operational readiness items from `PLAN.md`:
- runbook drill validation,
- alert-threshold tuning from real traffic,
- action closure logging.

Environment: `docker compose` (local production-like stack), UTC timestamps.

## Commands executed

1. Drill (baseline, with artifact):
   - `php artisan wids:ops-drill --format=json --save`
2. Tuning planner (safe apply + snippet):
   - `php artisan wids:ops-tune-plan --format=json --current-workers=1 --write-snippet=ops-drills/tune-20260212-go-no-go.env --apply --env-file=.env`
3. Rollback validation:
   - `php artisan wids:ops-tune-plan --format=json --rollback-from="storage/app/ops-drills/env-backups/env-20260212_215506_065238.bak" --env-file=.env`
4. Re-apply after rollback test:
   - `php artisan wids:ops-tune-plan --format=json --current-workers=1 --write-snippet=ops-drills/tune-20260212-go-no-go.env --apply --env-file=.env`

## Artifacts

- Drill artifacts:
  - `storage/app/private/ops-drills/drill-20260212_214012.json`
  - `storage/app/private/ops-drills/drill-20260212_214104.json`
  - `storage/app/private/ops-drills/drill-20260212_215431.json`
  - `storage/app/private/ops-drills/drill-20260212_215615.json`
  - `storage/app/private/ops-drills/drill-20260212_220048.json`
- Tuning snippet:
  - `storage/app/private/ops-drills/tune-20260212-go-no-go.env`
- Env backups (apply/rollback trail):
  - `storage/app/ops-drills/env-backups/env-20260212_215506_065238.bak`
  - `storage/app/ops-drills/env-backups/env-20260212_215721_426724.bak`

## Findings

1. Initial drill failure root cause:
   - queue backlog was concentrated in `wids-high`/`wids-low`,
   - queue worker process was running with outdated queue list (`--queue=wids,default`),
   - result: scans from priority queues were not consumed, causing lag/wait inflation.
2. Corrective action:
   - recreated queue service with current compose command:
     - `php artisan queue:work --queue=wids-high,wids,wids-low,default --sleep=1 --tries=1 --timeout=0`
   - backlog drained to `0` on both `wids-high` and `wids-low`,
   - stale monitor resumed scanning (`last_scanned_at` advanced, `next_scan_at` moved forward).
3. Current status after remediation:
   - `queue_lag`: PASS,
   - `queue_wait`: still FAIL in 24h/1h windows due historical backlog samples (expected to normalize after clean traffic window).

## Threshold tuning decision

Applied overrides from real traffic profile:
- `WIDS_QUEUE_AUTOSCALING_TARGET_READY_JOBS_PER_WORKER=96` (from `120`)
- `WIDS_SCAN_TENANT_DISPATCH_CAP_PER_TICK=200` (from `250`)

Rationale:
- react earlier to queue pressure,
- reduce dispatch burst during recovery and heavy bursts.

Rollback path verified and operational.

## Action closure record

1. **Queue worker command drift fixed**  
   Owner: Platform owner (primary on-call)  
   Status: DONE (2026-02-12)

2. **Tuning apply/rollback flow validated**  
   Owner: Platform owner  
   Status: DONE (2026-02-12)

3. **Post-recovery queue-wait revalidation on clean window (24h)**  
   Owner: Platform owner + Incident commander  
   Due: 2026-02-13  
   Status: OPEN

## Go/No-Go mapping

- Runbook validation: completed (artifact + findings + closure record created).
- Alert tuning from real traffic: foundation complete, awaiting clean-window verification.
- Error budget / ownership: defined in `docs/observability/ERROR_BUDGET_ONCALL_ESCALATION.md`.

---

## Addendum (2026-02-13)

### Commands executed

1. Re-apply tuning overrides after additional ops review:
   - `php artisan wids:ops-tune-plan --format=json --current-workers=1 --apply --env-file=.env`
2. Cache refresh:
   - `php artisan optimize:clear`
3. Post-apply drill (24h window + artifact):
   - `php artisan wids:ops-drill --save --format=json`
4. Clean short-window validation:
   - `php artisan wids:ops-drill --window-hours=1 --format=json`

### Applied values

- `WIDS_QUEUE_AUTOSCALING_TARGET_READY_JOBS_PER_WORKER=76`
- `WIDS_SCAN_TENANT_DISPATCH_CAP_PER_TICK=160`

### New artifacts and evidence

- Env backup (apply trail):
  - `storage/app/ops-drills/env-backups/env-20260213_122118_873509.bak`
- Drill artifact:
  - `storage/app/ops-drills/drill-20260213_122159.json`

### Findings

1. `--window-hours=24` remains `FAIL` on queue wait due to historical backlog samples still included in window.
2. `--window-hours=1` is `PASS` (`queue_wait_p95_ms=1130.4`), which confirms current settings are healthy on fresh traffic.
3. Operational ownership/escalation readiness is now visible in Ops Center UI and backed by env config.

### Action closure update

- **Post-recovery queue-wait revalidation on clean 24h window**  
  Owner: Platform owner + Incident commander  
  Due: 2026-02-14  
  Status: OPEN (short-window pass confirmed; full 24h clean-window pass pending)
