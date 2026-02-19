# Runbook: Storage lifecycle (hot -> archive + orphan cleanup)

## Goal

Reduce hot storage growth while keeping snapshot evidence accessible.
For DB row growth controls, use `RUNBOOK_SNAPSHOT_PARTITION_ARCHIVE.md`.

## Enablement

- `WIDS_STORAGE_LIFECYCLE_ENABLED=true`
- `WIDS_STORAGE_ARCHIVE_PREFIX=wids/archive`
- `WIDS_STORAGE_ARCHIVE_AFTER_DAYS_<TIER>`
- `WIDS_STORAGE_ORPHAN_CLEANUP_ENABLED=true`
- `WIDS_STORAGE_ORPHAN_CLEANUP_GRACE_DAYS=3`

## Command

- Execute lifecycle:
  - `php artisan wids:archive-snapshots`
- Dry run:
  - `php artisan wids:archive-snapshots --dry-run`
- Single tenant:
  - `php artisan wids:archive-snapshots --tenant_id=<id>`

## Orphan cleanup command

- Execute orphan cleanup:
  - `php artisan wids:storage-orphan-cleanup`
- Dry run:
  - `php artisan wids:storage-orphan-cleanup --dry-run`
- JSON output:
  - `php artisan wids:storage-orphan-cleanup --format=json`
- Optional force (run when toggle disabled):
  - `php artisan wids:storage-orphan-cleanup --force`

## Checks

1. Verify archive progress metrics:
   - `TruMonit_storage_snapshots_total{tier="archive"}`
   - `TruMonit_storage_bytes_by_tier{tier="archive"}`
2. Confirm snapshot details still load (body/screenshot paths updated).
3. Confirm orphan cleanup summary:
   - orphan candidates found,
   - old unreferenced files deleted,
   - recent files kept during grace window.
4. Monitor cleanup behavior after archive (`wids:cleanup` still removes expired assets).

## Tuning

- Archive too aggressive:
  - increase `WIDS_STORAGE_ARCHIVE_AFTER_DAYS_<TIER>`
- Hot storage growth too fast:
  - lower archive-after days for selected tiers
  - tighten retention policy per tier
- Archive storage cost still high:
  - enable orphan cleanup schedule
  - lower orphan grace days only after validating no race conditions
