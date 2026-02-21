<?php

namespace Tests\Feature;

use App\Jobs\ScanMonitoredUrlJob;
use App\Models\MonitoredUrl;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PluginApiContractTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('wids.api.plugin.enabled', true);
        config()->set('wids.api.plugin.accept_legacy_wids_scopes', false);
        config()->set('wids.api.plugin.allow_empty_scopes', false);
    }

    public function test_plugin_api_requires_api_key(): void
    {
        $response = $this->getJson('/api/plugin/me');

        $response->assertStatus(401)
            ->assertJsonPath('error', 'missing_api_key');
    }

    public function test_plugin_login_issues_token_and_me_works(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
            'email' => 'owner@example.com',
            'password' => Hash::make('VeryStrongPass123!'),
        ]);

        $login = $this->postJson('/api/plugin/auth/login', [
            'email' => $user->email,
            'password' => 'VeryStrongPass123!',
        ]);

        $login->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('user.email', $user->email);

        $token = (string) $login->json('token');
        $this->assertNotSame('', $token);

        $me = $this->withHeader('X-Api-Key', $token)->getJson('/api/plugin/me');
        $me->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('user.email', $user->email)
            ->assertJsonPath('capabilities.can_run_scan', true);
    }

    public function test_plugin_monitors_respects_per_page_parameter(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
        ]);

        MonitoredUrl::query()->create([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $owner->id,
            'url' => 'https://example-1.test',
            'display_name' => 'Example 1',
            'is_active' => true,
            'frequency_minutes' => 1440,
            'timeout_seconds' => 30,
            'follow_redirects' => true,
            'expected_status_min' => 200,
            'expected_status_max' => 399,
        ]);

        MonitoredUrl::query()->create([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $owner->id,
            'url' => 'https://example-2.test',
            'display_name' => 'Example 2',
            'is_active' => true,
            'frequency_minutes' => 1440,
            'timeout_seconds' => 30,
            'follow_redirects' => true,
            'expected_status_min' => 200,
            'expected_status_max' => 399,
        ]);

        $token = $owner->createToken('plugin', ['plugin.read'])->plainTextToken;

        $response = $this->withHeader('X-Api-Key', $token)->getJson('/api/plugin/monitored-urls?per_page=1&page=1');

        $response->assertOk()
            ->assertJsonPath('per_page', 1)
            ->assertJsonCount(1, 'data');
    }

    public function test_plugin_scan_requires_owner_role(): void
    {
        Queue::fake();

        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        $member = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'member',
        ]);

        $monitor = MonitoredUrl::query()->create([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $member->id,
            'url' => 'https://example-owner-required.test',
            'display_name' => 'Owner Required',
            'is_active' => true,
            'frequency_minutes' => 1440,
            'timeout_seconds' => 30,
            'follow_redirects' => true,
            'expected_status_min' => 200,
            'expected_status_max' => 399,
        ]);

        $token = $member->createToken('plugin', ['plugin.write'])->plainTextToken;
        $response = $this->withHeader('X-Api-Key', $token)->postJson('/api/plugin/monitored-urls/' . $monitor->id . '/scan');

        $response->assertStatus(403)
            ->assertJsonPath('error', 'owner_required');

        Queue::assertNothingPushed();
    }

    public function test_plugin_scan_accepts_legacy_wids_write_scope_for_owner(): void
    {
        Queue::fake();
        config()->set('wids.api.plugin.accept_legacy_wids_scopes', true);

        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
        ]);

        $monitor = MonitoredUrl::query()->create([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $owner->id,
            'url' => 'https://example-legacy-scope.test',
            'display_name' => 'Legacy Scope',
            'is_active' => true,
            'frequency_minutes' => 1440,
            'timeout_seconds' => 30,
            'follow_redirects' => true,
            'expected_status_min' => 200,
            'expected_status_max' => 399,
        ]);

        $token = $owner->createToken('legacy', ['wids.write'])->plainTextToken;
        $response = $this->withHeader('X-Api-Key', $token)->postJson('/api/plugin/monitored-urls/' . $monitor->id . '/scan');

        $response->assertStatus(202)
            ->assertJsonPath('message', 'Scan dispatched.');

        Queue::assertPushed(ScanMonitoredUrlJob::class);
    }

    public function test_plugin_api_rejects_token_without_scopes_when_disabled(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
        ]);

        $token = $owner->createToken('empty', [])->plainTextToken;
        $response = $this->withHeader('X-Api-Key', $token)->getJson('/api/plugin/me');

        $response->assertStatus(403)
            ->assertJsonPath('error', 'insufficient_scope');
    }

    public function test_plugin_login_rotates_token_for_same_client_id(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
            'email' => 'owner@example.com',
            'password' => Hash::make('VeryStrongPass123!'),
        ]);

        $first = $this->postJson('/api/plugin/auth/login', [
            'email' => $user->email,
            'password' => 'VeryStrongPass123!',
            'client_id' => 'https://site-a.example',
        ])->assertOk();

        $firstToken = (string) $first->json('token');
        $this->assertNotSame('', $firstToken);

        $second = $this->postJson('/api/plugin/auth/login', [
            'email' => $user->email,
            'password' => 'VeryStrongPass123!',
            'client_id' => 'https://site-a.example',
        ])->assertOk();

        $secondToken = (string) $second->json('token');
        $this->assertNotSame('', $secondToken);
        $this->assertNotSame($firstToken, $secondToken);

        $this->withHeader('X-Api-Key', $firstToken)
            ->getJson('/api/plugin/me')
            ->assertStatus(401)
            ->assertJsonPath('error', 'invalid_api_key');

        $this->withHeader('X-Api-Key', $secondToken)
            ->getJson('/api/plugin/me')
            ->assertOk();
    }

    public function test_plugin_logout_revokes_current_token(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
        ]);

        $token = $owner->createToken('plugin', ['plugin.read'])->plainTextToken;

        $logout = $this->withHeader('X-Api-Key', $token)->postJson('/api/plugin/auth/logout');
        $logout->assertOk()
            ->assertJsonPath('ok', true);

        $me = $this->withHeader('X-Api-Key', $token)->getJson('/api/plugin/me');
        $me->assertStatus(401)
            ->assertJsonPath('error', 'invalid_api_key');
    }
}
