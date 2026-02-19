# Error Budget, Ownership and Escalation Policy

## Purpose

Define clear operational ownership and escalation for stable service operation.

## Ownership model

- **Service owner (accountable):** Platform owner
- **Primary on-call (24/7):** Platform owner
- **Secondary on-call (backup):** Security/Infra owner
- **Incident commander (Sev-1/Sev-2):** Product/Engineering owner on duty

Single-team mode default:
- if only one owner exists, that owner is primary on-call and incident commander.

## SLO and error budgets (rolling 30 days)

1. **Scan freshness SLO**
   - SLI: `% of active monitors with scan lag <= 900s`
   - Target: `>= 99.0%`
   - Error budget: `1.0%`

2. **Queue latency SLO**
   - SLI: `% of 5m windows with queue_wait_p95 <= 10000ms`
   - Target: `>= 99.0%`
   - Error budget: `1.0%`

3. **Alert delivery SLO**
   - SLI: `% of alert events delivered within 5 minutes (webhook/slack/email)`
   - Target: `>= 99.5%`
   - Error budget: `0.5%`

## Burn-rate policy

- **25% budget used:** tighten thresholds/review noisy monitors in weekly ops review.
- **50% budget used:** freeze non-reliability changes for affected subsystem.
- **75% budget used:** daily incident review until burn normalizes.
- **100% budget used:** release freeze, explicit recovery plan, management update.

## Escalation path

### Severity levels

- **Sev-1:** major customer impact, monitoring/alerting path degraded, widespread missed scans.
- **Sev-2:** partial degradation, elevated lag/wait, delayed but functioning alert path.
- **Sev-3:** localized issue with workaround.

### Response targets

- **Sev-1**
  - acknowledge: <= 5 min
  - engage secondary on-call: <= 10 min
  - first customer/internal status update: <= 30 min
- **Sev-2**
  - acknowledge: <= 15 min
  - secondary escalation: <= 30 min
  - status update cadence: every 60 min
- **Sev-3**
  - acknowledge: <= 4h (business hours)
  - escalation as needed by owner

### Communication path

1. Trigger from monitoring alert/runbook drill.
2. Primary on-call acknowledges and opens incident timeline.
3. If Sev-1/Sev-2, assign incident commander immediately.
4. Track decisions, mitigations, rollback steps, and recovery timestamps.
5. Post-incident review within 2 business days for Sev-1/Sev-2.

## Operational checklist (monthly)

- Run `wids:ops-drill --format=json --save`.
- Review last 30-day SLO and budget burn.
- Confirm on-call rota and backup availability.
- Validate rollback path for tuning changes.
- Document findings and open/close actions with owners and due dates.

