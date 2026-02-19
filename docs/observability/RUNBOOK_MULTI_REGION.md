# Runbook: Multi-region outage confirmation

## Goal

Reduce false-positive DOWN incidents by requiring regional failure quorum before alert emission.

## Enablement

- `WIDS_MULTI_REGION_ENABLED=true`
- `WIDS_MULTI_REGION_REGIONS=us-east,eu-west,ap-southeast`
- `WIDS_MULTI_REGION_<TIER>_MAX_REGIONS`
- `WIDS_MULTI_REGION_QUORUM_FAILURES=2`
- `WIDS_MULTI_REGION_HEALTH_ENABLED=true`
- `WIDS_MULTI_REGION_HEALTH_WINDOW_HOURS=24`
- `WIDS_MULTI_REGION_HEALTH_MIN_SAMPLES=12`
- `WIDS_MULTI_REGION_HEALTH_MAX_FAILURE_RATIO=0.75`
- `WIDS_MULTI_REGION_HEALTH_EXCLUDE_UNHEALTHY=true`
- `WIDS_MULTI_REGION_HEALTH_MIN_REGIONS=2`
- `WIDS_MULTI_REGION_HEALTH_STRICT_MIN_REGIONS=false`

## Signals

- `TruMonit_multi_region_verifications_last_1h_total`
- `TruMonit_multi_region_confirmed_last_1h_total`
- `TruMonit_multi_region_suppressed_last_1h_total`
- `TruMonit_multi_region_excluded_regions_last_1h_total`
- `TruMonit_multi_region_region_attempts_last_1h{region}`
- `TruMonit_multi_region_region_failures_last_1h{region}`
- `TruMonit_multi_region_region_failure_ratio_last_1h{region}`
- `TruMonit_scan_lag_seconds_p95`
- `TruMonit_error_rate_last_5m`

## Investigation

1. Check incident/snapshot details for `meta.multi_region`.
2. Confirm whether primary region failed but quorum was not met.
3. Review region health snapshot:
   - `php artisan wids:multi-region-health`
   - If strict mode is enabled and too few healthy regions remain, expect `reason=not_enough_healthy_regions`.
4. If suppressions are too frequent, tune:
   - quorum (`WIDS_MULTI_REGION_QUORUM_FAILURES`)
   - region count per plan tier
   - probe timeout
   - health controls (`WIDS_MULTI_REGION_HEALTH_*`)

## Tuning guidelines

- Too many false alerts: increase regions or quorum.
- Real incidents delayed: reduce quorum or lower tier region count.
- High probe cost/latency: reduce per-tier max regions.
- If one region is consistently noisy: tighten health controls to exclude unhealthy regions earlier.
- If strict mode blocks too many checks: disable `WIDS_MULTI_REGION_HEALTH_STRICT_MIN_REGIONS` or lower `WIDS_MULTI_REGION_HEALTH_MIN_REGIONS`.

## Related alerts

- `SaasMonitMultiRegionSuppressionSpike`
- `SaasMonitMultiRegionRegionFailureRatioHigh`
