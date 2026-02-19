<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantScimProvider;
use App\Models\User;
use App\Services\ScimProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ScimApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_scim_service_provider_config_advertises_groups_and_bulk(): void
    {
        $this->enableScim();
        config()->set('wids.scim.bulk_enabled', true);
        config()->set('wids.scim.bulk_max_operations', 25);
        config()->set('wids.scim.bulk_max_payload_bytes', 131072);

        $tenant = Tenant::query()->create(['name' => 'Tenant', 'slug' => 'tenant']);
        $token = 'scim-token-config';
        $this->createProvider($tenant, $token);

        $this->withToken($token)
            ->getJson('/api/scim/v2/ServiceProviderConfig')
            ->assertStatus(200)
            ->assertJsonPath('etag.supported', true)
            ->assertJsonPath('bulk.supported', true)
            ->assertJsonPath('bulk.maxOperations', 25)
            ->assertJsonPath('bulk.maxPayloadSize', 131072);

        $this->withToken($token)
            ->getJson('/api/scim/v2/ResourceTypes')
            ->assertStatus(200)
            ->assertJsonPath('totalResults', 2)
            ->assertJsonPath('Resources.0.name', 'User')
            ->assertJsonPath('Resources.1.name', 'Group');
    }

    public function test_scim_requires_bearer_token(): void
    {
        config()->set('wids.scim.enabled', true);

        $this->getJson('/api/scim/v2/ServiceProviderConfig')
            ->assertStatus(401)
            ->assertJsonPath('schemas.0', 'urn:ietf:params:scim:api:messages:2.0:Error');
    }

    public function test_scim_users_list_is_tenant_scoped(): void
    {
        config()->set('wids.scim.enabled', true);

        $tenantA = Tenant::query()->create(['name' => 'A', 'slug' => 'a']);
        $tenantB = Tenant::query()->create(['name' => 'B', 'slug' => 'b']);

        $userA1 = User::factory()->create(['tenant_id' => $tenantA->id, 'email' => 'a1@example.com']);
        $userA2 = User::factory()->create(['tenant_id' => $tenantA->id, 'email' => 'a2@example.com']);
        User::factory()->create(['tenant_id' => $tenantB->id, 'email' => 'b1@example.com']);

        $token = 'scim-token-a';

        TenantScimProvider::query()->create([
            'tenant_id' => $tenantA->id,
            'is_enabled' => true,
            'auth_token_hash' => app(ScimProvisioningService::class)->tokenHash($token),
            'auth_token_last8' => substr($token, -8),
        ]);

        $response = $this->withToken($token)
            ->getJson('/api/scim/v2/Users?startIndex=1&count=100');

        $response
            ->assertStatus(200)
            ->assertJsonPath('schemas.0', 'urn:ietf:params:scim:api:messages:2.0:ListResponse')
            ->assertJsonPath('totalResults', 2)
            ->assertJsonPath('itemsPerPage', 2)
            ->assertJsonPath('Resources.0.userName', 'a1@example.com')
            ->assertJsonPath('Resources.1.userName', 'a2@example.com');

        $response->assertJsonMissing(['userName' => 'b1@example.com']);

        $showResponse = $this->withToken($token)
            ->getJson('/api/scim/v2/Users/'.$userA2->id);

        $showResponse
            ->assertStatus(200)
            ->assertJsonPath('id', (string) $userA2->id)
            ->assertJsonPath('userName', 'a2@example.com');

        $otherTenantResponse = $this->withToken($token)
            ->getJson('/api/scim/v2/Users/'.($userA2->id + 100000));
        $otherTenantResponse->assertStatus(404);
    }

    public function test_scim_users_filter_by_username_eq_email(): void
    {
        $this->enableScim();

        $tenant = Tenant::query()->create(['name' => 'A', 'slug' => 'a']);
        User::factory()->create(['tenant_id' => $tenant->id, 'email' => 'one@example.com']);
        User::factory()->create(['tenant_id' => $tenant->id, 'email' => 'two@example.com']);

        $token = 'scim-token-filter';
        $this->createProvider($tenant, $token);

        $response = $this->withToken($token)
            ->get('/api/scim/v2/Users?filter='.urlencode('userName eq "two@example.com"'));

        $response
            ->assertStatus(200)
            ->assertJsonPath('totalResults', 1)
            ->assertJsonPath('Resources.0.userName', 'two@example.com');
    }

    public function test_scim_users_filter_supports_and_or_and_string_operators(): void
    {
        $this->enableScim();

        $tenant = Tenant::query()->create(['name' => 'A', 'slug' => 'a']);
        $token = 'scim-token-users-advanced-filter';
        $this->createProvider($tenant, $token);

        User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'ops.alpha@example.com',
            'name' => 'Ops Alpha',
            'is_active' => true,
        ]);
        User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'ops.beta@example.com',
            'name' => 'Ops Beta',
            'is_active' => false,
        ]);
        User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'support@example.com',
            'name' => 'Support Team',
            'is_active' => true,
        ]);

        $this->withToken($token)
            ->get('/api/scim/v2/Users?filter='.urlencode('active eq true and userName co "ops."'))
            ->assertStatus(200)
            ->assertJsonPath('totalResults', 1)
            ->assertJsonPath('Resources.0.userName', 'ops.alpha@example.com');

        $this->withToken($token)
            ->get('/api/scim/v2/Users?filter='.urlencode('name sw "Support" or active eq false'))
            ->assertStatus(200)
            ->assertJsonPath('totalResults', 2);
    }

    public function test_scim_create_requires_auto_provision(): void
    {
        $this->enableScim();

        $tenant = Tenant::query()->create(['name' => 'Tenant', 'slug' => 'tenant']);
        $token = 'scim-token-create-denied';
        $this->createProvider($tenant, $token, [
            'auto_provision' => false,
        ]);

        $this->withToken($token)
            ->postJson('/api/scim/v2/Users', [
                'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:User'],
                'userName' => 'new@example.com',
            ])
            ->assertStatus(403)
            ->assertJsonPath('detail', 'SCIM auto-provision is disabled for this workspace.');
    }

    public function test_scim_users_write_lifecycle_create_replace_patch_and_disable_deprovision(): void
    {
        $this->enableScim();

        $tenant = Tenant::query()->create(['name' => 'Tenant', 'slug' => 'tenant']);
        $token = 'scim-token-write';
        $this->createProvider($tenant, $token, [
            'auto_provision' => true,
            'default_role' => 'owner',
            'deprovision_mode' => 'disable',
        ]);

        $createResponse = $this->withToken($token)
            ->postJson('/api/scim/v2/Users', [
                'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:User'],
                'userName' => 'new.user@example.com',
                'name' => [
                    'givenName' => 'New',
                    'familyName' => 'User',
                ],
                'active' => true,
            ]);

        $createResponse
            ->assertStatus(201)
            ->assertJsonPath('userName', 'new.user@example.com')
            ->assertJsonPath('active', true);

        $userId = (int) $createResponse->json('id');
        $this->assertGreaterThan(0, $userId);

        $this->assertDatabaseHas('users', [
            'id' => $userId,
            'tenant_id' => $tenant->id,
            'role' => 'owner',
            'email' => 'new.user@example.com',
            'is_active' => 1,
        ]);

        $replaceResponse = $this->withToken($token)
            ->putJson('/api/scim/v2/Users/'.$userId, [
                'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:User'],
                'userName' => 'updated.user@example.com',
                'name' => [
                    'formatted' => 'Updated User',
                ],
                'active' => true,
            ]);

        $replaceResponse
            ->assertStatus(200)
            ->assertJsonPath('userName', 'updated.user@example.com')
            ->assertJsonPath('name.formatted', 'Updated User')
            ->assertJsonPath('active', true);

        $this->assertDatabaseHas('users', [
            'id' => $userId,
            'email' => 'updated.user@example.com',
            'name' => 'Updated User',
            'is_active' => 1,
        ]);

        $patchResponse = $this->withToken($token)
            ->patchJson('/api/scim/v2/Users/'.$userId, [
                'schemas' => ['urn:ietf:params:scim:api:messages:2.0:PatchOp'],
                'Operations' => [
                    [
                        'op' => 'replace',
                        'path' => 'active',
                        'value' => false,
                    ],
                    [
                        'op' => 'replace',
                        'path' => 'userName',
                        'value' => 'patched.user@example.com',
                    ],
                ],
            ]);

        $patchResponse
            ->assertStatus(200)
            ->assertJsonPath('active', false)
            ->assertJsonPath('userName', 'patched.user@example.com');

        $this->assertDatabaseHas('users', [
            'id' => $userId,
            'email' => 'patched.user@example.com',
            'is_active' => 0,
        ]);

        $user = User::query()->findOrFail($userId);
        $user->createToken('api');

        DB::table('sessions')->insert([
            'id' => 'scim-session-'.$userId,
            'user_id' => $userId,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'scim-test',
            'payload' => 'test',
            'last_activity' => time(),
        ]);

        $this->withToken($token)
            ->delete('/api/scim/v2/Users/'.$userId)
            ->assertStatus(204);

        $this->assertDatabaseHas('users', [
            'id' => $userId,
            'is_active' => 0,
        ]);

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_type' => User::class,
            'tokenable_id' => $userId,
        ]);

        $this->assertDatabaseMissing('sessions', [
            'id' => 'scim-session-'.$userId,
        ]);
    }

    public function test_scim_delete_mode_removes_user_record(): void
    {
        $this->enableScim();

        $tenant = Tenant::query()->create(['name' => 'Tenant', 'slug' => 'tenant']);
        $token = 'scim-token-delete';
        $this->createProvider($tenant, $token, [
            'auto_provision' => true,
            'deprovision_mode' => 'delete',
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'delete.me@example.com',
            'is_active' => true,
        ]);

        $this->withToken($token)
            ->delete('/api/scim/v2/Users/'.$user->id)
            ->assertStatus(204);

        $this->assertDatabaseMissing('users', [
            'id' => $user->id,
        ]);
    }

    public function test_scim_create_respects_domain_allowlist(): void
    {
        $this->enableScim();

        $tenant = Tenant::query()->create(['name' => 'Tenant', 'slug' => 'tenant']);
        $token = 'scim-token-domain';
        $this->createProvider($tenant, $token, [
            'auto_provision' => true,
            'domain_allowlist' => ['allowed.com'],
        ]);

        $this->withToken($token)
            ->postJson('/api/scim/v2/Users', [
                'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:User'],
                'userName' => 'blocked@example.com',
            ])
            ->assertStatus(403)
            ->assertJsonPath('detail', 'Email domain is not allowed for this workspace SCIM provider.');
    }

    public function test_scim_groups_write_lifecycle_create_patch_and_delete(): void
    {
        $this->enableScim();

        $tenant = Tenant::query()->create(['name' => 'Tenant', 'slug' => 'tenant']);
        $token = 'scim-token-groups';
        $this->createProvider($tenant, $token, [
            'auto_provision' => true,
        ]);

        $userA = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'group.a@example.com',
            'name' => 'Group A',
        ]);
        $userB = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'group.b@example.com',
            'name' => 'Group B',
        ]);

        $createResponse = $this->withToken($token)
            ->postJson('/api/scim/v2/Groups', [
                'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:Group'],
                'displayName' => 'Ops Team',
                'members' => [
                    ['value' => (string) $userA->id],
                ],
            ]);

        $createResponse
            ->assertStatus(201)
            ->assertJsonPath('displayName', 'Ops Team')
            ->assertJsonPath('members.0.value', (string) $userA->id);

        $groupId = (int) $createResponse->json('id');
        $this->assertGreaterThan(0, $groupId);

        $this->assertDatabaseHas('tenant_scim_groups', [
            'id' => $groupId,
            'tenant_id' => $tenant->id,
            'display_name' => 'Ops Team',
        ]);

        $this->assertDatabaseHas('tenant_scim_group_memberships', [
            'tenant_scim_group_id' => $groupId,
            'user_id' => $userA->id,
        ]);

        $this->withToken($token)
            ->getJson('/api/scim/v2/Groups?filter='.urlencode('displayName eq "Ops Team"'))
            ->assertStatus(200)
            ->assertJsonPath('totalResults', 1)
            ->assertJsonPath('Resources.0.id', (string) $groupId);

        $patchResponse = $this->withToken($token)
            ->patchJson('/api/scim/v2/Groups/'.$groupId, [
                'schemas' => ['urn:ietf:params:scim:api:messages:2.0:PatchOp'],
                'Operations' => [
                    [
                        'op' => 'add',
                        'path' => 'members',
                        'value' => [
                            ['value' => (string) $userB->id],
                        ],
                    ],
                    [
                        'op' => 'remove',
                        'path' => 'members[value eq "'.$userA->id.'"]',
                    ],
                ],
            ]);

        $patchResponse
            ->assertStatus(200)
            ->assertJsonPath('members.0.value', (string) $userB->id);

        $this->assertDatabaseMissing('tenant_scim_group_memberships', [
            'tenant_scim_group_id' => $groupId,
            'user_id' => $userA->id,
        ]);

        $this->assertDatabaseHas('tenant_scim_group_memberships', [
            'tenant_scim_group_id' => $groupId,
            'user_id' => $userB->id,
        ]);

        $this->withToken($token)
            ->delete('/api/scim/v2/Groups/'.$groupId)
            ->assertStatus(204);

        $this->assertDatabaseMissing('tenant_scim_groups', [
            'id' => $groupId,
        ]);
    }

    public function test_scim_groups_filter_supports_string_ops_and_members_value_eq(): void
    {
        $this->enableScim();

        $tenant = Tenant::query()->create(['name' => 'Tenant', 'slug' => 'tenant']);
        $token = 'scim-token-groups-advanced-filter';
        $this->createProvider($tenant, $token, [
            'auto_provision' => true,
        ]);

        $userA = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'group.filter.a@example.com',
        ]);
        $userB = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'group.filter.b@example.com',
        ]);

        $groupOps = DB::table('tenant_scim_groups')->insertGetId([
            'tenant_id' => $tenant->id,
            'display_name' => 'Ops Team',
            'external_id' => 'ext-ops',
            'metadata' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $groupPlatform = DB::table('tenant_scim_groups')->insertGetId([
            'tenant_id' => $tenant->id,
            'display_name' => 'Platform',
            'external_id' => 'ext-platform',
            'metadata' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $groupLegacy = DB::table('tenant_scim_groups')->insertGetId([
            'tenant_id' => $tenant->id,
            'display_name' => 'Legacy Team',
            'external_id' => null,
            'metadata' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('tenant_scim_group_memberships')->insert([
            [
                'tenant_id' => $tenant->id,
                'tenant_scim_group_id' => $groupOps,
                'user_id' => $userA->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'tenant_id' => $tenant->id,
                'tenant_scim_group_id' => $groupLegacy,
                'user_id' => $userB->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->withToken($token)
            ->get('/api/scim/v2/Groups?filter='.urlencode('displayName co "Team" and externalId sw "ext-"'))
            ->assertStatus(200)
            ->assertJsonPath('totalResults', 1)
            ->assertJsonPath('Resources.0.id', (string) $groupOps);

        $this->withToken($token)
            ->get('/api/scim/v2/Groups?filter='.urlencode('members.value eq "'.$userB->id.'" or displayName eq "Platform"'))
            ->assertStatus(200)
            ->assertJsonPath('totalResults', 2);
    }

    public function test_scim_invalid_filter_expression_returns_400(): void
    {
        $this->enableScim();

        $tenant = Tenant::query()->create(['name' => 'Tenant', 'slug' => 'tenant']);
        $token = 'scim-token-invalid-filter';
        $this->createProvider($tenant, $token);
        User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'invalid.filter@example.com',
        ]);

        $this->withToken($token)
            ->get('/api/scim/v2/Users?filter='.urlencode('userName gt "a@example.com"'))
            ->assertStatus(400)
            ->assertJsonPath('schemas.0', 'urn:ietf:params:scim:api:messages:2.0:Error');
    }

    public function test_scim_group_role_mapping_sync_updates_user_role(): void
    {
        $this->enableScim();

        $tenant = Tenant::query()->create(['name' => 'Tenant', 'slug' => 'tenant']);
        $token = 'scim-token-role-sync';
        $this->createProvider($tenant, $token, [
            'auto_provision' => true,
            'default_role' => 'member',
            'metadata' => [
                'group_role_sync_enabled' => true,
                'group_role_sync_strict' => true,
                'group_role_mappings' => [
                    'owner' => ['Owners'],
                    'member' => ['Members'],
                ],
            ],
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'sync.role@example.com',
            'role' => 'member',
        ]);

        $createGroupResponse = $this->withToken($token)
            ->postJson('/api/scim/v2/Groups', [
                'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:Group'],
                'displayName' => 'Owners',
                'members' => [
                    ['value' => (string) $user->id],
                ],
            ]);

        $createGroupResponse->assertStatus(201);
        $groupId = (int) $createGroupResponse->json('id');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'role' => 'owner',
        ]);

        $this->withToken($token)
            ->patchJson('/api/scim/v2/Groups/'.$groupId, [
                'schemas' => ['urn:ietf:params:scim:api:messages:2.0:PatchOp'],
                'Operations' => [
                    [
                        'op' => 'remove',
                        'path' => 'members[value eq "'.$user->id.'"]',
                    ],
                ],
            ])
            ->assertStatus(200);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'role' => 'member',
        ]);
    }

    public function test_scim_bulk_processes_user_and_group_operations_with_bulk_id_references(): void
    {
        $this->enableScim();
        config()->set('wids.scim.bulk_enabled', true);
        config()->set('wids.scim.bulk_max_operations', 10);

        $tenant = Tenant::query()->create(['name' => 'Tenant', 'slug' => 'tenant']);
        $token = 'scim-token-bulk';
        $this->createProvider($tenant, $token, [
            'auto_provision' => true,
        ]);

        $response = $this->withToken($token)
            ->postJson('/api/scim/v2/Bulk', [
                'schemas' => ['urn:ietf:params:scim:api:messages:2.0:BulkRequest'],
                'failOnErrors' => 1,
                'Operations' => [
                    [
                        'method' => 'POST',
                        'bulkId' => 'user1',
                        'path' => '/Users',
                        'data' => [
                            'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:User'],
                            'userName' => 'bulk.user@example.com',
                            'name' => ['formatted' => 'Bulk User'],
                            'active' => true,
                        ],
                    ],
                    [
                        'method' => 'POST',
                        'bulkId' => 'group1',
                        'path' => '/Groups',
                        'data' => [
                            'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:Group'],
                            'displayName' => 'Bulk Group',
                            'members' => [
                                ['value' => 'bulkId:user1'],
                            ],
                        ],
                    ],
                ],
            ]);

        $response
            ->assertStatus(200)
            ->assertJsonPath('schemas.0', 'urn:ietf:params:scim:api:messages:2.0:BulkResponse')
            ->assertJsonPath('Operations.0.status.code', '201')
            ->assertJsonPath('Operations.1.status.code', '201')
            ->assertJsonPath('Operations.1.response.displayName', 'Bulk Group');

        $createdUserId = (int) data_get($response->json(), 'Operations.0.response.id');
        $createdGroupId = (int) data_get($response->json(), 'Operations.1.response.id');

        $this->assertGreaterThan(0, $createdUserId);
        $this->assertGreaterThan(0, $createdGroupId);

        $this->assertDatabaseHas('users', [
            'id' => $createdUserId,
            'tenant_id' => $tenant->id,
            'email' => 'bulk.user@example.com',
        ]);

        $this->assertDatabaseHas('tenant_scim_groups', [
            'id' => $createdGroupId,
            'tenant_id' => $tenant->id,
            'display_name' => 'Bulk Group',
        ]);

        $this->assertDatabaseHas('tenant_scim_group_memberships', [
            'tenant_scim_group_id' => $createdGroupId,
            'user_id' => $createdUserId,
        ]);
    }

    public function test_scim_user_resource_returns_etag_and_honors_if_none_match(): void
    {
        $this->enableScim();

        $tenant = Tenant::query()->create(['name' => 'Tenant', 'slug' => 'tenant']);
        $token = 'scim-token-etag-user';
        $this->createProvider($tenant, $token, [
            'auto_provision' => true,
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'etag.user@example.com',
            'name' => 'ETag User',
        ]);

        $showResponse = $this->withToken($token)
            ->getJson('/api/scim/v2/Users/'.$user->id);

        $showResponse->assertStatus(200);
        $etag = (string) $showResponse->headers->get('ETag');
        $this->assertNotSame('', $etag);
        $showResponse->assertJsonPath('meta.version', $etag);
        $showResponse->assertJsonPath('meta.lastModified', $user->fresh()?->updated_at?->copy()->utc()->format('Y-m-d\TH:i:s.u\Z'));

        $notModifiedResponse = $this->withToken($token)
            ->withHeaders(['If-None-Match' => $etag])
            ->get('/api/scim/v2/Users/'.$user->id);

        $notModifiedResponse->assertStatus(304);
        $this->assertSame('', (string) $notModifiedResponse->getContent());
    }

    public function test_scim_user_patch_requires_matching_if_match_when_header_is_sent(): void
    {
        $this->enableScim();

        $tenant = Tenant::query()->create(['name' => 'Tenant', 'slug' => 'tenant']);
        $token = 'scim-token-if-match-user';
        $this->createProvider($tenant, $token, [
            'auto_provision' => true,
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'ifmatch.user@example.com',
            'name' => 'If Match User',
            'is_active' => true,
        ]);

        $showResponse = $this->withToken($token)
            ->getJson('/api/scim/v2/Users/'.$user->id);
        $showResponse->assertStatus(200);
        $etag = (string) $showResponse->headers->get('ETag');
        $this->assertNotSame('', $etag);

        $this->withToken($token)
            ->withHeaders(['If-Match' => '"stale-version"'])
            ->patchJson('/api/scim/v2/Users/'.$user->id, [
                'schemas' => ['urn:ietf:params:scim:api:messages:2.0:PatchOp'],
                'Operations' => [
                    [
                        'op' => 'replace',
                        'path' => 'active',
                        'value' => false,
                    ],
                ],
            ])
            ->assertStatus(412)
            ->assertJsonPath('detail', 'Precondition failed. If-Match does not match resource version.');

        $patchResponse = $this->withToken($token)
            ->withHeaders(['If-Match' => $etag])
            ->patchJson('/api/scim/v2/Users/'.$user->id, [
                'schemas' => ['urn:ietf:params:scim:api:messages:2.0:PatchOp'],
                'Operations' => [
                    [
                        'op' => 'replace',
                        'path' => 'active',
                        'value' => false,
                    ],
                ],
            ]);

        $patchResponse->assertStatus(200)
            ->assertJsonPath('active', false);
        $newEtag = (string) $patchResponse->headers->get('ETag');
        $this->assertNotSame('', $newEtag);
        $this->assertNotSame($etag, $newEtag);
    }

    public function test_scim_group_put_requires_matching_if_match_when_header_is_sent(): void
    {
        $this->enableScim();

        $tenant = Tenant::query()->create(['name' => 'Tenant', 'slug' => 'tenant']);
        $token = 'scim-token-if-match-group';
        $this->createProvider($tenant, $token, [
            'auto_provision' => true,
        ]);

        $groupCreateResponse = $this->withToken($token)
            ->postJson('/api/scim/v2/Groups', [
                'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:Group'],
                'displayName' => 'IfMatch Group',
                'members' => [],
            ]);
        $groupCreateResponse->assertStatus(201);
        $groupId = (int) $groupCreateResponse->json('id');
        $etag = (string) $groupCreateResponse->headers->get('ETag');
        $this->assertNotSame('', $etag);

        $this->withToken($token)
            ->withHeaders(['If-Match' => '"stale-group-version"'])
            ->putJson('/api/scim/v2/Groups/'.$groupId, [
                'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:Group'],
                'displayName' => 'IfMatch Group Updated',
                'members' => [],
            ])
            ->assertStatus(412)
            ->assertJsonPath('detail', 'Precondition failed. If-Match does not match resource version.');

        $this->withToken($token)
            ->withHeaders(['If-Match' => $etag])
            ->putJson('/api/scim/v2/Groups/'.$groupId, [
                'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:Group'],
                'displayName' => 'IfMatch Group Updated',
                'members' => [],
            ])
            ->assertStatus(200)
            ->assertJsonPath('displayName', 'IfMatch Group Updated');
    }

    private function enableScim(): void
    {
        config()->set('wids.scim.enabled', true);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createProvider(Tenant $tenant, string $token, array $overrides = []): TenantScimProvider
    {
        return TenantScimProvider::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'is_enabled' => true,
            'auth_token_hash' => app(ScimProvisioningService::class)->tokenHash($token),
            'auth_token_last8' => substr($token, -8),
            'auto_provision' => false,
            'default_role' => 'member',
            'deprovision_mode' => 'disable',
            'domain_allowlist' => [],
        ], $overrides));
    }
}
