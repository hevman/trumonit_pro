<?php

namespace Tests\Feature;

use App\Models\Incident;
use App\Models\MonitoredUrl;
use App\Models\Tenant;
use App\Models\UrlSnapshot;
use App\Models\User;
use App\Services\TagService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MonitoredUrlTagsTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_can_create_monitor_with_tags_and_filter_by_tag(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-11 12:00:00', 'UTC'));

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
            'url' => 'https://example.com/alpha',
            'display_name' => 'Alpha',
            'tags' => 'prod, team:payments',
            'is_active' => true,
            'scan_mode' => 'http',
        ])->assertRedirect();

        $alpha = MonitoredUrl::query()->where('tenant_id', $tenant->id)->where('url', 'https://example.com/alpha')->firstOrFail();
        $this->assertSame(['prod', 'team:payments'], $alpha->tags()->pluck('name')->sort()->values()->all());

        $beta = MonitoredUrl::query()->create([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $user->id,
            'url' => 'https://example.com/beta',
            'display_name' => 'Beta',
            'is_active' => true,
            'frequency_minutes' => 1440,
            'timeout_seconds' => 30,
            'follow_redirects' => true,
            'expected_status_min' => 200,
            'expected_status_max' => 399,
        ]);

        app(TagService::class)->syncMonitoredUrlTags($beta, ['staging']);

        $this->get(route('monitored-urls.index', ['tag' => 'prod']))
            ->assertOk()
            ->assertSee('Alpha', escape: false)
            ->assertDontSee('Beta', escape: false);
    }

    public function test_web_rejects_invalid_tags_on_store(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-11 12:00:00', 'UTC'));

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
            'url' => 'https://example.com/alpha',
            'display_name' => 'Alpha',
            'tags' => 'bad tag',
            'is_active' => true,
            'scan_mode' => 'http',
        ])->assertSessionHasErrors('tags');
    }

    public function test_web_incidents_index_can_filter_by_monitor_tag(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-11 12:00:00', 'UTC'));

        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
        ]);

        $alpha = MonitoredUrl::query()->create([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $user->id,
            'url' => 'https://alpha.example.com',
            'display_name' => 'Alpha',
            'is_active' => true,
            'frequency_minutes' => 1440,
            'timeout_seconds' => 30,
            'follow_redirects' => true,
            'expected_status_min' => 200,
            'expected_status_max' => 399,
        ]);
        app(TagService::class)->syncMonitoredUrlTags($alpha, ['prod']);

        $beta = MonitoredUrl::query()->create([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $user->id,
            'url' => 'https://beta.example.com',
            'display_name' => 'Beta',
            'is_active' => true,
            'frequency_minutes' => 1440,
            'timeout_seconds' => 30,
            'follow_redirects' => true,
            'expected_status_min' => 200,
            'expected_status_max' => 399,
        ]);
        app(TagService::class)->syncMonitoredUrlTags($beta, ['staging']);

        $alphaSnapshot = UrlSnapshot::query()->create([
            'monitored_url_id' => $alpha->id,
            'fetched_at' => now(),
            'request_url' => $alpha->url,
            'final_url' => $alpha->url,
            'http_status' => 500,
            'headers' => [],
            'response_time_ms' => 123,
            'body_size' => 10,
            'body_sha256' => hash('sha256', 'alpha'),
        ]);

        $betaSnapshot = UrlSnapshot::query()->create([
            'monitored_url_id' => $beta->id,
            'fetched_at' => now(),
            'request_url' => $beta->url,
            'final_url' => $beta->url,
            'http_status' => 500,
            'headers' => [],
            'response_time_ms' => 123,
            'body_size' => 10,
            'body_sha256' => hash('sha256', 'beta'),
        ]);

        Incident::query()->create([
            'tenant_id' => $tenant->id,
            'monitored_url_id' => $alpha->id,
            'previous_snapshot_id' => null,
            'current_snapshot_id' => $alphaSnapshot->id,
            'kind' => 'unexpected_status',
            'severity' => 95,
            'status' => 'open',
            'summary' => 'Alpha down',
            'details' => ['http_status' => 500],
            'detected_at' => now(),
            'last_seen_at' => now(),
            'occurrences' => 1,
            'fingerprint' => hash('sha256', 'alpha-incident'),
        ]);

        Incident::query()->create([
            'tenant_id' => $tenant->id,
            'monitored_url_id' => $beta->id,
            'previous_snapshot_id' => null,
            'current_snapshot_id' => $betaSnapshot->id,
            'kind' => 'unexpected_status',
            'severity' => 95,
            'status' => 'open',
            'summary' => 'Beta down',
            'details' => ['http_status' => 500],
            'detected_at' => now(),
            'last_seen_at' => now(),
            'occurrences' => 1,
            'fingerprint' => hash('sha256', 'beta-incident'),
        ]);

        $this->actingAs($user);

        $this->get(route('incidents.index', ['tag' => 'prod']))
            ->assertOk()
            ->assertSee('Alpha down', escape: false)
            ->assertDontSee('Beta down', escape: false);
    }

    public function test_api_can_create_monitor_with_tags(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-11 12:00:00', 'UTC'));

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
            'url' => 'https://example.com/api',
            'display_name' => 'API',
            'tags' => ['Prod', 'team:Payments'],
            'scan_mode' => 'http',
        ]);

        $response->assertCreated();
        $response->assertJsonFragment(['name' => 'prod']);
        $response->assertJsonFragment(['name' => 'team:payments']);
    }
}

