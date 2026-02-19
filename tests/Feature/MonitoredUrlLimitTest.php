<?php

namespace Tests\Feature;

use App\Models\MonitoredUrl;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MonitoredUrlLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_monitored_url_store_enforces_tenant_limit(): void
    {
        config()->set('wids.limits.max_monitored_urls_per_tenant', 1);

        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
        ]);

        MonitoredUrl::query()->create([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $user->id,
            'url' => 'https://one.example.com',
            'display_name' => 'One',
            'is_active' => true,
            'frequency_minutes' => 1440,
            'timeout_seconds' => 30,
            'follow_redirects' => true,
            'expected_status_min' => 200,
            'expected_status_max' => 399,
        ]);

        $this->actingAs($user);

        $response = $this->post(route('monitored-urls.store'), [
            'url' => 'https://two.example.com',
            'display_name' => 'Two',
            'is_active' => true,
            'frequency_minutes' => 1440,
            'timeout_seconds' => 30,
            'follow_redirects' => true,
            'expected_status_min' => 200,
            'expected_status_max' => 399,
            'scan_mode' => 'http',
        ]);

        $response->assertSessionHasErrors('url');
        $this->assertSame(1, MonitoredUrl::query()->where('tenant_id', $tenant->id)->count());
    }

    public function test_api_monitored_url_store_enforces_tenant_limit(): void
    {
        config()->set('wids.limits.max_monitored_urls_per_tenant', 1);

        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
        ]);

        MonitoredUrl::query()->create([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $user->id,
            'url' => 'https://one.example.com',
            'display_name' => 'One',
            'is_active' => true,
            'frequency_minutes' => 1440,
            'timeout_seconds' => 30,
            'follow_redirects' => true,
            'expected_status_min' => 200,
            'expected_status_max' => 399,
        ]);

        Sanctum::actingAs($user, ['wids.read', 'wids.write']);

        $response = $this->postJson('/api/monitored-urls', [
            'url' => 'https://two.example.com',
            'display_name' => 'Two',
            'is_active' => true,
            'frequency_minutes' => 1440,
            'timeout_seconds' => 30,
            'follow_redirects' => true,
            'expected_status_min' => 200,
            'expected_status_max' => 399,
            'scan_mode' => 'http',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('url');
        $this->assertSame(1, MonitoredUrl::query()->where('tenant_id', $tenant->id)->count());
    }
}


