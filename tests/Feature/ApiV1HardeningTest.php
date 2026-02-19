<?php

namespace Tests\Feature;

use App\Models\Incident;
use App\Models\MonitoredUrl;
use App\Models\Tenant;
use App\Models\UrlSnapshot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiV1HardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_v1_login_and_me_endpoints_work(): void
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

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'VeryStrongPass123!',
        ]);

        $login->assertOk();
        $login->assertJsonStructure(['token', 'user', 'tenant']);

        $token = (string) $login->json('token');
        $this->assertNotSame('', $token);

        $me = $this->withToken($token)->getJson('/api/v1/me');
        $me->assertOk();
        $me->assertJsonPath('user.email', $user->email);
        $me->assertJsonPath('tenant.id', $tenant->id);
    }

    public function test_v1_login_rejects_inactive_user(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
            'email' => 'owner@example.com',
            'is_active' => false,
            'password' => Hash::make('VeryStrongPass123!'),
        ]);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'owner@example.com',
            'password' => 'VeryStrongPass123!',
        ]);

        $login->assertStatus(422);
    }

    public function test_v1_exposes_monitored_urls_and_incidents_endpoints(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
        ]);

        $monitor = MonitoredUrl::query()->create([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $user->id,
            'url' => 'https://example.com',
            'display_name' => 'Example',
            'is_active' => true,
            'frequency_minutes' => 1440,
            'timeout_seconds' => 30,
            'follow_redirects' => true,
            'expected_status_min' => 200,
            'expected_status_max' => 399,
        ]);

        $snapshot = UrlSnapshot::query()->create([
            'monitored_url_id' => $monitor->id,
            'fetched_at' => now(),
            'request_url' => $monitor->url,
            'final_url' => $monitor->url,
            'http_status' => 500,
            'headers' => [],
            'response_time_ms' => 120,
            'body_size' => 100,
            'body_sha256' => hash('sha256', 'body'),
        ]);

        Incident::query()->create([
            'tenant_id' => $tenant->id,
            'monitored_url_id' => $monitor->id,
            'previous_snapshot_id' => null,
            'current_snapshot_id' => $snapshot->id,
            'kind' => 'unexpected_status',
            'severity' => 90,
            'status' => 'open',
            'summary' => 'API v1 incident',
            'details' => ['http_status' => 500],
            'detected_at' => now(),
            'last_seen_at' => now(),
            'occurrences' => 1,
            'fingerprint' => hash('sha256', 'api-v1-incident'),
        ]);

        Sanctum::actingAs($user, ['wids.read', 'wids.write']);

        $monitors = $this->getJson('/api/v1/monitored-urls');
        $monitors->assertOk();
        $monitors->assertJsonPath('data.0.id', $monitor->id);

        $incidents = $this->getJson('/api/v1/incidents');
        $incidents->assertOk();
        $incidents->assertJsonPath('data.0.summary', 'API v1 incident');
    }

    public function test_v1_authenticated_rate_limit_is_enforced(): void
    {
        config()->set('wids.api.rate_limits.min_authenticated_requests_per_minute', 1);
        config()->set('wids.api.rate_limits.tenant_rpm_share_percent', 100);
        config()->set('wids.api.rate_limits.per_user_share_percent', 100);
        config()->set('wids.api.rate_limits.unlimited_tenant_requests_per_minute', 1);
        config()->set('wids.plan_tiers.lite.tenant_rpm', 1);

        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
            'plan_tier' => 'lite',
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
        ]);

        Sanctum::actingAs($user, ['wids.read', 'wids.write']);

        $first = $this->getJson('/api/v1/me');
        $first->assertOk();

        $second = $this->getJson('/api/v1/me');
        $second->assertStatus(429);
    }

    public function test_v1_login_issues_expiring_token_with_configured_abilities(): void
    {
        config()->set('wids.api.tokens.ttl_minutes', 60);
        config()->set('wids.api.tokens.abilities', ['wids.read', 'wids.write']);

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

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'VeryStrongPass123!',
        ]);

        $login->assertOk();
        $token = (string) $login->json('token');
        $this->assertNotSame('', $token);

        [$tokenId] = explode('|', $token, 2);

        $storedToken = DB::table('personal_access_tokens')
            ->where('id', (int) $tokenId)
            ->first(['abilities', 'expires_at']);

        $this->assertNotNull($storedToken);
        $this->assertSame(json_encode(['wids.read', 'wids.write']), $storedToken->abilities);

        $expiresAt = Carbon::parse((string) $storedToken->expires_at);
        $this->assertTrue($expiresAt->greaterThan(now()->addMinutes(55)));
        $this->assertTrue($expiresAt->lessThanOrEqualTo(now()->addMinutes(65)));
    }
}

