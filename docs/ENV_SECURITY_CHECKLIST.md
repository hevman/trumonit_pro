# `.env` Security Checklist (dev/prod) - `TruMonit`

Krotka checklista, zeby nie oslabic zabezpieczen przez przypadek.

## 1) API token scopes (Sanctum)

- `WIDS_API_TOKEN_ABILITIES=wids.read,wids.write`
- Nie ustawiaj `*` w srodowisku produkcyjnym.
- Jesli potrzebujesz token read-only, wydawaj go jawnie tylko z `wids.read`.

## 2) Metrics endpoint (`/ops/metrics`)

- `OPS_METRICS_ENABLED=false` (gdy nie uzywasz scrape)
- Jesli wlaczasz:
  - `OPS_METRICS_ENABLED=true`
  - `OPS_METRICS_REQUIRE_TOKEN=true`
  - `OPS_METRICS_TOKEN=<dlugi losowy sekret>`
- Nie uruchamiaj produkcji z `OPS_METRICS_ENABLED=true` i pustym `OPS_METRICS_TOKEN`.

## 3) URL safety dla kanalow notyfikacji

- `WIDS_NOTIFICATION_URL_SAFETY_ENFORCED=true`
- `WIDS_NOTIFICATION_URL_SAFETY_DNS_STRICT=true` (production)
- `WIDS_NOTIFICATION_GLOBAL_FALLBACK_ENABLED=false` (zalecane; ogranicza przypadkowe uzycie globalnych webhookow)
- `WIDS_NOTIFICATIONS_LOCALE=en|pl` (opcjonalnie; puste = locale aplikacji)
- `WIDS_WEBHOOK_HUMAN_READABLE_ENABLED=false` (zalecane domyslnie; wlacz tylko gdy odbiorca potrzebuje lokalizowanego bloku tekstowego)

## 4) Billing webhooki (jesli provider != `manual`)

- `WIDS_BILLING_PAYMENT_PROVIDER=<provider>`
- Ustaw odpowiadajacy sekret:
  - Stripe: `WIDS_BILLING_STRIPE_WEBHOOK_SECRET=...`
  - Paddle: `WIDS_BILLING_PADDLE_WEBHOOK_SECRET=...`
- Bez sekretu podpis webhooka zostanie odrzucony.

## 5) Minimalna kontrola przed deployem

- Sprawdz wartosci:
  - `WIDS_API_TOKEN_ABILITIES`
  - `OPS_METRICS_ENABLED`, `OPS_METRICS_REQUIRE_TOKEN`, `OPS_METRICS_TOKEN`
  - `WIDS_NOTIFICATION_URL_SAFETY_ENFORCED`, `WIDS_NOTIFICATION_URL_SAFETY_DNS_STRICT`, `WIDS_NOTIFICATIONS_LOCALE`, `WIDS_WEBHOOK_HUMAN_READABLE_ENABLED`
  - `WIDS_BILLING_PAYMENT_PROVIDER` + sekret providera
- Zrob smoke test:
  - `/ops/metrics` bez tokenu ma byc `403` albo `503` (zaleznie od konfiguracji),
  - endpointy API maja respektowac `wids.read`/`wids.write`.

## 6) Ops ownership i eskalacja (Go/No-Go)

- Uzupelnij role:
  - `OPS_SERVICE_OWNER`
  - `OPS_PRIMARY_ON_CALL`
  - `OPS_SECONDARY_ON_CALL`
  - `OPS_INCIDENT_COMMANDER`
- Uzupelnij kanaly i cele eskalacji:
  - `OPS_ESCALATION_CHANNEL_SEV1`, `OPS_ESCALATION_CHANNEL_SEV2`, `OPS_ESCALATION_CHANNEL_STATUS`
  - `OPS_ESCALATION_TARGET_CUSTOMER_UPDATES`, `OPS_ESCALATION_TARGET_INTERNAL_UPDATES`
- Weryfikacja UI:
  - `GET /ops` powinien pokazywac sekcje `Operational ownership` bez statusow `MISSING`.
