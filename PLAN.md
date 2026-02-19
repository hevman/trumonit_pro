# TruMonit - Master Plan (wIDS + Monit24 Lite)

Last update: 2026-02-13
Scope owner: Product + Engineering

This file is the single source of truth for product, delivery, and operational readiness.

Legend:
- [x] done
- [~] partial / needs hardening
- [ ] planned

Status rules:
- [x] means implemented, tested, and documented.
- [~] means usable, but missing at least one: production hardening, tests, docs, or operational runbook.
- [ ] means not implemented yet.

## 1. Strategy and positioning

Product goal:
- Build a reliable outside-in monitoring service with wIDS signals for SMB and enterprise.
- Be "Monit24 Lite" in simplicity and speed for SMB, then scale to Pro and Enterprise requirements.

Value proposition:
- Fast incident detection (availability + content + security signals).
- Low noise by default (baseline, dedupe, auto-resolve, cooldowns, maintenance windows).
- Clear operations model (queue-based scanning, retention, backup/restore, strict SSRF hardening).

## 2. Scope and non-goals

In scope:
- Website/API monitoring, browser journeys, incidenting, filters, integrations (webhook/slack/email), status pages.

Non-goals (explicit):
- No SMS or voice alerting.
- No formal SLA report generation (PDF/compliance contract reports).
- Basic operational stats are allowed and required.

## 3. Customer segments and plans

Lite (SMB):
- Core monitoring, incidents, baseline, mute/maintenance, saved views, status page.
- Focus: quick setup, low noise, clear UP/DOWN.

Pro:
- Better collaboration, richer integrations, stronger API, advanced filtering/search.

Enterprise:
- SSO/SCIM, advanced RBAC, compliance workflows, data isolation, private probes, HA/DR.

## 4. Success metrics (tracked weekly)

Business:
- Activation: % tenants with first monitor and first successful scan in 24h.
- Time-to-value: median time from signup to first useful alert.
- Retention/churn by plan tier.

Product quality:
- False positive rate (per 1000 scans).
- Alert fatigue proxy: repeated incidents suppressed by dedupe/cooldown.
- Incident precision for DOWN states.

Operations:
- Scan lag p95 (scheduled time vs actual scan start).
- Queue depth and queue wait p95.
- Error ratio in scans (5m/1h windows).
- Storage growth per tenant and global.
- Backup restore drill success (RTO/RPO target).

## 5. Current baseline (already shipped)

Platform and architecture:
- [x] Docker stack: nginx, app, queue, scheduler, postgres, redis, playwright, optional minio.
- [x] Multi-tenant model (tenant_id), sessions + API tokens.
- [x] Owner/member RBAC for write actions.

Monitoring engine:
- [x] URL CRUD, frequency, expected status range.
- [x] HTTP + journey scan modes.
- [x] Snapshot persistence (status/headers/body hash/body file/screenshot).
- [x] Incident engine with dedupe and occurrences tracking.
- [x] Auto-resolve for recovery kinds.

Signals and detections:
- [x] fetch_error, unexpected_status, final_url_change.
- [x] content_change, appearance_change, suspicious_pattern.
- [x] browser_console_error, browser_request_failed, journey_failed.
- [x] tls_changed, tls_expiring, dns_change, content_rule_violation.

Noise reduction and controls:
- [x] Baseline support.
- [x] Mute and maintenance windows.
- [x] Alert threshold and cooldown per monitor.

Operations and integrations:
- [x] Retention cleanup job.
- [x] Audit log.
- [x] Webhook/slack/email channels with retry/backoff.
- [x] SIEM export endpoint.
- [x] Saved views for incidents and monitors.
- [x] Incident collaboration basics (comments, assignment, snooze, history).
- [x] Public status page (read-only, cache controls, custom domain support).
- [x] Ops drill command (`wids:ops-drill`) + monthly artifact schedule for threshold tuning.
- [x] Ops tuning planner (`wids:ops-tune-plan`) with env override snippet, safe `--apply` (backup), `--rollback-from`, and owner UI in Ops Center for apply/rollback history.
- [x] Ops ownership readiness in Ops Center (owner/on-call/escalation panel + Go/No-Go readiness checks based on env + drill artifacts + policy file presence).

## 6. Core quality gates (must be solid before scale)

- [x] Observability stack (metrics + dashboards + alerts)
Done when:
- `/ops/metrics` exposes scan lag, queue wait, error rate, and storage growth.
- dashboard + runbook assets exist in `docs/observability/*`.
- queue backlog/error spike/scheduler lag alerts are defined.

- [x] Tenant quotas and limits per plan
Done when:
- tier limits are enforced for monitors, tenant RPM, storage quota, and retention days.
- UI/API return upgrade-path feedback when limits are hit.

- [x] Backup/restore + DR minimum
Done when:
- backup/restore commands and execution order are documented.
- restore procedure and service restart flow are defined.

- [x] DB growth optimization and indexing
Done when:
- core indexes support scheduler flow and incident filtering/dedupe.
- index review decisions are documented in migrations.

- [x] Safe bulk operations
Done when:
- import/export and mass actions are owner-only.
- audit visibility and tenant scoping are enforced.

## 7. Product roadmap

P0 - Sellable MVP (SMB, low-noise):
- [x] Incident diff and "what changed" summary.
- [x] Maintenance windows and mute.
- [x] Per-monitor alert rules (threshold + cooldown).
- [x] Tagging and filtering.
- [x] Saved views.
- [x] Monitor templates (status page/login/API presets).
- [x] Onboarding wizard with sample monitors and baseline guidance.

P1 - Paid Core+:
- [x] Public status page read-only + cache - foundation+ delivered (public uptime view, cache controls, custom domain support, and stable owner management flow).
- [x] Teams/Discord integrations and routing UI - foundation+ delivered (tenant-scoped channels, routing thresholds, owner configuration UI, and feature coverage in tests).
- [x] Incident collaboration - foundation+ delivered (comments, assignment, snooze, history, and API/web parity with tenant scoping).
- [x] Advanced search and saved search management - foundation+ delivered (multi-filter search, saved views, management flow, and drilldown-ready list UX).
- [x] Public API v1 hardening - foundation+ delivered (versioned endpoints, auth/rate-limit hardening, and docs baseline).
- [~] Billing/plans - foundation+ shipped (metering, API cap enforcement, plan change UI, checkout/webhook foundation, executive trends/forecast in Billing); real provider invoicing and reconciliation workflows still TBD.

P2 - Scale:
- [x] Multi-region scanning and false-positive reduction logic - delivered (regional quorum confirmation for DOWN states, health-based region filtering/strict policy gates, per-region SLA metrics, health snapshot command, alert rules, runbook, and regression coverage).
- [x] Queue priority model and autoscaling strategy - delivered (priority queues, per-tenant dispatch cap, autoscaling planner/metrics, runbooks, guarded auto-apply worker scaler with cooldown/max-step/command-template controls, scheduled execution toggle, and regression coverage).
- [x] Storage tiering lifecycle - delivered (`wids:archive-snapshots`, hot->archive tier metadata, scheduling hooks, metrics, runbook, and automated archive orphan cleanup with grace window/scheduler/JSON audit output for storage cost control).
- [x] Data partition/archive strategy for snapshots - delivered (snapshot archive table `url_snapshot_archives`, partition/archive sync command with safe purge guardrails for baseline/incident/latest rows, scheduler integration, archive growth metrics, and operational runbook).
- [x] Weekly digest and trend analytics (non-SLA reports) - delivered (weekly command + email digest + trend aggregates + Billing executive cap forecast + Ops Center drill artifact trends with click-to-drilldown + persisted weekly report packs JSON/CSV with Billing download flow and retention policy).
- [x] Private probes foundation - delivered (owner API lifecycle, token heartbeat, task claim/lease, signed result ingest, auto-dispatch with fair routing + pin/region hints, residency allowlist + max-active policy by plan tier, managed fleet auto-enforcement command/scheduler for stale+capacity+region controls, and ops metrics/alerts).

P3 - Enterprise:
- [x] SSO (OIDC/SAML) and SCIM - foundation+ delivered (tenant-scoped OIDC with owner UI/discovery/PKCE/identity-linking + full SAML assertion validation with signature/issuer/audience/time checks, encrypted assertion decryption (tenant SP private key), attribute mapping, identity linking, JIT provisioning, and SLO in both SP-initiated + IdP-initiated flows + SCIM v2 lifecycle with tenant bearer auth, ServiceProviderConfig/Users/Groups read+write endpoints, advanced filters (flat AND/OR with field operator support), Bulk operations with limits and bulkId references, group->RBAC role sync, deprovision policy modes, ETag/version preconditions, token CLI, ADR/docs/tests + org-level governance policy pack foundation: tenant policy for SSO enforcement/password allowance/provider allowlist/session max, default provider + domain-based OIDC/SAML routing, login and SSO entrypoint enforcement, and session-governance middleware with owner UI/audit/tests).
- [ ] Multi-instance IdP governance per protocol (more than one OIDC or SAML config per tenant).
- [ ] Advanced RBAC (teams/projects/read-write separation).
- [ ] Full audit export and policy controls.
- [ ] GDPR/compliance workflows.
- [ ] Formal SLA reporting module (PDF + compliance templates + signed/shareable exports).
- [ ] Data isolation controls (tenant-level key strategy).
- [ ] HA/DR production architecture.
- [ ] Managed private probes with residency controls.

## 8. 90-day execution plan (operational)

Milestone M1 (2026-03-15): "Core hardening"
- Finish Observability DoD.
- Finish tenant storage/retention quotas DoD.
- Complete runbook pack (incident, backup restore drill, queue lag).
Done when:
- monitoring alerts are exercised in at least one production-like drill artifact,
- plan-tier storage/retention limits are validated end-to-end (API/UI + cleanup jobs),
- runbook pack is complete and references a real drill result with follow-up actions.

Milestone M2 (2026-04-30): "SMB product completion"
- Deliver monitor templates.
- Deliver onboarding flow.
- Improve status page UX and incident timeline.
Done when:
- new owner can create first monitor from template and trigger first scan in one flow,
- onboarding path and advanced path are both validated in tests and UI review,
- public status page and incident timeline are consistent on desktop/mobile.

Milestone M3 (2026-06-30): "Pro readiness"
- Deliver first additional integrations (Teams/Discord).
- Deliver incident workflow basics (comments/assignment/snooze).
- Prepare API v1 docs and limits.
Done when:
- Teams/Discord routing works per tenant with retry/backoff and severity thresholds,
- incident collaboration workflow is stable in web + API and auditable,
- API v1 documentation is published with enforced limits and regression coverage.

## 9. Operating cadence

- Weekly cadence
  Done when:
  - KPI dashboard and top incidents are reviewed.
  - noisy monitors / false positives are reviewed with follow-up actions.
  - backlog burn and blocker list are updated.

- Bi-weekly cadence
  Done when:
  - security and dependency updates are reviewed.
  - DB performance and storage growth trends are reviewed.

- Monthly cadence
  Done when:
  - restore drill is executed and artifact is stored.
  - plan vs actual roadmap review is completed with decisions.

## 10. Go/No-Go checklist for stable service

Before "ready for production scale":
- [x] Core quality gates in section 6 are all [x] (foundation+ verified in code, docs, and automated tests).
- [x] Runbooks validated by at least one production-like drill (foundation+ evidence: `docs/observability/OPS_GO_NO_GO_2026-02-12.md` + drill artifacts + action closure record).
- [~] Alerting thresholds tuned from real traffic (re-applied 2026-02-13: `target_ready_jobs_per_worker=76`, `dispatch_cap_per_tick=160`; `--window-hours=1` is PASS, `--window-hours=24` still FAIL due historical backlog samples; one clean 24h window still pending).
- [x] Error budget and operational ownership clearly assigned (policy + owner UI readiness in Ops Center, evidence: `docs/observability/ERROR_BUDGET_ONCALL_ESCALATION.md` and `docs/observability/OPS_GO_NO_GO_2026-02-12.md`).

## 11. Main risks and mitigation

- SSRF and scan abuse
  Signal: suspicious target patterns or private-network resolution attempts.
  Mitigation: strict URL safety policy, DNS/IP checks, redirect/body limits, safe defaults.

- Storage explosion
  Signal: accelerating storage growth per tenant/global beyond plan assumptions.
  Mitigation: retention enforcement, per-plan quotas, lifecycle/tiering roadmap.

- Alert fatigue
  Signal: repeated low-value incidents and high suppression ratios.
  Mitigation: baseline, dedupe, cooldown, maintenance windows, incident workflow.

- Operational overload
  Signal: sustained queue lag/error spikes and unresolved operational alerts.
  Mitigation: observability with action-oriented alerts, explicit runbooks, owner accountability.

## 12. Next 3 execution tasks (now)

1. Queue-wait clean-window revalidation (target: 2026-02-13)
   Done when:
   - post-recovery drill run for `--window-hours=24` no longer reflects backlog-distorted queue wait p95,
   - current overrides (`target_ready_jobs_per_worker`, `dispatch_cap_per_tick`) are confirmed or adjusted,
   - decision and owner sign-off are appended to `docs/observability/OPS_GO_NO_GO_2026-02-12.md`.
2. Enterprise auth scale-up: multi-IdP governance (target: 2026-03-31)
   Done when:
   - architecture supports more than one OIDC and more than one SAML config per tenant,
   - deterministic routing/priority and fallback rules are documented,
   - migration and rollback paths are covered in ADR + tests.
3. Enterprise governance hardening pack (target: 2026-04-15)
   Done when:
   - audit export scope + policy controls are implemented for tenant owners,
   - compliance workflow baseline (retention/export/deletion operations) is documented and tested,
   - operational ownership and support flow are integrated with on-call policy.

## 13. Hevman bundle sync (TruMonit + TruCookie)

Monit status (today):
- [x] Ops ownership/escalation readiness is now visible and testable in UI (`/ops`) and backed by env/config keys.
- [~] Queue threshold tuning is operationally applied, with short-window PASS and pending clean 24h confirmation.

Cookie status (today):
- [x] Security baseline is aligned in `TruCookie` (consent telemetry throttle, Stripe webhook fail-closed + throttle, outbound DNS pinning/SSRF guardrails, API token scope/TTL governance).
- [x] Ops governance parity foundation is now aligned (ownership/on-call/escalation readiness panel in Cookie `/ops`, shared `OPS_*` model, and error-budget/on-call policy doc).

Bundle requirements (joint launch readiness):
- [x] Mirror the same ownership/on-call/escalation env model and readiness view in `TruCookie`.
- [ ] Maintain one monthly ops review artifact per product and one combined bundle review note (Monit + Cookie).
