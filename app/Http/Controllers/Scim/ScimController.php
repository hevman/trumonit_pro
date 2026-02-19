<?php

namespace App\Http\Controllers\Scim;

use App\Http\Controllers\Controller;
use App\Models\TenantScimGroup;
use App\Models\TenantScimProvider;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ScimController extends Controller
{
    public function serviceProviderConfig(Request $request)
    {
        $bulkEnabled = (bool) config('wids.scim.bulk_enabled', true);
        $bulkMaxOperations = max(1, (int) config('wids.scim.bulk_max_operations', 100));
        $bulkMaxPayloadBytes = max(1, (int) config('wids.scim.bulk_max_payload_bytes', 1_048_576));

        return $this->scimResponse([
            'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:ServiceProviderConfig'],
            'documentationUri' => url('/docs/SCIM.md'),
            'patch' => ['supported' => true],
            'bulk' => [
                'supported' => $bulkEnabled,
                'maxOperations' => $bulkEnabled ? $bulkMaxOperations : 0,
                'maxPayloadSize' => $bulkEnabled ? $bulkMaxPayloadBytes : 0,
            ],
            'filter' => ['supported' => true, 'maxResults' => (int) config('wids.scim.max_list_count', 200)],
            'changePassword' => ['supported' => false],
            'sort' => ['supported' => false],
            'etag' => ['supported' => true],
            'authenticationSchemes' => [[
                'name' => 'OAuth Bearer Token',
                'description' => 'Use SCIM provisioning bearer token from workspace SCIM settings.',
                'specUri' => 'http://www.rfc-editor.org/info/rfc6750',
                'type' => 'oauthbearertoken',
                'primary' => true,
            ]],
        ]);
    }

    public function resourceTypes(Request $request)
    {
        return $this->scimResponse([
            'schemas' => ['urn:ietf:params:scim:api:messages:2.0:ListResponse'],
            'totalResults' => 2,
            'startIndex' => 1,
            'itemsPerPage' => 2,
            'Resources' => [
                [
                    'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:ResourceType'],
                    'id' => 'User',
                    'name' => 'User',
                    'endpoint' => '/Users',
                    'description' => 'SCIM User',
                    'schema' => 'urn:ietf:params:scim:schemas:core:2.0:User',
                ],
                [
                    'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:ResourceType'],
                    'id' => 'Group',
                    'name' => 'Group',
                    'endpoint' => '/Groups',
                    'description' => 'SCIM Group',
                    'schema' => 'urn:ietf:params:scim:schemas:core:2.0:Group',
                ],
            ],
        ]);
    }

    public function schemas(Request $request)
    {
        return $this->scimResponse([
            'schemas' => ['urn:ietf:params:scim:api:messages:2.0:ListResponse'],
            'totalResults' => 2,
            'startIndex' => 1,
            'itemsPerPage' => 2,
            'Resources' => [
                [
                    'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:Schema'],
                    'id' => 'urn:ietf:params:scim:schemas:core:2.0:User',
                    'name' => 'User',
                    'description' => 'User Account',
                    'attributes' => [
                        ['name' => 'userName', 'type' => 'string', 'multiValued' => false, 'required' => true, 'mutability' => 'readWrite', 'returned' => 'default', 'uniqueness' => 'none'],
                        ['name' => 'name', 'type' => 'complex', 'multiValued' => false, 'required' => false, 'mutability' => 'readWrite', 'returned' => 'default'],
                        ['name' => 'emails', 'type' => 'complex', 'multiValued' => true, 'required' => false, 'mutability' => 'readWrite', 'returned' => 'default'],
                        ['name' => 'active', 'type' => 'boolean', 'multiValued' => false, 'required' => false, 'mutability' => 'readWrite', 'returned' => 'default'],
                    ],
                ],
                [
                    'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:Schema'],
                    'id' => 'urn:ietf:params:scim:schemas:core:2.0:Group',
                    'name' => 'Group',
                    'description' => 'Group',
                    'attributes' => [
                        ['name' => 'displayName', 'type' => 'string', 'multiValued' => false, 'required' => true, 'mutability' => 'readWrite', 'returned' => 'default', 'uniqueness' => 'none'],
                        ['name' => 'members', 'type' => 'complex', 'multiValued' => true, 'required' => false, 'mutability' => 'readWrite', 'returned' => 'default'],
                        ['name' => 'externalId', 'type' => 'string', 'multiValued' => false, 'required' => false, 'mutability' => 'readWrite', 'returned' => 'default', 'uniqueness' => 'none'],
                    ],
                ],
            ],
        ]);
    }

    public function usersIndex(Request $request)
    {
        $tenantId = (int) $request->attributes->get('scimTenantId', 0);

        if ($tenantId <= 0) {
            return $this->scimError('SCIM tenant context is missing.', 400);
        }

        $startIndex = max(1, (int) $request->query('startIndex', 1));
        $maxCount = max(1, min(1000, (int) config('wids.scim.max_list_count', 200)));
        $count = max(1, min($maxCount, (int) $request->query('count', 100)));
        $offset = $startIndex - 1;

        $query = User::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('id');

        $filter = trim((string) $request->query('filter', ''));
        if ($filter !== '') {
            $filterError = $this->applyUsersFilter($query, $filter);
            if ($filterError !== null) {
                return $this->scimError($filterError, 400);
            }
        }

        $total = (int) (clone $query)->count();
        $users = $query->skip($offset)->take($count)->get();

        return $this->scimResponse([
            'schemas' => ['urn:ietf:params:scim:api:messages:2.0:ListResponse'],
            'totalResults' => $total,
            'startIndex' => $startIndex,
            'itemsPerPage' => $users->count(),
            'Resources' => $users->map(fn (User $user): array => $this->toScimUserResource($request, $user))->values()->all(),
        ]);
    }

    public function usersShow(Request $request, string $id)
    {
        $tenantId = (int) $request->attributes->get('scimTenantId', 0);
        $userId = (int) $id;

        if ($tenantId <= 0 || $userId <= 0) {
            return $this->scimError('Resource not found.', 404);
        }

        $user = User::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $userId)
            ->first();

        if (! $user) {
            return $this->scimError('Resource not found.', 404);
        }

        $etag = $this->userResourceVersion($user);
        $notModified = $this->notModifiedWhenIfNoneMatchSatisfied($request, $etag);

        if ($notModified !== null) {
            return $notModified;
        }

        return $this->scimResourceResponse($this->toScimUserResource($request, $user));
    }

    public function usersStore(Request $request)
    {
        [$tenantId, $provider] = $this->tenantContext($request);

        if ($tenantId <= 0 || ! $provider) {
            return $this->scimError('SCIM tenant context is missing.', 400);
        }

        if (! (bool) $provider->auto_provision) {
            return $this->scimError('SCIM auto-provision is disabled for this workspace.', 403);
        }

        $normalized = $this->normalizeScimUserPayload($request->all(), true);

        if (! $normalized['ok']) {
            return $this->scimError((string) $normalized['detail'], 400);
        }

        $email = (string) $normalized['email'];
        $domainError = $this->domainAllowlistError($provider, $email);

        if ($domainError !== null) {
            return $this->scimError($domainError, 403);
        }

        $emailConflict = $this->emailConflictDetail($email, $tenantId);
        if ($emailConflict !== null) {
            return $this->scimError($emailConflict, 409);
        }

        $user = User::query()->create([
            'tenant_id' => $tenantId,
            'role' => (string) ($provider->default_role === 'owner' ? 'owner' : 'member'),
            'is_active' => true,
            'name' => (string) $normalized['name'],
            'email' => $email,
            'email_verified_at' => now(),
            'password' => Str::random(64),
        ]);

        if (! (bool) $normalized['active']) {
            $this->disableUserAccess($user);
        }

        $this->logScimAudit(
            request: $request,
            tenantId: $tenantId,
            action: 'auth.scim.user_create',
            user: $user,
            data: [
                'provider_id' => (int) $provider->id,
                'active' => (bool) $user->is_active,
                'email' => (string) $user->email,
            ],
        );

        return $this->scimResourceResponse(
            $this->toScimUserResource($request, $user->fresh() ?? $user),
            201,
            [
                'Location' => route('api.scim.users.show', ['id' => (string) $user->id]),
            ],
        );
    }

    public function usersPut(Request $request, string $id)
    {
        [$tenantId, $provider] = $this->tenantContext($request);
        $user = $this->findTenantUserByScimId($tenantId, $id);

        if (! $provider || ! $user) {
            return $this->scimError('Resource not found.', 404);
        }

        $preconditionError = $this->ifMatchPreconditionError($request, $this->userResourceVersion($user));
        if ($preconditionError !== null) {
            return $preconditionError;
        }

        $normalized = $this->normalizeScimUserPayload($request->all(), true);

        if (! $normalized['ok']) {
            return $this->scimError((string) $normalized['detail'], 400);
        }

        $email = (string) $normalized['email'];
        $domainError = $this->domainAllowlistError($provider, $email);

        if ($domainError !== null) {
            return $this->scimError($domainError, 403);
        }

        $emailConflict = $this->emailConflictDetail($email, $tenantId, (int) $user->id);
        if ($emailConflict !== null) {
            return $this->scimError($emailConflict, 409);
        }

        $user->forceFill([
            'email' => $email,
            'name' => (string) $normalized['name'],
        ])->save();

        if ((bool) $normalized['active']) {
            $this->enableUserAccess($user);
        } else {
            $this->disableUserAccess($user);
        }

        $this->logScimAudit(
            request: $request,
            tenantId: $tenantId,
            action: 'auth.scim.user_replace',
            user: $user,
            data: [
                'provider_id' => (int) $provider->id,
                'active' => (bool) ($user->fresh()?->is_active ?? $user->is_active),
                'email' => (string) $email,
            ],
        );

        return $this->scimResourceResponse($this->toScimUserResource($request, $user->fresh() ?? $user));
    }

    public function usersPatch(Request $request, string $id)
    {
        [$tenantId, $provider] = $this->tenantContext($request);
        $user = $this->findTenantUserByScimId($tenantId, $id);

        if (! $provider || ! $user) {
            return $this->scimError('Resource not found.', 404);
        }

        $preconditionError = $this->ifMatchPreconditionError($request, $this->userResourceVersion($user));
        if ($preconditionError !== null) {
            return $preconditionError;
        }

        $operations = $request->input('Operations');

        if (! is_array($operations) || $operations === []) {
            return $this->scimError('PATCH requires non-empty Operations array.', 400);
        }

        $patchDocument = $this->scimPatchDocumentFromUser($user);
        $patchError = $this->applyPatchOperations($patchDocument, $operations);

        if ($patchError !== null) {
            return $this->scimError($patchError, 400);
        }

        $normalized = $this->normalizeScimUserPayload($patchDocument, true);

        if (! $normalized['ok']) {
            return $this->scimError((string) $normalized['detail'], 400);
        }

        $email = (string) $normalized['email'];
        $domainError = $this->domainAllowlistError($provider, $email);

        if ($domainError !== null) {
            return $this->scimError($domainError, 403);
        }

        $emailConflict = $this->emailConflictDetail($email, $tenantId, (int) $user->id);
        if ($emailConflict !== null) {
            return $this->scimError($emailConflict, 409);
        }

        $user->forceFill([
            'email' => $email,
            'name' => (string) $normalized['name'],
        ])->save();

        if ((bool) $normalized['active']) {
            $this->enableUserAccess($user);
        } else {
            $this->disableUserAccess($user);
        }

        $this->logScimAudit(
            request: $request,
            tenantId: $tenantId,
            action: 'auth.scim.user_patch',
            user: $user,
            data: [
                'provider_id' => (int) $provider->id,
                'active' => (bool) ($user->fresh()?->is_active ?? $user->is_active),
                'email' => (string) $email,
            ],
        );

        return $this->scimResourceResponse($this->toScimUserResource($request, $user->fresh() ?? $user));
    }

    public function usersDestroy(Request $request, string $id)
    {
        [$tenantId, $provider] = $this->tenantContext($request);
        $user = $this->findTenantUserByScimId($tenantId, $id);

        if (! $provider || ! $user) {
            return $this->scimError('Resource not found.', 404);
        }

        $preconditionError = $this->ifMatchPreconditionError($request, $this->userResourceVersion($user));
        if ($preconditionError !== null) {
            return $preconditionError;
        }

        $mode = strtolower(trim((string) $provider->deprovision_mode));
        $mode = in_array($mode, ['disable', 'delete'], true) ? $mode : 'disable';
        $email = (string) $user->email;
        $userId = (int) $user->id;

        if ($mode === 'delete') {
            $user->tokens()->delete();
            $user->delete();
        } else {
            $this->disableUserAccess($user);
        }

        $this->logScimAudit(
            request: $request,
            tenantId: $tenantId,
            action: 'auth.scim.user_deprovision',
            user: $user,
            data: [
                'provider_id' => (int) $provider->id,
                'deprovision_mode' => $mode,
                'user_id' => $userId,
                'email' => $email,
            ],
        );

        return response('', 204, [
            'Content-Type' => 'application/scim+json',
        ]);
    }

    public function groupsIndex(Request $request)
    {
        $tenantId = (int) $request->attributes->get('scimTenantId', 0);

        if ($tenantId <= 0) {
            return $this->scimError('SCIM tenant context is missing.', 400);
        }

        $startIndex = max(1, (int) $request->query('startIndex', 1));
        $maxCount = max(1, min(1000, (int) config('wids.scim.max_list_count', 200)));
        $count = max(1, min($maxCount, (int) $request->query('count', 100)));
        $offset = $startIndex - 1;

        $query = TenantScimGroup::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('id');

        $filter = trim((string) $request->query('filter', ''));
        if ($filter !== '') {
            $filterError = $this->applyGroupsFilter($query, $filter);
            if ($filterError !== null) {
                return $this->scimError($filterError, 400);
            }
        }

        $total = (int) (clone $query)->count();
        $groups = $query->skip($offset)->take($count)->get();

        return $this->scimResponse([
            'schemas' => ['urn:ietf:params:scim:api:messages:2.0:ListResponse'],
            'totalResults' => $total,
            'startIndex' => $startIndex,
            'itemsPerPage' => $groups->count(),
            'Resources' => $groups->map(fn (TenantScimGroup $group): array => $this->toScimGroupResource($request, $group))->values()->all(),
        ]);
    }

    public function groupsShow(Request $request, string $id)
    {
        $tenantId = (int) $request->attributes->get('scimTenantId', 0);
        $group = $this->findTenantGroupByScimId($tenantId, $id);

        if (! $group) {
            return $this->scimError('Resource not found.', 404);
        }

        $etag = $this->groupResourceVersion($group);
        $notModified = $this->notModifiedWhenIfNoneMatchSatisfied($request, $etag);

        if ($notModified !== null) {
            return $notModified;
        }

        return $this->scimResourceResponse($this->toScimGroupResource($request, $group));
    }

    public function groupsStore(Request $request)
    {
        [$tenantId, $provider] = $this->tenantContext($request);

        if ($tenantId <= 0 || ! $provider) {
            return $this->scimError('SCIM tenant context is missing.', 400);
        }

        $normalized = $this->normalizeScimGroupPayload($request->all(), true);

        if (! $normalized['ok']) {
            return $this->scimError((string) $normalized['detail'], 400);
        }

        $displayName = (string) $normalized['display_name'];
        $externalId = $normalized['external_id'];
        $memberIds = $normalized['members'];

        if ($this->groupNameExistsInTenant($tenantId, $displayName)) {
            return $this->scimError('A SCIM group with this displayName already exists in the workspace.', 409);
        }

        $memberValidationError = $this->validateGroupMemberIdsForTenant($tenantId, $memberIds);
        if ($memberValidationError !== null) {
            return $this->scimError($memberValidationError, 400);
        }

        $group = TenantScimGroup::query()->create([
            'tenant_id' => $tenantId,
            'display_name' => $displayName,
            'external_id' => $externalId,
            'metadata' => [],
        ]);

        $this->syncGroupMembers($group, $memberIds);
        $this->syncMappedRolesForUsers($provider, $tenantId, $memberIds);

        $this->logScimGroupAudit(
            request: $request,
            tenantId: $tenantId,
            action: 'auth.scim.group_create',
            group: $group,
            data: [
                'provider_id' => (int) $provider->id,
                'display_name' => $displayName,
                'member_count' => count($memberIds),
            ],
        );

        return $this->scimResourceResponse(
            $this->toScimGroupResource($request, $group->fresh() ?? $group),
            201,
            [
                'Location' => route('api.scim.groups.show', ['id' => (string) $group->id]),
            ],
        );
    }

    public function groupsPut(Request $request, string $id)
    {
        [$tenantId, $provider] = $this->tenantContext($request);
        $group = $this->findTenantGroupByScimId($tenantId, $id);

        if (! $provider || ! $group) {
            return $this->scimError('Resource not found.', 404);
        }

        $preconditionError = $this->ifMatchPreconditionError($request, $this->groupResourceVersion($group));
        if ($preconditionError !== null) {
            return $preconditionError;
        }

        $normalized = $this->normalizeScimGroupPayload($request->all(), true);

        if (! $normalized['ok']) {
            return $this->scimError((string) $normalized['detail'], 400);
        }

        $displayName = (string) $normalized['display_name'];
        $externalId = $normalized['external_id'];
        $memberIds = $normalized['members'];

        if ($this->groupNameExistsInTenant($tenantId, $displayName, (int) $group->id)) {
            return $this->scimError('A SCIM group with this displayName already exists in the workspace.', 409);
        }

        $memberValidationError = $this->validateGroupMemberIdsForTenant($tenantId, $memberIds);
        if ($memberValidationError !== null) {
            return $this->scimError($memberValidationError, 400);
        }

        $beforeMemberIds = $group->users()->pluck('users.id')->map(fn (mixed $value): int => (int) $value)->all();

        $group->forceFill([
            'display_name' => $displayName,
            'external_id' => $externalId,
        ])->save();

        $this->syncGroupMembers($group, $memberIds);
        $this->syncMappedRolesForUsers($provider, $tenantId, array_values(array_unique(array_merge($beforeMemberIds, $memberIds))));

        $this->logScimGroupAudit(
            request: $request,
            tenantId: $tenantId,
            action: 'auth.scim.group_replace',
            group: $group,
            data: [
                'provider_id' => (int) $provider->id,
                'display_name' => $displayName,
                'member_count' => count($memberIds),
            ],
        );

        return $this->scimResourceResponse($this->toScimGroupResource($request, $group->fresh() ?? $group));
    }

    public function groupsPatch(Request $request, string $id)
    {
        [$tenantId, $provider] = $this->tenantContext($request);
        $group = $this->findTenantGroupByScimId($tenantId, $id);

        if (! $provider || ! $group) {
            return $this->scimError('Resource not found.', 404);
        }

        $preconditionError = $this->ifMatchPreconditionError($request, $this->groupResourceVersion($group));
        if ($preconditionError !== null) {
            return $preconditionError;
        }

        $operations = $request->input('Operations');
        if (! is_array($operations) || $operations === []) {
            return $this->scimError('PATCH requires non-empty Operations array.', 400);
        }

        $patchDocument = $this->scimPatchDocumentFromGroup($group);
        $patchError = $this->applyGroupPatchOperations($patchDocument, $operations);

        if ($patchError !== null) {
            return $this->scimError($patchError, 400);
        }

        $normalized = $this->normalizeScimGroupPayload($patchDocument, true);

        if (! $normalized['ok']) {
            return $this->scimError((string) $normalized['detail'], 400);
        }

        $displayName = (string) $normalized['display_name'];
        $externalId = $normalized['external_id'];
        $memberIds = $normalized['members'];

        if ($this->groupNameExistsInTenant($tenantId, $displayName, (int) $group->id)) {
            return $this->scimError('A SCIM group with this displayName already exists in the workspace.', 409);
        }

        $memberValidationError = $this->validateGroupMemberIdsForTenant($tenantId, $memberIds);
        if ($memberValidationError !== null) {
            return $this->scimError($memberValidationError, 400);
        }

        $beforeMemberIds = $group->users()->pluck('users.id')->map(fn (mixed $value): int => (int) $value)->all();

        $group->forceFill([
            'display_name' => $displayName,
            'external_id' => $externalId,
        ])->save();

        $this->syncGroupMembers($group, $memberIds);
        $this->syncMappedRolesForUsers($provider, $tenantId, array_values(array_unique(array_merge($beforeMemberIds, $memberIds))));

        $this->logScimGroupAudit(
            request: $request,
            tenantId: $tenantId,
            action: 'auth.scim.group_patch',
            group: $group,
            data: [
                'provider_id' => (int) $provider->id,
                'display_name' => $displayName,
                'member_count' => count($memberIds),
            ],
        );

        return $this->scimResourceResponse($this->toScimGroupResource($request, $group->fresh() ?? $group));
    }

    public function groupsDestroy(Request $request, string $id)
    {
        [$tenantId, $provider] = $this->tenantContext($request);
        $group = $this->findTenantGroupByScimId($tenantId, $id);

        if (! $provider || ! $group) {
            return $this->scimError('Resource not found.', 404);
        }

        $preconditionError = $this->ifMatchPreconditionError($request, $this->groupResourceVersion($group));
        if ($preconditionError !== null) {
            return $preconditionError;
        }

        $groupId = (int) $group->id;
        $displayName = (string) $group->display_name;
        $memberIds = $group->users()->pluck('users.id')->map(fn (mixed $value): int => (int) $value)->all();
        $memberCount = (int) $group->users()->count();

        $group->delete();
        $this->syncMappedRolesForUsers($provider, $tenantId, $memberIds);

        $this->logScimGroupAudit(
            request: $request,
            tenantId: $tenantId,
            action: 'auth.scim.group_delete',
            groupId: $groupId,
            data: [
                'provider_id' => (int) $provider->id,
                'display_name' => $displayName,
                'member_count' => $memberCount,
            ],
        );

        return response('', 204, [
            'Content-Type' => 'application/scim+json',
        ]);
    }

    public function bulk(Request $request)
    {
        [$tenantId, $provider] = $this->tenantContext($request);

        if ($tenantId <= 0 || ! $provider) {
            return $this->scimError('SCIM tenant context is missing.', 400);
        }

        if (! (bool) config('wids.scim.bulk_enabled', true)) {
            return $this->scimError('SCIM bulk operations are disabled.', 501);
        }

        $rawContent = (string) $request->getContent();
        $payloadBytes = strlen($rawContent);
        $maxPayloadBytes = max(1, (int) config('wids.scim.bulk_max_payload_bytes', 1_048_576));

        if ($payloadBytes > $maxPayloadBytes) {
            return $this->scimError('SCIM bulk payload exceeds configured maxPayloadSize.', 413);
        }

        $operations = $request->input('Operations');

        if (! is_array($operations) || $operations === []) {
            return $this->scimError('SCIM bulk request requires non-empty Operations array.', 400);
        }

        $maxOperations = max(1, (int) config('wids.scim.bulk_max_operations', 100));
        if (count($operations) > $maxOperations) {
            return $this->scimError('SCIM bulk request exceeds configured maxOperations.', 413);
        }

        $failOnErrors = max(1, (int) $request->input('failOnErrors', count($operations)));
        $errorCount = 0;
        $bulkIdMap = [];
        $responseOperations = [];

        foreach ($operations as $index => $operation) {
            $operationResult = $this->executeBulkOperation($request, $tenantId, $provider, $operation, $bulkIdMap);
            $responseOperations[] = $operationResult['payload'];

            if ((bool) $operationResult['is_error']) {
                $errorCount++;
            }

            if ($errorCount >= $failOnErrors) {
                break;
            }
        }

        return $this->scimResponse([
            'schemas' => ['urn:ietf:params:scim:api:messages:2.0:BulkResponse'],
            'Operations' => $responseOperations,
        ]);
    }

    private function scimResponse(array $payload, int $statusCode = 200, array $headers = [])
    {
        $headers = array_merge([
            'Content-Type' => 'application/scim+json',
        ], $headers);

        return response()->json($payload, $statusCode, $headers);
    }

    private function scimResourceResponse(array $payload, int $statusCode = 200, array $headers = [])
    {
        $version = trim((string) data_get($payload, 'meta.version', ''));
        if ($version !== '') {
            $headers['ETag'] = $version;
        }

        return $this->scimResponse($payload, $statusCode, $headers);
    }

    private function ifMatchPreconditionError(Request $request, string $currentEtag): ?Response
    {
        $ifMatchRaw = trim((string) $request->header('If-Match', ''));
        if ($ifMatchRaw === '') {
            return null;
        }

        if (! $this->etagHeaderMatches($ifMatchRaw, $currentEtag)) {
            return $this->scimError('Precondition failed. If-Match does not match resource version.', 412);
        }

        return null;
    }

    private function notModifiedWhenIfNoneMatchSatisfied(Request $request, string $currentEtag): ?Response
    {
        $ifNoneMatchRaw = trim((string) $request->header('If-None-Match', ''));
        if ($ifNoneMatchRaw === '') {
            return null;
        }

        if (! $this->etagHeaderMatches($ifNoneMatchRaw, $currentEtag, weakCompare: true)) {
            return null;
        }

        return response('', 304, [
            'Content-Type' => 'application/scim+json',
            'ETag' => $currentEtag,
        ]);
    }

    private function etagHeaderMatches(string $headerValue, string $currentEtag, bool $weakCompare = false): bool
    {
        $currentEtag = trim($currentEtag);
        if ($currentEtag === '') {
            return false;
        }

        $candidates = $this->extractEtagsFromHeader($headerValue);
        if ($candidates === []) {
            return false;
        }

        $normalizedCurrentWeak = $this->normalizeWeakEtag($currentEtag);

        foreach ($candidates as $candidate) {
            $candidate = trim($candidate);
            if ($candidate === '') {
                continue;
            }

            if ($candidate === '*') {
                return true;
            }

            if ($weakCompare) {
                if ($this->normalizeWeakEtag($candidate) === $normalizedCurrentWeak) {
                    return true;
                }

                continue;
            }

            if ($candidate === $currentEtag) {
                return true;
            }

            if ($this->normalizeWeakEtag($candidate) === $normalizedCurrentWeak) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function extractEtagsFromHeader(string $headerValue): array
    {
        $headerValue = trim($headerValue);
        if ($headerValue === '') {
            return [];
        }

        preg_match_all('/W\/"[^"]*"|"[^"]*"|\*/', $headerValue, $matches);
        $values = is_array($matches[0] ?? null) ? $matches[0] : [];

        return array_values(array_filter(
            array_map(
                fn (mixed $value): string => is_string($value) ? trim($value) : '',
                $values
            ),
            fn (string $value): bool => $value !== '',
        ));
    }

    private function normalizeWeakEtag(string $etag): string
    {
        $etag = trim($etag);
        if (str_starts_with($etag, 'W/')) {
            return trim(substr($etag, 2));
        }

        return $etag;
    }

    private function resourceLastModifiedFromModel(Model $model): string
    {
        $candidate = $model->updated_at ?? $model->created_at ?? now();
        $timestamp = $candidate instanceof \DateTimeInterface
            ? $candidate
            : \Carbon\CarbonImmutable::parse((string) $candidate);

        return $timestamp->setTimezone('UTC')->format('Y-m-d\TH:i:s.u\Z');
    }

    private function resourceCreatedFromModel(Model $model): string
    {
        $candidate = $model->created_at ?? $model->updated_at ?? now();
        $timestamp = $candidate instanceof \DateTimeInterface
            ? $candidate
            : \Carbon\CarbonImmutable::parse((string) $candidate);

        return $timestamp->setTimezone('UTC')->format('Y-m-d\TH:i:s.u\Z');
    }

    private function resourceVersionFromPayload(array $payload): string
    {
        $encoded = json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION
        );

        if (! is_string($encoded)) {
            $encoded = serialize($payload);
        }

        return '"'.substr(hash('sha256', $encoded), 0, 40).'"';
    }

    private function userResourceVersion(User $user): string
    {
        return $this->resourceVersionFromPayload([
            'id' => (int) $user->id,
            'tenant_id' => (int) $user->tenant_id,
            'email' => strtolower(trim((string) $user->email)),
            'name' => trim((string) $user->name),
            'is_active' => (bool) ($user->is_active ?? true),
            'updated_at' => $this->resourceLastModifiedFromModel($user),
        ]);
    }

    private function groupResourceVersion(TenantScimGroup $group): string
    {
        $group->loadMissing('users:id');
        $memberIds = $group->users
            ->map(fn (User $user): int => (int) $user->id)
            ->sort()
            ->values()
            ->all();

        return $this->resourceVersionFromPayload([
            'id' => (int) $group->id,
            'tenant_id' => (int) $group->tenant_id,
            'display_name' => trim((string) $group->display_name),
            'external_id' => $group->external_id !== null ? (string) $group->external_id : null,
            'member_ids' => $memberIds,
            'updated_at' => $this->resourceLastModifiedFromModel($group),
        ]);
    }

    /**
     * @return array{
     *   resourceType: string,
     *   created: string,
     *   lastModified: string,
     *   version: string,
     *   location: string
     * }
     */
    private function scimMetaForModel(Model $model, string $resourceType, string $location): array
    {
        $version = match (true) {
            $model instanceof User => $this->userResourceVersion($model),
            $model instanceof TenantScimGroup => $this->groupResourceVersion($model),
            default => $this->resourceVersionFromPayload([
                'table' => (string) $model->getTable(),
                'id' => (string) $model->getKey(),
                'updated_at' => $this->resourceLastModifiedFromModel($model),
            ]),
        };

        return [
            'resourceType' => $resourceType,
            'created' => $this->resourceCreatedFromModel($model),
            'lastModified' => $this->resourceLastModifiedFromModel($model),
            'version' => $version,
            'location' => $location,
        ];
    }

    /**
     * @return array{0: int, 1: TenantScimProvider|null}
     */
    private function tenantContext(Request $request): array
    {
        $tenantId = (int) $request->attributes->get('scimTenantId', 0);
        $provider = $request->attributes->get('scimProvider');

        return [
            $tenantId,
            $provider instanceof TenantScimProvider ? $provider : null,
        ];
    }

    private function findTenantUserByScimId(int $tenantId, string $id): ?User
    {
        $userId = (int) $id;

        if ($tenantId <= 0 || $userId <= 0) {
            return null;
        }

        return User::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $userId)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, detail?: string, email?: string, name?: string, active?: bool}
     */
    private function normalizeScimUserPayload(array $payload, bool $requireUserName): array
    {
        $emailFromUserName = $this->normalizeEmail($payload['userName'] ?? null);

        if ($requireUserName && ! is_string($emailFromUserName)) {
            return [
                'ok' => false,
                'detail' => 'SCIM field "userName" must be a valid email address.',
            ];
        }

        $email = $emailFromUserName ?? $this->extractEmailFromEmails($payload['emails'] ?? null);

        if (! is_string($email)) {
            return [
                'ok' => false,
                'detail' => 'SCIM payload must include a valid email in "userName" or "emails".',
            ];
        }

        $active = true;
        if (array_key_exists('active', $payload)) {
            $normalizedActive = $this->normalizeBoolean($payload['active']);

            if ($normalizedActive === null) {
                return [
                    'ok' => false,
                    'detail' => 'SCIM field "active" must be boolean.',
                ];
            }

            $active = $normalizedActive;
        }

        $namePayload = is_array($payload['name'] ?? null) ? $payload['name'] : [];
        $formattedName = trim((string) ($namePayload['formatted'] ?? ''));
        $givenName = trim((string) ($namePayload['givenName'] ?? ''));
        $familyName = trim((string) ($namePayload['familyName'] ?? ''));
        $displayName = trim((string) ($payload['displayName'] ?? ''));

        $name = $formattedName !== ''
            ? $formattedName
            : trim($givenName.' '.$familyName);

        if ($name === '' && $displayName !== '') {
            $name = $displayName;
        }

        if ($name === '') {
            $name = ucfirst((string) strstr($email, '@', true));
        }

        if ($name === '') {
            $name = $email;
        }

        return [
            'ok' => true,
            'email' => $email,
            'name' => $name,
            'active' => $active,
        ];
    }

    private function normalizeEmail(mixed $value): ?string
    {
        $email = strtolower(trim((string) $value));

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    private function normalizeBoolean(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1 ? true : ($value === 0 ? false : null);
        }

        if (is_string($value)) {
            $normalized = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

            return is_bool($normalized) ? $normalized : null;
        }

        return null;
    }

    private function extractEmailFromEmails(mixed $emails): ?string
    {
        if (is_string($emails)) {
            return $this->normalizeEmail($emails);
        }

        if (! is_array($emails)) {
            return null;
        }

        $fallback = null;

        foreach ($emails as $entry) {
            if (is_array($entry)) {
                $entryEmail = $this->normalizeEmail($entry['value'] ?? null);

                if (! is_string($entryEmail)) {
                    continue;
                }

                $isPrimary = $this->normalizeBoolean($entry['primary'] ?? null) === true;
                if ($isPrimary) {
                    return $entryEmail;
                }

                if ($fallback === null) {
                    $fallback = $entryEmail;
                }

                continue;
            }

            if (is_string($entry)) {
                $entryEmail = $this->normalizeEmail($entry);

                if ($entryEmail !== null && $fallback === null) {
                    $fallback = $entryEmail;
                }
            }
        }

        return $fallback;
    }

    private function domainAllowlistError(TenantScimProvider $provider, string $email): ?string
    {
        $allowlist = is_array($provider->domain_allowlist)
            ? array_values($provider->domain_allowlist)
            : [];

        if ($allowlist === []) {
            return null;
        }

        return $this->isEmailDomainAllowed($email, $allowlist)
            ? null
            : 'Email domain is not allowed for this workspace SCIM provider.';
    }

    /**
     * @param  list<string>  $domainAllowlist
     */
    private function isEmailDomainAllowed(string $email, array $domainAllowlist): bool
    {
        $domain = strtolower(trim((string) strstr($email, '@')));
        $domain = ltrim($domain, '@');

        if ($domain === '') {
            return false;
        }

        foreach ($domainAllowlist as $allowedDomain) {
            if (! is_string($allowedDomain)) {
                continue;
            }

            $allowedDomain = strtolower(trim($allowedDomain));
            $allowedDomain = ltrim($allowedDomain, '@');

            if ($allowedDomain !== '' && $allowedDomain === $domain) {
                return true;
            }
        }

        return false;
    }

    private function emailConflictDetail(string $email, int $tenantId, ?int $exceptUserId = null): ?string
    {
        $existing = User::query()
            ->whereRaw('LOWER(email) = ?', [strtolower($email)])
            ->first();

        if (! $existing) {
            return null;
        }

        if ($exceptUserId !== null && (int) $existing->id === $exceptUserId) {
            return null;
        }

        return (int) $existing->tenant_id === $tenantId
            ? 'A workspace user with this email already exists.'
            : 'This email is already assigned to another workspace.';
    }

    private function disableUserAccess(User $user): void
    {
        $user->forceFill([
            'is_active' => false,
            'password' => Str::random(64),
            'remember_token' => Str::random(60),
        ])->save();

        $user->tokens()->delete();

        if (Schema::hasTable('sessions')) {
            DB::table('sessions')
                ->where('user_id', (int) $user->id)
                ->delete();
        }
    }

    private function enableUserAccess(User $user): void
    {
        if ((bool) $user->is_active) {
            return;
        }

        $user->forceFill([
            'is_active' => true,
        ])->save();
    }

    /**
     * @return array{
     *   userName: string,
     *   emails: list<array{value: string, primary: bool}>,
     *   name: array{formatted: string, givenName: string, familyName: string},
     *   active: bool
     * }
     */
    private function scimPatchDocumentFromUser(User $user): array
    {
        $name = trim((string) $user->name);
        $parts = preg_split('/\s+/', $name) ?: [];

        return [
            'userName' => (string) $user->email,
            'emails' => [[
                'value' => (string) $user->email,
                'primary' => true,
            ]],
            'name' => [
                'formatted' => $name,
                'givenName' => trim((string) ($parts[0] ?? '')),
                'familyName' => trim((string) implode(' ', array_slice($parts, 1))),
            ],
            'active' => (bool) ($user->is_active ?? true),
        ];
    }

    /**
     * @param  array<string, mixed>  $patchDocument
     * @param  array<int|string, mixed>  $operations
     */
    private function applyPatchOperations(array &$patchDocument, array $operations): ?string
    {
        foreach ($operations as $index => $operation) {
            if (! is_array($operation)) {
                return 'PATCH operation at index '.$index.' must be an object.';
            }

            $error = $this->applyPatchOperation($patchDocument, $operation);
            if ($error !== null) {
                return 'PATCH operation at index '.$index.' failed: '.$error;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $patchDocument
     * @param  array<string, mixed>  $operation
     */
    private function applyPatchOperation(array &$patchDocument, array $operation): ?string
    {
        $op = strtolower(trim((string) ($operation['op'] ?? '')));

        if (! in_array($op, ['add', 'replace', 'remove'], true)) {
            return 'Unsupported op. Allowed values: add, replace, remove.';
        }

        $pathRaw = isset($operation['path']) ? trim((string) $operation['path']) : '';
        $path = strtolower(preg_replace('/\s+/', '', $pathRaw));
        $value = $operation['value'] ?? null;

        if ($op === 'remove') {
            return $this->removePatchPathValue($patchDocument, $path);
        }

        if ($path === '') {
            if (! is_array($value)) {
                return 'Operations with empty path require object value.';
            }

            $this->mergePatchDocument($patchDocument, $value);

            return null;
        }

        return $this->setPatchPathValue($patchDocument, $path, $value);
    }

    /**
     * @param  array<string, mixed>  $patchDocument
     * @param  array<string, mixed>  $value
     */
    private function mergePatchDocument(array &$patchDocument, array $value): void
    {
        foreach ($value as $key => $entry) {
            if (! is_string($key)) {
                continue;
            }

            $normalizedKey = strtolower(trim($key));
            $canonicalKey = match ($normalizedKey) {
                'username' => 'userName',
                'emails' => 'emails',
                'name' => 'name',
                'active' => 'active',
                'displayname' => 'displayName',
                default => null,
            };

            if (! is_string($canonicalKey)) {
                continue;
            }

            if (
                is_array($entry)
                && is_array($patchDocument[$canonicalKey] ?? null)
                && $canonicalKey !== 'emails'
            ) {
                $patchDocument[$canonicalKey] = array_replace_recursive($patchDocument[$canonicalKey], $entry);
                continue;
            }

            $patchDocument[$canonicalKey] = $entry;
        }
    }

    /**
     * @param  array<string, mixed>  $patchDocument
     */
    private function setPatchPathValue(array &$patchDocument, string $path, mixed $value): ?string
    {
        if ($path === 'username') {
            $patchDocument['userName'] = is_scalar($value) ? (string) $value : '';

            return null;
        }

        if ($path === 'active') {
            $normalized = $this->normalizeBoolean($value);

            if ($normalized === null) {
                return 'Path "active" requires boolean value.';
            }

            $patchDocument['active'] = $normalized;

            return null;
        }

        if ($path === 'name') {
            if (is_string($value)) {
                $patchDocument['name'] = ['formatted' => $value];

                return null;
            }

            if (! is_array($value)) {
                return 'Path "name" requires object or string value.';
            }

            $patchDocument['name'] = $value;

            return null;
        }

        if ($path === 'name.formatted' || $path === 'name.givenname' || $path === 'name.familyname') {
            $patchDocument['name'] = is_array($patchDocument['name'] ?? null) ? $patchDocument['name'] : [];
            $leaf = match ($path) {
                'name.formatted' => 'formatted',
                'name.givenname' => 'givenName',
                'name.familyname' => 'familyName',
                default => 'formatted',
            };
            $patchDocument['name'][$leaf] = is_scalar($value) ? trim((string) $value) : '';

            return null;
        }

        if ($path === 'emails') {
            if (is_string($value)) {
                $patchDocument['emails'] = [[
                    'value' => $value,
                    'primary' => true,
                ]];

                return null;
            }

            if (! is_array($value)) {
                return 'Path "emails" requires array or string value.';
            }

            $patchDocument['emails'] = $value;

            return null;
        }

        if (str_starts_with($path, 'emails') && str_contains($path, 'value')) {
            $patchDocument['emails'] = [[
                'value' => is_scalar($value) ? (string) $value : '',
                'primary' => true,
            ]];

            return null;
        }

        return 'Unsupported path: '.$path.'.';
    }

    /**
     * @param  array<string, mixed>  $patchDocument
     */
    private function removePatchPathValue(array &$patchDocument, string $path): ?string
    {
        if ($path === '') {
            return 'Remove operation requires path.';
        }

        if ($path === 'active') {
            $patchDocument['active'] = false;

            return null;
        }

        if ($path === 'name') {
            $patchDocument['name'] = [];

            return null;
        }

        if ($path === 'name.formatted' || $path === 'name.givenname' || $path === 'name.familyname') {
            $patchDocument['name'] = is_array($patchDocument['name'] ?? null) ? $patchDocument['name'] : [];
            $leaf = match ($path) {
                'name.formatted' => 'formatted',
                'name.givenname' => 'givenName',
                'name.familyname' => 'familyName',
                default => 'formatted',
            };
            unset($patchDocument['name'][$leaf]);

            return null;
        }

        return 'Unsupported remove path: '.$path.'.';
    }

    private function findTenantGroupByScimId(int $tenantId, string $id): ?TenantScimGroup
    {
        $groupId = (int) $id;

        if ($tenantId <= 0 || $groupId <= 0) {
            return null;
        }

        return TenantScimGroup::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $groupId)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, detail?: string, display_name?: string, external_id?: string|null, members?: list<int>}
     */
    private function normalizeScimGroupPayload(array $payload, bool $requireDisplayName): array
    {
        $displayName = trim((string) ($payload['displayName'] ?? ''));
        if ($requireDisplayName && $displayName === '') {
            return [
                'ok' => false,
                'detail' => 'SCIM field "displayName" is required.',
            ];
        }

        if (array_key_exists('displayName', $payload) && $displayName === '') {
            return [
                'ok' => false,
                'detail' => 'SCIM field "displayName" must be a non-empty string.',
            ];
        }

        $externalIdRaw = $payload['externalId'] ?? null;
        $externalId = null;
        if (is_scalar($externalIdRaw)) {
            $externalId = trim((string) $externalIdRaw);
            $externalId = $externalId !== '' ? $externalId : null;
        }

        $memberNormalization = $this->normalizeGroupMembers($payload['members'] ?? []);
        if (! $memberNormalization['ok']) {
            return [
                'ok' => false,
                'detail' => (string) $memberNormalization['detail'],
            ];
        }

        return [
            'ok' => true,
            'display_name' => $displayName,
            'external_id' => $externalId,
            'members' => $memberNormalization['members'],
        ];
    }

    /**
     * @return array{ok: bool, detail?: string, members: list<int>}
     */
    private function normalizeGroupMembers(mixed $members): array
    {
        if ($members === null) {
            return [
                'ok' => true,
                'members' => [],
            ];
        }

        $entries = is_array($members) ? $members : [$members];
        $memberMap = [];

        foreach ($entries as $entry) {
            $normalizedMemberId = $this->normalizeGroupMemberId($entry);

            if ($normalizedMemberId === null) {
                return [
                    'ok' => false,
                    'detail' => 'SCIM group members must reference user IDs via "value".',
                    'members' => [],
                ];
            }

            $memberMap[$normalizedMemberId] = true;
        }

        return [
            'ok' => true,
            'members' => array_map('intval', array_keys($memberMap)),
        ];
    }

    private function normalizeGroupMemberId(mixed $entry): ?int
    {
        if (is_array($entry)) {
            $entry = $entry['value'] ?? null;
        }

        if (is_int($entry) && $entry > 0) {
            return $entry;
        }

        if (is_string($entry)) {
            $entry = trim($entry);

            if ($entry !== '' && ctype_digit($entry) && (int) $entry > 0) {
                return (int) $entry;
            }
        }

        return null;
    }

    private function groupNameExistsInTenant(int $tenantId, string $displayName, ?int $exceptGroupId = null): bool
    {
        $query = TenantScimGroup::query()
            ->where('tenant_id', $tenantId)
            ->whereRaw('LOWER(display_name) = ?', [strtolower($displayName)]);

        if ($exceptGroupId !== null && $exceptGroupId > 0) {
            $query->where('id', '!=', $exceptGroupId);
        }

        return $query->exists();
    }

    /**
     * @param  list<int>  $memberIds
     */
    private function validateGroupMemberIdsForTenant(int $tenantId, array $memberIds): ?string
    {
        if ($memberIds === []) {
            return null;
        }

        $existingIds = User::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $memberIds)
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        $existingMap = array_fill_keys($existingIds, true);

        foreach ($memberIds as $memberId) {
            if (! isset($existingMap[(int) $memberId])) {
                return 'SCIM group member user ID "'.$memberId.'" was not found in this workspace.';
            }
        }

        return null;
    }

    /**
     * @param  list<int>  $memberIds
     */
    private function syncGroupMembers(TenantScimGroup $group, array $memberIds): void
    {
        $group->users()->syncWithPivotValues($memberIds, [
            'tenant_id' => (int) $group->tenant_id,
        ]);
    }

    /**
     * @param  list<int>  $userIds
     */
    private function syncMappedRolesForUsers(TenantScimProvider $provider, int $tenantId, array $userIds): void
    {
        $userIds = array_values(array_unique(array_filter(
            array_map(fn (mixed $value): int => (int) $value, $userIds),
            fn (int $value): bool => $value > 0
        )));

        if ($tenantId <= 0 || $userIds === []) {
            return;
        }

        $groupRoleMap = $this->groupRoleMapByDisplayName($provider);
        if ($groupRoleMap === []) {
            return;
        }

        $strict = $this->providerScimMetadataBoolean($provider, 'group_role_sync_strict', false);
        $strictFallbackRole = (string) ($provider->default_role === 'owner' ? 'owner' : 'member');

        $memberships = DB::table('tenant_scim_group_memberships as membership')
            ->join('tenant_scim_groups as groups', 'groups.id', '=', 'membership.tenant_scim_group_id')
            ->where('membership.tenant_id', $tenantId)
            ->whereIn('membership.user_id', $userIds)
            ->get([
                'membership.user_id as user_id',
                'groups.display_name as display_name',
            ]);

        $targetRoles = [];

        foreach ($memberships as $membership) {
            $userId = (int) ($membership->user_id ?? 0);
            $groupDisplayName = strtolower(trim((string) ($membership->display_name ?? '')));
            $mappedRole = $groupRoleMap[$groupDisplayName] ?? null;

            if ($userId <= 0 || ! is_string($mappedRole)) {
                continue;
            }

            if (! isset($targetRoles[$userId]) || $mappedRole === 'owner') {
                $targetRoles[$userId] = $mappedRole;
            }
        }

        $users = User::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $userIds)
            ->get();

        foreach ($users as $user) {
            $userId = (int) $user->id;
            $targetRole = $targetRoles[$userId] ?? ($strict ? $strictFallbackRole : null);

            if (! is_string($targetRole) || $targetRole === '' || $user->role === $targetRole) {
                continue;
            }

            $user->forceFill([
                'role' => $targetRole,
            ])->save();
        }
    }

    /**
     * @return array<string, 'owner'|'member'>
     */
    private function groupRoleMapByDisplayName(TenantScimProvider $provider): array
    {
        if (! $this->providerScimMetadataBoolean($provider, 'group_role_sync_enabled', false)) {
            return [];
        }

        $metadata = is_array($provider->metadata) ? $provider->metadata : [];
        $map = [];

        $groupRoleMappings = is_array($metadata['group_role_mappings'] ?? null)
            ? $metadata['group_role_mappings']
            : [];

        foreach (['member', 'owner'] as $role) {
            $groupNames = $groupRoleMappings[$role] ?? [];
            $groupNames = is_array($groupNames) ? $groupNames : preg_split('/[\r\n,;]+/', (string) $groupNames);
            $groupNames = is_array($groupNames) ? $groupNames : [];

            foreach ($groupNames as $groupName) {
                $normalizedGroupName = strtolower(trim((string) $groupName));
                if ($normalizedGroupName === '') {
                    continue;
                }

                if (! isset($map[$normalizedGroupName]) || $role === 'owner') {
                    $map[$normalizedGroupName] = $role;
                }
            }
        }

        return $map;
    }

    private function providerScimMetadataBoolean(TenantScimProvider $provider, string $key, bool $default): bool
    {
        $metadata = is_array($provider->metadata) ? $provider->metadata : [];

        if (! array_key_exists($key, $metadata)) {
            return $default;
        }

        $value = filter_var($metadata[$key], FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

        return is_bool($value) ? $value : $default;
    }

    private function applyUsersFilter(Builder $query, string $filter): ?string
    {
        $parsed = $this->parseScimFilterExpression($filter);
        if (! $parsed['ok']) {
            return (string) ($parsed['detail'] ?? 'Invalid SCIM Users filter.');
        }

        $clauses = is_array($parsed['clauses'] ?? null) ? $parsed['clauses'] : [];
        $connectors = is_array($parsed['connectors'] ?? null) ? $parsed['connectors'] : [];
        $error = null;

        $query->where(function (Builder $compound) use (&$error, $clauses, $connectors): void {
            foreach ($clauses as $index => $clause) {
                $connector = $index === 0
                    ? 'and'
                    : (string) ($connectors[$index - 1] ?? 'and');
                $connector = strtolower(trim($connector)) === 'or' ? 'or' : 'and';
                $method = $connector === 'or' ? 'orWhere' : 'where';

                $compound->{$method}(function (Builder $subQuery) use (&$error, $clause): void {
                    if ($error !== null) {
                        return;
                    }

                    $error = $this->applyUsersFilterClause($subQuery, $clause);
                });

                if ($error !== null) {
                    return;
                }
            }
        });

        return $error;
    }

    private function applyGroupsFilter(Builder $query, string $filter): ?string
    {
        $parsed = $this->parseScimFilterExpression($filter);
        if (! $parsed['ok']) {
            return (string) ($parsed['detail'] ?? 'Invalid SCIM Groups filter.');
        }

        $clauses = is_array($parsed['clauses'] ?? null) ? $parsed['clauses'] : [];
        $connectors = is_array($parsed['connectors'] ?? null) ? $parsed['connectors'] : [];
        $error = null;

        $query->where(function (Builder $compound) use (&$error, $clauses, $connectors): void {
            foreach ($clauses as $index => $clause) {
                $connector = $index === 0
                    ? 'and'
                    : (string) ($connectors[$index - 1] ?? 'and');
                $connector = strtolower(trim($connector)) === 'or' ? 'or' : 'and';
                $method = $connector === 'or' ? 'orWhere' : 'where';

                $compound->{$method}(function (Builder $subQuery) use (&$error, $clause): void {
                    if ($error !== null) {
                        return;
                    }

                    $error = $this->applyGroupsFilterClause($subQuery, $clause);
                });

                if ($error !== null) {
                    return;
                }
            }
        });

        return $error;
    }

    /**
     * @param  array{field: string, operator: string, value: mixed}  $clause
     */
    private function applyUsersFilterClause(Builder $query, array $clause): ?string
    {
        $field = $this->normalizeFilterField((string) ($clause['field'] ?? ''));
        $operator = strtolower(trim((string) ($clause['operator'] ?? '')));
        $value = $clause['value'] ?? null;

        return match ($field) {
            'username', 'email', 'emails.value' => $this->applyTextFilterClause($query, 'email', $operator, $value),
            'name', 'name.formatted' => $this->applyTextFilterClause($query, 'name', $operator, $value),
            'active' => $this->applyBooleanFilterClause($query, 'is_active', $operator, $value),
            'id', 'externalid' => $this->applyIntegerFilterClause($query, 'id', $operator, $value),
            default => 'Unsupported Users filter attribute: '.$field.'.',
        };
    }

    /**
     * @param  array{field: string, operator: string, value: mixed}  $clause
     */
    private function applyGroupsFilterClause(Builder $query, array $clause): ?string
    {
        $field = $this->normalizeFilterField((string) ($clause['field'] ?? ''));
        $operator = strtolower(trim((string) ($clause['operator'] ?? '')));
        $value = $clause['value'] ?? null;

        if ($field === 'members.value') {
            if ($operator === 'pr') {
                $query->whereHas('users');

                return null;
            }

            $memberId = $this->valueToInteger($value);
            if ($memberId === null) {
                return 'Groups filter "members.value" expects numeric user id.';
            }

            if ($operator === 'eq') {
                $query->whereHas('users', fn (Builder $memberQuery) => $memberQuery->where('users.id', $memberId));

                return null;
            }

            if ($operator === 'ne') {
                $query->whereDoesntHave('users', fn (Builder $memberQuery) => $memberQuery->where('users.id', $memberId));

                return null;
            }

            return 'Groups filter "members.value" supports only eq/ne/pr operators.';
        }

        return match ($field) {
            'displayname' => $this->applyTextFilterClause($query, 'display_name', $operator, $value),
            'externalid' => $this->applyTextFilterClause($query, 'external_id', $operator, $value),
            'id' => $this->applyIntegerFilterClause($query, 'id', $operator, $value),
            default => 'Unsupported Groups filter attribute: '.$field.'.',
        };
    }

    private function applyTextFilterClause(Builder $query, string $column, string $operator, mixed $value): ?string
    {
        $operator = strtolower(trim($operator));
        if ($operator === 'pr') {
            $query->whereNotNull($column)->where($column, '<>', '');

            return null;
        }

        if (! in_array($operator, ['eq', 'ne', 'co', 'sw', 'ew'], true)) {
            return 'Unsupported text filter operator: '.$operator.'.';
        }

        $needle = trim((string) $value);
        if ($needle === '') {
            return 'Text filter value must not be empty.';
        }

        $needle = strtolower($needle);

        if ($operator === 'eq') {
            $query->whereRaw('LOWER('.$column.') = ?', [$needle]);

            return null;
        }

        if ($operator === 'ne') {
            $query->where(function (Builder $innerQuery) use ($column, $needle): void {
                $innerQuery
                    ->whereNull($column)
                    ->orWhereRaw('LOWER('.$column.') <> ?', [$needle]);
            });

            return null;
        }

        $pattern = match ($operator) {
            'co' => '%'.$needle.'%',
            'sw' => $needle.'%',
            'ew' => '%'.$needle,
            default => null,
        };

        if (! is_string($pattern)) {
            return 'Unsupported text filter operator: '.$operator.'.';
        }

        $query->whereRaw('LOWER('.$column.') LIKE ?', [$pattern]);

        return null;
    }

    private function applyBooleanFilterClause(Builder $query, string $column, string $operator, mixed $value): ?string
    {
        $operator = strtolower(trim($operator));
        if ($operator === 'pr') {
            $query->whereNotNull($column);

            return null;
        }

        if (! in_array($operator, ['eq', 'ne'], true)) {
            return 'Boolean filter supports only eq/ne/pr operators.';
        }

        $normalized = $this->normalizeBoolean($value);
        if (! is_bool($normalized)) {
            return 'Boolean filter value must be true or false.';
        }

        if ($operator === 'eq') {
            $query->where($column, $normalized);

            return null;
        }

        $query->where($column, '<>', $normalized);

        return null;
    }

    private function applyIntegerFilterClause(Builder $query, string $column, string $operator, mixed $value): ?string
    {
        $operator = strtolower(trim($operator));
        if ($operator === 'pr') {
            $query->where($column, '>', 0);

            return null;
        }

        if (! in_array($operator, ['eq', 'ne'], true)) {
            return 'Numeric filter supports only eq/ne/pr operators.';
        }

        $number = $this->valueToInteger($value);
        if ($number === null) {
            return 'Numeric filter value must be an integer.';
        }

        if ($operator === 'eq') {
            $query->where($column, $number);

            return null;
        }

        $query->where($column, '<>', $number);

        return null;
    }

    private function normalizeFilterField(string $field): string
    {
        return strtolower(trim(str_replace(' ', '', $field)));
    }

    /**
     * @return array{
     *   ok: bool,
     *   clauses?: list<array{field: string, operator: string, value: mixed}>,
     *   connectors?: list<string>,
     *   detail?: string
     * }
     */
    private function parseScimFilterExpression(string $filter): array
    {
        $filter = trim($filter);
        if ($filter === '') {
            return [
                'ok' => false,
                'detail' => 'SCIM filter must not be empty.',
            ];
        }

        $parts = preg_split('/\s+(and|or)\s+/i', $filter, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        $parts = is_array($parts) ? array_values($parts) : [];

        if ($parts === [] || (count($parts) % 2) === 0) {
            return [
                'ok' => false,
                'detail' => 'SCIM filter has invalid logical expression.',
            ];
        }

        $clauses = [];
        $connectors = [];

        foreach ($parts as $index => $part) {
            if (($index % 2) === 1) {
                $connector = strtolower(trim((string) $part));
                if (! in_array($connector, ['and', 'or'], true)) {
                    return [
                        'ok' => false,
                        'detail' => 'SCIM filter supports only AND/OR connectors.',
                    ];
                }

                $connectors[] = $connector;
                continue;
            }

            $parsedClause = $this->parseScimFilterClause((string) $part);
            if (! $parsedClause['ok']) {
                return [
                    'ok' => false,
                    'detail' => (string) ($parsedClause['detail'] ?? 'Invalid SCIM filter clause.'),
                ];
            }

            $clauses[] = [
                'field' => (string) $parsedClause['field'],
                'operator' => (string) $parsedClause['operator'],
                'value' => $parsedClause['value'] ?? null,
            ];
        }

        return [
            'ok' => true,
            'clauses' => $clauses,
            'connectors' => $connectors,
        ];
    }

    /**
     * @return array{
     *   ok: bool,
     *   field?: string,
     *   operator?: string,
     *   value?: mixed,
     *   detail?: string
     * }
     */
    private function parseScimFilterClause(string $clause): array
    {
        $clause = trim($clause);
        if ($clause === '') {
            return [
                'ok' => false,
                'detail' => 'SCIM filter clause must not be empty.',
            ];
        }

        if (preg_match('/^([A-Za-z][A-Za-z0-9._:-]*)\s+pr$/i', $clause, $matches) === 1) {
            return [
                'ok' => true,
                'field' => $this->normalizeFilterField((string) ($matches[1] ?? '')),
                'operator' => 'pr',
                'value' => null,
            ];
        }

        if (preg_match('/^([A-Za-z][A-Za-z0-9._:-]*)\s+(eq|ne|co|sw|ew)\s+(.+)$/i', $clause, $matches) !== 1) {
            return [
                'ok' => false,
                'detail' => 'Unsupported SCIM filter clause: '.$clause,
            ];
        }

        $field = $this->normalizeFilterField((string) ($matches[1] ?? ''));
        $operator = strtolower(trim((string) ($matches[2] ?? '')));
        $rawValue = trim((string) ($matches[3] ?? ''));

        $parsedValue = $this->parseScimFilterValue($rawValue);
        if (! $parsedValue['ok']) {
            return [
                'ok' => false,
                'detail' => (string) ($parsedValue['detail'] ?? 'Invalid SCIM filter value.'),
            ];
        }

        return [
            'ok' => true,
            'field' => $field,
            'operator' => $operator,
            'value' => $parsedValue['value'] ?? null,
        ];
    }

    /**
     * @return array{ok: bool, value?: mixed, detail?: string}
     */
    private function parseScimFilterValue(string $rawValue): array
    {
        $rawValue = trim($rawValue);
        if ($rawValue === '') {
            return [
                'ok' => false,
                'detail' => 'SCIM filter value must not be empty.',
            ];
        }

        if (preg_match('/^"((?:[^"\\\\]|\\\\.)*)"$/s', $rawValue, $matches) === 1) {
            return [
                'ok' => true,
                'value' => stripcslashes((string) ($matches[1] ?? '')),
            ];
        }

        $lower = strtolower($rawValue);
        if (in_array($lower, ['true', 'false'], true)) {
            return [
                'ok' => true,
                'value' => $lower === 'true',
            ];
        }

        if (preg_match('/^-?\d+$/', $rawValue) === 1) {
            return [
                'ok' => true,
                'value' => (int) $rawValue,
            ];
        }

        if (preg_match('/\s/', $rawValue) === 1) {
            return [
                'ok' => false,
                'detail' => 'SCIM filter string values with spaces must be quoted.',
            ];
        }

        return [
            'ok' => true,
            'value' => $rawValue,
        ];
    }

    private function valueToInteger(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^-?\d+$/', trim($value)) === 1) {
            return (int) trim($value);
        }

        return null;
    }

    /**
     * @return array{
     *   displayName: string,
     *   externalId: ?string,
     *   members: list<array{value: string}>
     * }
     */
    private function scimPatchDocumentFromGroup(TenantScimGroup $group): array
    {
        $group->loadMissing('users:id');

        return [
            'displayName' => (string) $group->display_name,
            'externalId' => is_string($group->external_id) && trim($group->external_id) !== ''
                ? trim((string) $group->external_id)
                : null,
            'members' => $group->users
                ->map(fn (User $user): array => ['value' => (string) $user->id])
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $patchDocument
     * @param  array<int|string, mixed>  $operations
     */
    private function applyGroupPatchOperations(array &$patchDocument, array $operations): ?string
    {
        foreach ($operations as $index => $operation) {
            if (! is_array($operation)) {
                return 'PATCH operation at index '.$index.' must be an object.';
            }

            $error = $this->applyGroupPatchOperation($patchDocument, $operation);
            if ($error !== null) {
                return 'PATCH operation at index '.$index.' failed: '.$error;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $patchDocument
     * @param  array<string, mixed>  $operation
     */
    private function applyGroupPatchOperation(array &$patchDocument, array $operation): ?string
    {
        $op = strtolower(trim((string) ($operation['op'] ?? '')));

        if (! in_array($op, ['add', 'replace', 'remove'], true)) {
            return 'Unsupported op. Allowed values: add, replace, remove.';
        }

        $pathRaw = isset($operation['path']) ? trim((string) $operation['path']) : '';
        $path = strtolower((string) preg_replace('/\s+/', '', $pathRaw));
        $value = $operation['value'] ?? null;

        if ($op === 'remove') {
            return $this->removeGroupPatchPathValue($patchDocument, $path);
        }

        if ($path === '') {
            if (! is_array($value)) {
                return 'Operations with empty path require object value.';
            }

            return $this->mergeGroupPatchDocument($patchDocument, $value, $op);
        }

        return $this->setGroupPatchPathValue($patchDocument, $path, $value, $op);
    }

    /**
     * @param  array<string, mixed>  $patchDocument
     * @param  array<string, mixed>  $value
     */
    private function mergeGroupPatchDocument(array &$patchDocument, array $value, string $operation): ?string
    {
        foreach ($value as $key => $entry) {
            if (! is_string($key)) {
                continue;
            }

            $path = strtolower(trim($key));
            $path = str_replace(' ', '', $path);

            $error = $this->setGroupPatchPathValue($patchDocument, $path, $entry, $operation);
            if ($error !== null) {
                return $error;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $patchDocument
     */
    private function setGroupPatchPathValue(array &$patchDocument, string $path, mixed $value, string $operation): ?string
    {
        if ($path === 'displayname') {
            $patchDocument['displayName'] = is_scalar($value) ? trim((string) $value) : '';

            return null;
        }

        if ($path === 'externalid') {
            if ($value === null) {
                $patchDocument['externalId'] = null;

                return null;
            }

            $patchDocument['externalId'] = is_scalar($value) ? trim((string) $value) : null;

            return null;
        }

        if ($path === 'members') {
            $normalizedMembers = $this->normalizeGroupMembers($value);
            if (! $normalizedMembers['ok']) {
                return (string) $normalizedMembers['detail'];
            }

            $existingMembers = $this->normalizeGroupMembers($patchDocument['members'] ?? []);
            if (! $existingMembers['ok']) {
                return (string) $existingMembers['detail'];
            }

            $memberIds = $operation === 'add'
                ? array_values(array_unique(array_merge($existingMembers['members'], $normalizedMembers['members'])))
                : $normalizedMembers['members'];

            $patchDocument['members'] = array_map(
                fn (int $memberId): array => ['value' => (string) $memberId],
                $memberIds
            );

            return null;
        }

        if (preg_match('/^members\[valueeq"([^"]+)"\]$/', $path, $matches) === 1) {
            $memberIdFromPath = $this->normalizeGroupMemberId((string) ($matches[1] ?? ''));
            if ($memberIdFromPath === null) {
                return 'Members path must include numeric user id in value eq clause.';
            }

            $existingMembers = $this->normalizeGroupMembers($patchDocument['members'] ?? []);
            if (! $existingMembers['ok']) {
                return (string) $existingMembers['detail'];
            }

            $memberIds = $existingMembers['members'];

            if ($operation === 'add') {
                $memberIds[] = $memberIdFromPath;
            }

            if ($operation === 'replace') {
                $normalizedMembers = $this->normalizeGroupMembers($value);
                if (! $normalizedMembers['ok']) {
                    return (string) $normalizedMembers['detail'];
                }

                $memberIds = $normalizedMembers['members'];
            }

            $memberIds = array_values(array_unique(array_map('intval', $memberIds)));
            $patchDocument['members'] = array_map(
                fn (int $memberId): array => ['value' => (string) $memberId],
                $memberIds
            );

            return null;
        }

        return 'Unsupported path: '.$path.'.';
    }

    /**
     * @param  array<string, mixed>  $patchDocument
     */
    private function removeGroupPatchPathValue(array &$patchDocument, string $path): ?string
    {
        if ($path === '') {
            return 'Remove operation requires path.';
        }

        if ($path === 'displayname') {
            return 'Path "displayName" cannot be removed.';
        }

        if ($path === 'externalid') {
            $patchDocument['externalId'] = null;

            return null;
        }

        if ($path === 'members') {
            $patchDocument['members'] = [];

            return null;
        }

        if (preg_match('/^members\[valueeq"([^"]+)"\]$/', $path, $matches) === 1) {
            $memberIdToRemove = $this->normalizeGroupMemberId((string) ($matches[1] ?? ''));
            if ($memberIdToRemove === null) {
                return 'Members path must include numeric user id in value eq clause.';
            }

            $normalizedMembers = $this->normalizeGroupMembers($patchDocument['members'] ?? []);
            if (! $normalizedMembers['ok']) {
                return (string) $normalizedMembers['detail'];
            }

            $memberIds = array_values(array_filter(
                $normalizedMembers['members'],
                fn (int $memberId): bool => $memberId !== $memberIdToRemove
            ));

            $patchDocument['members'] = array_map(
                fn (int $memberId): array => ['value' => (string) $memberId],
                $memberIds
            );

            return null;
        }

        return 'Unsupported remove path: '.$path.'.';
    }

    /**
     * @param  array<string, string>  $bulkIdMap
     * @return array{payload: array<string, mixed>, is_error: bool}
     */
    private function executeBulkOperation(
        Request $request,
        int $tenantId,
        TenantScimProvider $provider,
        mixed $operation,
        array &$bulkIdMap,
    ): array {
        if (! is_array($operation)) {
            return [
                'payload' => $this->bulkErrorPayload('UNKNOWN', null, 400, 'Bulk operation must be an object.'),
                'is_error' => true,
            ];
        }

        $method = strtoupper(trim((string) ($operation['method'] ?? '')));
        $bulkId = trim((string) ($operation['bulkId'] ?? ''));
        $pathRaw = trim((string) ($operation['path'] ?? ''));

        if (! in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return [
                'payload' => $this->bulkErrorPayload($method !== '' ? $method : 'UNKNOWN', $bulkId !== '' ? $bulkId : null, 400, 'Unsupported bulk method.'),
                'is_error' => true,
            ];
        }

        $parsedPath = $this->parseBulkPath($pathRaw, $bulkIdMap);
        if (! $parsedPath['ok']) {
            return [
                'payload' => $this->bulkErrorPayload($method, $bulkId !== '' ? $bulkId : null, 400, (string) $parsedPath['detail']),
                'is_error' => true,
            ];
        }

        if ($method === 'POST' && $parsedPath['resource_id'] !== null) {
            return [
                'payload' => $this->bulkErrorPayload($method, $bulkId !== '' ? $bulkId : null, 400, 'POST bulk operation must target collection endpoint.'),
                'is_error' => true,
            ];
        }

        if (in_array($method, ['PUT', 'PATCH', 'DELETE'], true) && $parsedPath['resource_id'] === null) {
            return [
                'payload' => $this->bulkErrorPayload($method, $bulkId !== '' ? $bulkId : null, 400, 'Bulk operation requires resource id in path.'),
                'is_error' => true,
            ];
        }

        $data = $operation['data'] ?? [];
        if (in_array($method, ['POST', 'PUT', 'PATCH'], true) && ! is_array($data)) {
            return [
                'payload' => $this->bulkErrorPayload($method, $bulkId !== '' ? $bulkId : null, 400, 'Bulk operation data must be an object.'),
                'is_error' => true,
            ];
        }

        $resolvedData = is_array($data)
            ? $this->resolveBulkIdReferences($data, $bulkIdMap)
            : [];
        $operationVersion = trim((string) ($operation['version'] ?? ''));
        $ifMatch = in_array($method, ['PUT', 'PATCH', 'DELETE'], true) && $operationVersion !== ''
            ? $operationVersion
            : null;

        try {
            $subRequest = $this->createScimSubRequest(
                request: $request,
                tenantId: $tenantId,
                provider: $provider,
                method: $method,
                data: $resolvedData,
                ifMatch: $ifMatch,
            );
        } catch (\Throwable $e) {
            return [
                'payload' => $this->bulkErrorPayload($method, $bulkId !== '' ? $bulkId : null, 400, 'Bulk operation payload encoding failed.'),
                'is_error' => true,
            ];
        }

        $resource = (string) $parsedPath['resource'];
        $resourceId = $parsedPath['resource_id'];

        $response = null;

        if ($resource === 'Users') {
            $response = match ($method) {
                'POST' => $this->usersStore($subRequest),
                'PUT' => $this->usersPut($subRequest, (string) $resourceId),
                'PATCH' => $this->usersPatch($subRequest, (string) $resourceId),
                'DELETE' => $this->usersDestroy($subRequest, (string) $resourceId),
                default => $this->scimError('Unsupported bulk operation.', 400),
            };
        }

        if ($resource === 'Groups') {
            $response = match ($method) {
                'POST' => $this->groupsStore($subRequest),
                'PUT' => $this->groupsPut($subRequest, (string) $resourceId),
                'PATCH' => $this->groupsPatch($subRequest, (string) $resourceId),
                'DELETE' => $this->groupsDestroy($subRequest, (string) $resourceId),
                default => $this->scimError('Unsupported bulk operation.', 400),
            };
        }

        if (! $response instanceof Response) {
            return [
                'payload' => $this->bulkErrorPayload($method, $bulkId !== '' ? $bulkId : null, 400, 'Unsupported bulk resource path.'),
                'is_error' => true,
            ];
        }

        $statusCode = (int) $response->getStatusCode();
        $isError = $statusCode >= 400;
        $jsonPayload = $this->responseJsonPayload($response);
        $location = trim((string) $response->headers->get('Location', ''));
        $etag = trim((string) $response->headers->get('ETag', ''));

        $payload = [
            'method' => $method,
            'status' => [
                'code' => (string) $statusCode,
            ],
        ];

        if ($bulkId !== '') {
            $payload['bulkId'] = $bulkId;
        }

        if ($location !== '') {
            $payload['location'] = $location;
        }

        if ($etag !== '') {
            $payload['version'] = $etag;
        }

        if (is_array($jsonPayload) && ($isError || in_array($method, ['POST', 'PUT', 'PATCH'], true))) {
            $payload['response'] = $jsonPayload;
        }

        if (! $isError && $bulkId !== '' && is_array($jsonPayload) && is_string($jsonPayload['id'] ?? null)) {
            $resolvedId = trim((string) ($jsonPayload['id'] ?? ''));

            if ($resolvedId !== '') {
                $bulkIdMap[$bulkId] = $resolvedId;
            }
        }

        return [
            'payload' => $payload,
            'is_error' => $isError,
        ];
    }

    private function createScimSubRequest(
        Request $request,
        int $tenantId,
        TenantScimProvider $provider,
        string $method,
        array $data = [],
        ?string $ifMatch = null,
    ): Request {
        $content = null;
        $server = [];

        if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            $content = json_encode($data, JSON_THROW_ON_ERROR);
            $server['CONTENT_TYPE'] = 'application/scim+json';
            $server['HTTP_CONTENT_TYPE'] = 'application/scim+json';
        }

        $subRequest = Request::create(
            uri: '/api/scim/v2/internal',
            method: $method,
            parameters: [],
            cookies: [],
            files: [],
            server: $server,
            content: $content,
        );

        $subRequest->attributes->set('scimTenantId', $tenantId);
        $subRequest->attributes->set('scimProvider', $provider);
        $subRequest->headers->set('Content-Type', 'application/scim+json');

        if (is_string($ifMatch) && trim($ifMatch) !== '') {
            $subRequest->headers->set('If-Match', trim($ifMatch));
        }

        return $subRequest;
    }

    /**
     * @param  array<string, string>  $bulkIdMap
     * @return array{ok: bool, detail?: string, resource?: string, resource_id?: string|null}
     */
    private function parseBulkPath(string $path, array $bulkIdMap): array
    {
        $path = trim($path);

        if ($path === '') {
            return [
                'ok' => false,
                'detail' => 'Bulk operation path is required.',
            ];
        }

        $path = preg_replace('#^https?://[^/]+#i', '', $path) ?? $path;
        $path = preg_replace('#^/api/scim/v2/#i', '', $path) ?? $path;
        $path = trim($path, '/');

        if ($path === '') {
            return [
                'ok' => false,
                'detail' => 'Bulk operation path is invalid.',
            ];
        }

        $segments = array_values(array_filter(explode('/', $path), fn (string $segment): bool => $segment !== ''));

        if ($segments === []) {
            return [
                'ok' => false,
                'detail' => 'Bulk operation path is invalid.',
            ];
        }

        $resource = strtolower((string) ($segments[0] ?? ''));
        if (! in_array($resource, ['users', 'groups'], true)) {
            return [
                'ok' => false,
                'detail' => 'Bulk operation path must target /Users or /Groups.',
            ];
        }

        if (count($segments) > 2) {
            return [
                'ok' => false,
                'detail' => 'Bulk operation path must target collection or single resource.',
            ];
        }

        $resourceId = isset($segments[1]) ? trim((string) $segments[1]) : null;

        if ($resourceId !== null && str_starts_with($resourceId, 'bulkId:')) {
            $ref = substr($resourceId, strlen('bulkId:'));

            if (! is_string($ref) || trim($ref) === '' || ! isset($bulkIdMap[$ref])) {
                return [
                    'ok' => false,
                    'detail' => 'Bulk operation references unknown bulkId "'.$ref.'".',
                ];
            }

            $resourceId = (string) $bulkIdMap[$ref];
        }

        return [
            'ok' => true,
            'resource' => $resource === 'users' ? 'Users' : 'Groups',
            'resource_id' => $resourceId !== '' ? $resourceId : null,
        ];
    }

    private function resolveBulkIdReferences(mixed $value, array $bulkIdMap): mixed
    {
        if (is_string($value) && str_starts_with($value, 'bulkId:')) {
            $bulkId = substr($value, strlen('bulkId:'));

            if (is_string($bulkId) && $bulkId !== '' && isset($bulkIdMap[$bulkId])) {
                return $bulkIdMap[$bulkId];
            }

            return $value;
        }

        if (! is_array($value)) {
            return $value;
        }

        $resolved = [];

        foreach ($value as $key => $entry) {
            $resolved[$key] = $this->resolveBulkIdReferences($entry, $bulkIdMap);
        }

        return $resolved;
    }

    /**
     * @return array{
     *   method: string,
     *   status: array{code: string},
     *   bulkId?: string,
     *   response: array{
     *     schemas: list<string>,
     *     status: string,
     *     detail: string
     *   }
     * }
     */
    private function bulkErrorPayload(string $method, ?string $bulkId, int $statusCode, string $detail): array
    {
        $payload = [
            'method' => $method,
            'status' => [
                'code' => (string) $statusCode,
            ],
            'response' => $this->scimErrorPayload($detail, $statusCode),
        ];

        if (is_string($bulkId) && $bulkId !== '') {
            $payload['bulkId'] = $bulkId;
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function responseJsonPayload(Response $response): ?array
    {
        $content = trim((string) $response->getContent());

        if ($content === '') {
            return null;
        }

        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @return array{
     *   schemas: list<string>,
     *   id: string,
     *   externalId: ?string,
     *   displayName: string,
     *   members: list<array{value: string, display: string, $ref: string}>,
     *   meta: array{
     *     resourceType: string,
     *     created: string,
     *     lastModified: string,
     *     version: string,
     *     location: string
     *   }
     * }
     */
    private function toScimGroupResource(Request $request, TenantScimGroup $group): array
    {
        $group->loadMissing('users:id,name,email');

        return [
            'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:Group'],
            'id' => (string) $group->id,
            'externalId' => $group->external_id !== null ? (string) $group->external_id : null,
            'displayName' => (string) $group->display_name,
            'members' => $group->users
                ->sortBy('id')
                ->map(fn (User $user): array => [
                    'value' => (string) $user->id,
                    'display' => trim((string) $user->name) !== '' ? (string) $user->name : (string) $user->email,
                    '$ref' => route('api.scim.users.show', ['id' => (string) $user->id]),
                ])
                ->values()
                ->all(),
            'meta' => $this->scimMetaForModel(
                model: $group,
                resourceType: 'Group',
                location: route('api.scim.groups.show', ['id' => (string) $group->id]),
            ),
        ];
    }

    private function logScimGroupAudit(
        Request $request,
        int $tenantId,
        string $action,
        ?TenantScimGroup $group = null,
        ?int $groupId = null,
        array $data = [],
    ): void {
        $auditableId = $group instanceof TenantScimGroup
            ? (int) $group->id
            : (int) $groupId;

        if ($auditableId <= 0) {
            return;
        }

        app(AuditLogService::class)->log(
            tenantId: $tenantId,
            actorUserId: null,
            action: $action,
            auditableType: TenantScimGroup::class,
            auditableId: $auditableId,
            data: $data,
            request: $request,
        );
    }

    private function logScimAudit(
        Request $request,
        int $tenantId,
        string $action,
        User $user,
        array $data = [],
    ): void {
        app(AuditLogService::class)->log(
            tenantId: $tenantId,
            actorUserId: null,
            action: $action,
            auditableType: User::class,
            auditableId: (int) $user->id,
            data: $data,
            request: $request,
        );
    }

    private function scimErrorPayload(string $detail, int $statusCode): array
    {
        return [
            'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
            'status' => (string) $statusCode,
            'detail' => $detail,
        ];
    }

    /**
     * @return array{
     *   schemas: list<string>,
     *   id: string,
     *   externalId: string,
     *   userName: string,
     *   active: bool,
     *   name: array{formatted: string, givenName: string, familyName: string},
     *   emails: list<array{value: string, primary: bool}>,
     *   meta: array{
     *     resourceType: string,
     *     created: string,
     *     lastModified: string,
     *     version: string,
     *     location: string
     *   }
     * }
     */
    private function toScimUserResource(Request $request, User $user): array
    {
        $name = trim((string) $user->name);
        $parts = preg_split('/\s+/', $name) ?: [];
        $givenName = trim((string) ($parts[0] ?? ''));
        $familyName = trim((string) implode(' ', array_slice($parts, 1)));

        return [
            'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:User'],
            'id' => (string) $user->id,
            'externalId' => (string) $user->id,
            'userName' => (string) $user->email,
            'active' => (bool) ($user->is_active ?? true),
            'name' => [
                'formatted' => $name,
                'givenName' => $givenName,
                'familyName' => $familyName,
            ],
            'emails' => [[
                'value' => (string) $user->email,
                'primary' => true,
            ]],
            'meta' => $this->scimMetaForModel(
                model: $user,
                resourceType: 'User',
                location: route('api.scim.users.show', ['id' => (string) $user->id]),
            ),
        ];
    }

    private function scimError(string $detail, int $statusCode)
    {
        return $this->scimResponse($this->scimErrorPayload($detail, $statusCode), $statusCode);
    }
}
