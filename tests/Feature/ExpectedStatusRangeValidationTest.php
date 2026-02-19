<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExpectedStatusRangeValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_store_rejects_expected_status_min_greater_than_max(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
        ]);

        $this->actingAs($user);

        $this->post(route('monitored-urls.store'), [
            'url' => 'https://example.com/range-web',
            'display_name' => 'Range web',
            'expected_status_min' => 500,
            'expected_status_max' => 399,
            'scan_mode' => 'http',
        ])->assertSessionHasErrors('expected_status_max');

        $this->assertDatabaseMissing('monitored_urls', [
            'tenant_id' => $tenant->id,
            'url' => 'https://example.com/range-web',
        ]);
    }

    public function test_api_store_rejects_expected_status_min_when_default_max_is_lower(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
        ]);

        Sanctum::actingAs($user, ['wids.read', 'wids.write']);

        $response = $this->postJson('/api/monitored-urls', [
            'url' => 'https://example.com/range-api',
            'display_name' => 'Range api',
            'expected_status_min' => 500,
            'scan_mode' => 'http',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('expected_status_max');

        $this->assertDatabaseMissing('monitored_urls', [
            'tenant_id' => $tenant->id,
            'url' => 'https://example.com/range-api',
        ]);
    }
}

