# Integrations (P1)

## Outgoing webhooks (generic)

`TruMonit` can send `incident.created` events to one or more URLs.

Config (see `.env.example`):
- `WIDS_WEBHOOK_ENABLED=true`
- `WIDS_WEBHOOK_URLS=https://your-handler.example.com/webhooks/TruMonit`
- Optional signing: `WIDS_WEBHOOK_SECRET=...`

Each request contains:
- JSON body with `event`, `sent_at`, `incident` (URL, kind, severity, status, details, snapshots, etc.)
- Headers: `X-Saas-Monit-Event`, `X-Saas-Monit-Delivery`
- If secret is set: `X-Saas-Monit-Signature` = HMAC-SHA256 of raw JSON payload

## Jira (minimal mapping)

Recommended approach: build a tiny webhook receiver that calls Jira REST API.

Example mapping:
- Jira summary: `[TruMonit] {{incident.kind}} (sev {{incident.severity}}) - {{incident.display_name || incident.url}}`
- Description:
  - URL
  - Kind / severity / status
  - `details` JSON
  - Link to panel: `APP_URL/incidents/{{incident.id}}`
- Labels: `TruMonit`, `wids`, `{{incident.kind}}`

## ServiceNow (minimal mapping)

Same pattern as Jira: webhook receiver -> ServiceNow Table API (e.g. `incident` table).

Example mapping:
- `short_description`: `[TruMonit] {{incident.summary}}`
- `description`: include URL, kind, severity, panel link, `details`
- `urgency`/`impact`: derive from `incident.severity` (e.g. 80-100 => 1)

## SIEM export (pull)

Endpoint: `GET /api/siem/incidents` (requires API token + `owner` role)
- `format=json|cef|leef`
- `since=...` (filters by `updated_at`)
- `limit=...` (max 1000)

