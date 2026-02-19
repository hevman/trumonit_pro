# SSO SAML (foundation+)

## Zakres

Modul dostarcza tenant-scoped logowanie SAML dla panelu web.

Zakres foundation+:
- konfiguracja SAML per tenant (owner UI),
- endpoint metadata SP,
- endpoint startu AuthnRequest (redirect do IdP),
- endpoint ACS callback z walidacja assertion,
- mapowanie atrybutow i logowanie uzytkownika,
- JIT provisioning (zgodnie z polityka tenant),
- SP-initiated Single Logout (SLO) z callbackiem i walidacja LogoutResponse,
- IdP-initiated Single Logout (LogoutRequest -> LogoutResponse).

## Wlaczenie globalne

W `.env`:

- `WIDS_SSO_SAML_ENABLED=true`
- `WIDS_SSO_SAML_ACS_BINDING="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST"`
- `WIDS_SSO_SAML_NAMEID_FORMAT_DEFAULT="urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress"`
- `WIDS_SSO_SAML_REQUIRE_SIGNED_ASSERTION_DEFAULT=true`
- `WIDS_SSO_SAML_REQUIRE_SIGNED_LOGOUT_REQUEST_DEFAULT=true`
- `WIDS_SSO_SAML_REQUIRE_SIGNED_LOGOUT_RESPONSE_DEFAULT=false`
- `WIDS_SSO_SAML_REQUIRE_DESTINATION=true`
- `WIDS_SSO_SAML_REQUIRE_AUDIENCE=true`
- `WIDS_SSO_SAML_CLOCK_SKEW_SECONDS=120`

## Konfiguracja tenanta

Owner panel:
- `GET /sso/saml`
- `PUT /sso/saml`

Minimalna konfiguracja:
- `idp_entity_id`
- `sso_url`
- `x509_certificate` (gdy podpis assertion jest wymagany)
- `sp_private_key` (gdy IdP wysyla `EncryptedAssertion`)
- `saml_enabled=true`

Mapowanie atrybutow:
- `email_attribute` (default: `email`)
- `name_attribute` (default: `name`)
- `subject_attribute` (default: `NameID`)

Polityki:
- `auto_provision` + `auto_provision_role`
- `domain_allowlist`
- `enforce_signed_assertion` (w metadata)
- `enforce_signed_logout_request` (w metadata)
- `enforce_signed_logout_response` (w metadata)

Identity governance (wspolna polityka tenant):
- konfigurowana w panelu `GET/PUT /sso`,
- obejmuje `enforce_sso_for`, `allow_password_login`, `allowed_sso_providers`, `session_max_minutes`,
- egzekwuje dopuszczone metody logowania i TTL sesji dla tenant.

## Endpointy SAML

- start flow:
  - `GET /auth/saml/redirect?tenant=<workspace-slug>`
- ACS callback:
  - `POST /auth/saml/{tenantSlug}/acs`
  - `GET /auth/saml/{tenantSlug}/acs` (compat)
- SLO callback (SP-initiated):
  - `POST /auth/saml/{tenantSlug}/slo`
  - `GET /auth/saml/{tenantSlug}/slo` (compat)
- metadata:
  - `GET /auth/saml/{tenantSlug}/metadata`

## Walidacja assertion (ACS)

ACS wykonuje walidacje:
- `RelayState` i `InResponseTo`,
- `StatusCode=Success`,
- issuer (Response/Assertion) vs `idp_entity_id`,
- automatyczne odkodowanie `EncryptedAssertion` (gdy wystepuje),
- `Destination` oraz `SubjectConfirmationData Recipient`,
- `NotBefore` i `NotOnOrAfter` (z `clock_skew`),
- audience restriction vs SP entity ID,
- XML Signature (Response lub Assertion), z walidacja referencji po `ID`.

## Mapowanie loginu i JIT provisioning

Kolejnosc:
1. `user_identities` po (`tenant_id`, `provider=saml`, `provider_subject`)
2. fallback po email w tym samym `tenant_id`
3. jesli brak i `auto_provision=true` -> tworzy uzytkownika
4. jesli brak i `auto_provision=false` -> odrzuca logowanie

Po sukcesie:
- aktualizuje/zaklada `user_identities`,
- zapisuje `last_claims` i `last_login_at`,
- zapisuje marker sesji `sso_auth` (provider/nameid/session_index),
- zapisuje audit log (`auth.sso.saml_login`).

## SAML Single Logout (SLO)

Flow:
1. Uzytkownik klika `POST /logout`.
2. Jezeli sesja jest SAML i provider ma `slo_url`, system wysyla `LogoutRequest` do IdP.
3. IdP odsyla `LogoutResponse` do `/auth/saml/{tenantSlug}/slo`.
4. System waliduje `RelayState`, `InResponseTo`, status, issuer, destination i opcjonalnie podpis.
5. Lokalna sesja jest zamykana i czyszczona.

IdP-initiated flow:
1. IdP wysyla `LogoutRequest` do `/auth/saml/{tenantSlug}/slo`.
2. System waliduje issuer, destination, czas, oraz podpis (XML DSig lub HTTP-Redirect query signature).
3. Jesli aktywna sesja SAML pasuje do tenant/nameid, system zamyka sesje lokalna.
4. System odsyla `LogoutResponse` do IdP `slo_url`.

Uwagi:
- SLO jest uruchamiany tylko dla sesji oznaczonych `sso_auth.provider=saml`.
- Przy braku konfiguracji SLO wykonywany jest standardowy lokalny logout.

## Ograniczenia

- brak federacji multi-IdP na tenant i org-level rollout governance,
- brak SLO artifact/back-channel profile.

## Powiazane artefakty

- ADR: `docs/adr/ADR-0003-tenant-sso-saml-foundation-skeleton.md`
- OIDC: `docs/SSO_OIDC.md`
- SCIM: `docs/SCIM.md`
