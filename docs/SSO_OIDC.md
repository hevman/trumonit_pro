# SSO OIDC (foundation)

## Zakres

Modul dodaje tenant-scoped logowanie OIDC dla panelu web.

Zakres foundation:
- konfiguracja OIDC per tenant (owner UI),
- logowanie przez authorization code + PKCE,
- mapowanie tozsamosci przez `provider_subject`,
- opcjonalny auto-provisioning uzytkownika.

## Wlaczenie globalne

W `.env`:

- `WIDS_SSO_OIDC_ENABLED=true`
- `WIDS_SSO_OIDC_HTTP_TIMEOUT_SECONDS=8`
- `WIDS_SSO_OIDC_DISCOVERY_CACHE_SECONDS=600`
- `WIDS_SSO_OIDC_DEFAULT_SCOPES="openid profile email"`
- `WIDS_SSO_OIDC_STRICT_ISSUER_HOST_MATCH=true`

## Konfiguracja tenanta

Owner panel:
- `GET /sso`
- `PUT /sso`

Minimalna konfiguracja:
- `issuer_url`
- `client_id`
- `client_secret`
- `oidc_enabled=true`

Po zapisie aplikacja probuje pobrac discovery i uzupelnic endpointy.

## Governance policy pack (tenant)

W panelu owner (`/sso`) tenant ma polityke identity governance:
- `identity_enforce_sso_for` = `off|owner|all`,
- `identity_allow_password_login` (true/false),
- `identity_allow_oidc` i `identity_allow_saml`,
- `identity_default_sso_provider` = `auto|oidc|saml`,
- `identity_sso_domain_routing` (mapowanie domen e-mail -> provider),
- `identity_session_max_minutes` (0..43200, `0` = brak limitu).

Polityka jest zapisywana w `tenants.identity_governance` i egzekwowana przez:
- blokade logowania haslem, gdy tenant to wymusza,
- blokade startu/callback OIDC, gdy provider jest niedozwolony,
- middleware sesyjne (`session.governance`) dla wymuszenia SSO i TTL sesji.

## Login flow

1. Uzytkownik na ekranie logowania podaje slug workspace.
2. Unified start flow (routing polityki):
   - `GET /auth/sso/redirect?tenant=<slug>&email=<opcjonalny-email>`
   - wybiera provider wg `identity_sso_domain_routing` i `identity_default_sso_provider`.
3. OIDC start flow:
   - `GET /auth/oidc/redirect?tenant=<slug>`
4. Callback:
   - `GET /auth/oidc/{tenantSlug}/callback`
5. Aplikacja:
   - waliduje `state`,
   - wymienia `code` na token,
   - pobiera userinfo,
   - mapuje/zaklada uzytkownika.

## Mapowanie uzytkownika

Kolejnosc:
1. `user_identities` po (`tenant_id`, `provider`, `provider_subject`)
2. fallback po email w tym samym `tenant_id`
3. jesli brak i `auto_provision=true` -> tworzy uzytkownika
4. jesli brak i `auto_provision=false` -> odrzuca logowanie

## Ograniczenia (foundation+)

- SAML jest dostepny jako osobny foundation+ (walidacja assertion + mapowanie loginu/JIT/SLO).
- SCIM jest dostepny jako osobny modul provisioning.
- brak wsparcia dla wielu konfiguracji OIDC lub wielu konfiguracji SAML w jednym tenant,
- brak pelnego enterprise lifecycle governance (np. SCIM ETag/zaawansowane filtry).

## Powiazane artefakty

- ADR: `docs/adr/ADR-0001-tenant-sso-oidc-foundation.md`
- Plan: `PLAN.md` (P3)
