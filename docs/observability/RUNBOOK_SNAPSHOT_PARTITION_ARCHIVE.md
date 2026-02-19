# Runbook: Snapshot partition/archive table

## Goal

Control `url_snapshots` table growth by moving old archived snapshots into `url_snapshot_archives` with safe purge guardrails.

## Enablement

- `WIDS_SNAPSHOT_PARTITION_ENABLED=true`
- `WIDS_SNAPSHOT_PARTITION_AFTER_DAYS=30`
- `WIDS_SNAPSHOT_PARTITION_CHUNK_SIZE=400`
- `WIDS_SNAPSHOT_PARTITION_PURGE_SOURCE=false` (set to `true` only after validation)
- `WIDS_SNAPSHOT_PARTITION_SCHEDULE_TIME=03:40`

## Command

- Run sync:
  - `php artisan wids:snapshots-partition-archive`
- Purge source rows after sync (safe-mode checks still apply):
  - `php artisan wids:snapshots-partition-archive --purge-source`
- Dry run:
  - `php artisan wids:snapshots-partition-archive --dry-run --purge-source`
- JSON output:
  - `php artisan wids:snapshots-partition-archive --format=json`
- Tenant scope:
  - `php artisan wids:snapshots-partition-archive --tenant_id=<id>`

## Safe purge rules

Source row is not purged when:
- it is a monitor baseline snapshot,
- it is referenced by incident (`previous_snapshot_id` or `current_snapshot_id`),
- it is the latest snapshot for its monitor.

## Checks

1. Command reports non-zero `archived_inserted` or `archived_updated`.
2. Archive table growth is visible:
   - `TruMonit_storage_archive_rows_total`
   - `TruMonit_storage_archive_bytes_total`
3. UI/API incident flows still resolve snapshot references correctly.
4. `wids:cleanup` still deletes expired source snapshots and files as expected.

## Tuning

- If source table grows too fast:
  - lower `WIDS_SNAPSHOT_PARTITION_AFTER_DAYS`,
  - enable `WIDS_SNAPSHOT_PARTITION_PURGE_SOURCE` after dry-run verification.
- If archival job is heavy:
  - reduce `WIDS_SNAPSHOT_PARTITION_CHUNK_SIZE`,
  - scope by tenant and run incrementally.
