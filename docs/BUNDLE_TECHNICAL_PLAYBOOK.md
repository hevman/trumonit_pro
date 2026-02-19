# Monit + Cookies Bundle (technical playbook)

Cel: utrzymać **SMB-first runtime** (szybkie wdrożenie, niski koszt operacyjny) przy zachowaniu **enterprise foundation** w kodzie.

## 1) Zasada operacyjna

- Runtime domyślnie SMB: bez ciężkich enterprise flow aktywnych „z urzędu”.
- Enterprise funkcje pozostają jako foundation (gotowe do włączenia per tenant/środowisko), ale nie są wymuszane globalnie.

## 2) Kontrola konfiguracji (must-run)

### `TruMonit`

```bash
docker compose exec -T app php artisan wids:profile-check --format=json
```

Komenda waliduje m.in.:
- scope tokenów API (`wids.read`,`wids.write`, bez `*`),
- bezpieczeństwo URL notyfikacji (`dns_strict`),
- ochronę `/ops/metrics` tokenem,
- bezpieczeństwo webhooków billingowych dla aktywnego providera.

### `TruCookie`

```bash
docker compose run --rm backend-cli php artisan cmp:profile-check --format=json
```

Komenda waliduje m.in.:
- scope tokenów plugin API (bez `*`),
- throttle telemetry zgód,
- SSRF guard (DNS pinning + port policy),
- ochronę webhooka Stripe sekretem.

## 3) Bundle smoke checks (po każdej zmianie)

1. `TruMonit`: `docker compose exec -T app php artisan test`
2. `TruCookie`: `docker compose run --rm backend-cli php artisan test`
3. Weryfikacja endpointów:
   - `TruMonit`: `/ops/metrics` bez tokenu => `403/503` (zależnie od konfiguracji)
   - `TruCookie`: `/api/consents` po limicie => `429`
   - `TruCookie`: `/stripe/webhook` bez sekretu przy `require_secret=true` => `503`

## 4) Source of truth dla `.env`

- `TruMonit`: `docs/ENV_SECURITY_CHECKLIST.md`
- `TruCookie`: `docs/ENV_SECURITY_CHECKLIST.md`

## 5) Definition of Done (technicznie)

- oba profile-check mają `summary.errors = 0`,
- oba pełne zestawy testów przechodzą,
- żaden endpoint publiczny o podwyższonym ryzyku nie działa „otwarty” przez brak sekretu/limitu.
