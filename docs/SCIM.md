# SCIM v2 (foundation+)

## Status

Foundation+ stage:
- auth and tenant scoping are active,
- discovery/config endpoints are active,
- `Users` read/write lifecycle is active,
- `Groups` read/write lifecycle is active,
- `Bulk` API is active (Users/Groups operations).

## Global enablement

Set in `.env`:

- `WIDS_SCIM_ENABLED=true`
- `WIDS_SCIM_TOKEN_PREFIX=wids_scim`
- `WIDS_SCIM_MAX_LIST_COUNT=200`
- `WIDS_SCIM_BULK_ENABLED=true`
- `WIDS_SCIM_BULK_MAX_OPERATIONS=100`
- `WIDS_SCIM_BULK_MAX_PAYLOAD_BYTES=1048576`

## Provisioning token management

Use CLI:

- show state:
  - `php artisan wids:scim-token <tenant_id> --show`
- enable + rotate token:
  - `php artisan wids:scim-token <tenant_id> --enable --rotate`
- disable:
  - `php artisan wids:scim-token <tenant_id> --disable`
- enable group->role sync:
  - `php artisan wids:scim-token <tenant_id> --group-role-sync=1 --group-role-sync-strict=1 --group-owner-groups="Owners,Platform Admins" --group-member-groups="Members"`

JSON output:
- `php artisan wids:scim-token <tenant_id> --show --format=json`

## API base

- base URL: `/api/scim/v2`
- auth: `Authorization: Bearer <tenant_scim_token>`
- content type: `application/scim+json`

## Endpoints

Core:
- `GET /ServiceProviderConfig`
- `GET /ResourceTypes`
- `GET /Schemas`

Users:
- `GET /Users`
- `GET /Users/{id}`
- `POST /Users`
- `PUT /Users/{id}`
- `PATCH /Users/{id}`
- `DELETE /Users/{id}`

Groups:
- `GET /Groups`
- `GET /Groups/{id}`
- `POST /Groups`
- `PUT /Groups/{id}`
- `PATCH /Groups/{id}`
- `DELETE /Groups/{id}`

Bulk:
- `POST /Bulk`

## ETag and version preconditions

Single-resource responses (`Users/{id}`, `Groups/{id}`, and write responses) include:
- `meta.version` (entity tag),
- `meta.lastModified`,
- HTTP `ETag` header.

Conditional handling:
- `If-None-Match` on `GET /Users/{id}` and `GET /Groups/{id}` returns `304 Not Modified` when matched.
- `If-Match` on `PUT/PATCH/DELETE` for Users/Groups enforces version match and returns `412` on mismatch.
- SCIM Bulk operation `version` field is mapped to `If-Match` for `PUT/PATCH/DELETE` operations.

## Filter support

Users:
- `userName eq "user@example.com"`
- `active eq true and userName co "ops"`
- `name sw "Platform" or active eq false`
- supported user attributes: `userName`, `email`, `emails.value`, `name`, `name.formatted`, `active`, `id`, `externalId`
- supported operators by type:
  - text: `eq`, `ne`, `co`, `sw`, `ew`, `pr`
  - boolean/int: `eq`, `ne`, `pr`

Groups:
- `displayName eq "Ops Team"`
- `externalId eq "group-123"`
- `displayName co "Team" and externalId sw "ext-"`
- `members.value eq "123" or displayName eq "Platform"`
- supported group attributes: `displayName`, `externalId`, `id`, `members.value`
- supported operators:
  - text/int: `eq`, `ne`, `co`, `sw`, `ew`, `pr` (`co/sw/ew` only for text)
  - `members.value`: `eq`, `ne`, `pr`

Pagination:
- `startIndex` (default `1`)
- `count` (default `100`, max `WIDS_SCIM_MAX_LIST_COUNT`)

## Bulk behavior

Supported methods:
- `POST`, `PUT`, `PATCH`, `DELETE`

Supported resources:
- `/Users`
- `/Users/{id}`
- `/Groups`
- `/Groups/{id}`

Additional behavior:
- supports SCIM `bulkId` references (`bulkId:<id>`) in operation paths and payloads,
- obeys `failOnErrors`,
- enforces `maxOperations` and `maxPayloadSize`.

## Provisioning policy flags

Per-tenant provider flags:
- `auto_provision` - must be `true` to allow `POST /Users`,
- `default_role` - role used for newly created users (`member` / `owner`),
- `deprovision_mode` - `disable` (default) or `delete` for `DELETE /Users/{id}`,
- `domain_allowlist` - optional list of allowed email domains.
- `metadata.group_role_sync_enabled` - enables SCIM group to RBAC role sync,
- `metadata.group_role_sync_strict` - when `true`, users not in mapped groups fall back to `default_role`,
- `metadata.group_role_mappings.owner[]` - group display names mapped to `owner`,
- `metadata.group_role_mappings.member[]` - group display names mapped to `member`.

`PATCH /Users` supports:
- `active`,
- `userName`,
- `emails` and `emails[...].value`,
- `name`, `name.formatted`, `name.givenName`, `name.familyName`,
- empty-path object merge for core user fields.

`PATCH /Groups` supports:
- `displayName`,
- `externalId`,
- `members`,
- `members[value eq "<userId>"]` (add/remove patterns),
- empty-path object merge for group fields.

Role sync behavior:
- if user belongs to a mapped `owner` group => role becomes `owner`,
- else if user belongs to a mapped `member` group => role becomes `member`,
- else role remains unchanged (or falls back to `default_role` when strict mode is enabled).

## Current limitations

- no parenthesis nesting in filter expressions (flat `AND/OR` only),
- unsupported SCIM operators like `gt`, `ge`, `lt`, `le` are rejected.

## Related ADR

- `docs/adr/ADR-0002-tenant-scim-foundation.md`
