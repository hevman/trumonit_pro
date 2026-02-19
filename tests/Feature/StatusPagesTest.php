<?php

namespace Tests\Feature;

use App\Models\Incident;
use App\Models\MonitoredUrl;
use App\Models\StatusPage;
use App\Models\Tenant;
use App\Models\UrlSnapshot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class StatusPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_status_page_and_public_page_renders_statuses(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-11 12:00:00', 'UTC'));
        Cache::flush();

        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
        ]);

        $up = MonitoredUrl::query()->create([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $owner->id,
            'url' => 'https://example.com/up',
            'display_name' => 'Website',
            'is_active' => true,
            'frequency_minutes' => 5,
            'timeout_seconds' => 30,
            'follow_redirects' => true,
            'expected_status_min' => 200,
            'expected_status_max' => 399,
        ]);

        $down = MonitoredUrl::query()->create([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $owner->id,
            'url' => 'https://example.com/down',
            'display_name' => 'API',
            'is_active' => true,
            'frequency_minutes' => 5,
            'timeout_seconds' => 30,
            'follow_redirects' => true,
            'expected_status_min' => 200,
            'expected_status_max' => 399,
        ]);

        $upSnapshot = UrlSnapshot::query()->create([
            'monitored_url_id' => $up->id,
            'fetched_at' => now()->subMinute(),
            'request_url' => $up->url,
            'final_url' => $up->url,
            'http_status' => 200,
            'headers' => [],
            'response_time_ms' => 100,
            'body_size' => 10,
            'body_sha256' => hash('sha256', 'ok'),
        ]);

        $downSnapshot = UrlSnapshot::query()->create([
            'monitored_url_id' => $down->id,
            'fetched_at' => now(),
            'request_url' => $down->url,
            'final_url' => $down->url,
            'http_status' => 500,
            'headers' => [],
            'response_time_ms' => 120,
            'body_size' => 10,
            'body_sha256' => hash('sha256', 'err'),
            'error_message' => 'Timeout',
        ]);

        Incident::query()->create([
            'tenant_id' => $tenant->id,
            'monitored_url_id' => $down->id,
            'previous_snapshot_id' => null,
            'current_snapshot_id' => $downSnapshot->id,
            'kind' => 'fetch_error',
            'severity' => 90,
            'status' => 'open',
            'summary' => 'API outage today',
            'details' => ['error' => 'timeout'],
            'detected_at' => now()->subHours(2),
            'last_seen_at' => now()->subHours(2),
            'occurrences' => 1,
            'fingerprint' => hash('sha256', 'public-status-incident-today'),
        ]);

        Incident::query()->create([
            'tenant_id' => $tenant->id,
            'monitored_url_id' => $up->id,
            'previous_snapshot_id' => null,
            'current_snapshot_id' => $upSnapshot->id,
            'kind' => 'content_change',
            'severity' => 40,
            'status' => 'resolved',
            'summary' => 'Issue yesterday',
            'details' => ['changed' => true],
            'detected_at' => now()->subDay()->subHour(),
            'last_seen_at' => now()->subDay()->subHour(),
            'resolved_at' => now()->subDay(),
            'occurrences' => 1,
            'fingerprint' => hash('sha256', 'public-status-incident-yesterday'),
        ]);

        $this->actingAs($owner);

        $response = $this->post(route('status-pages.store'), [
            'title' => 'Acme Status',
            'slug' => '',
            'custom_domain' => 'status.acme.test',
            'cache_ttl_seconds' => 120,
            'description' => 'Public status for Acme',
            'is_public' => 1,
            'monitored_url_ids' => [$up->id, $down->id],
        ]);

        $statusPage = StatusPage::query()->where('tenant_id', $tenant->id)->firstOrFail();

        $response->assertRedirect(route('status-pages.show', $statusPage));

        $this->get(route('status-pages.index'))->assertOk()->assertSee('Acme Status');

        $public = $this->get(route('status-pages.public.show', $statusPage->slug));
        $public->assertOk();
        $public->assertHeader('X-Status-Cache-TTL', '120');
        $cacheControl = (string) $public->headers->get('Cache-Control');
        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('max-age=120', $cacheControl);
        $this->assertStringContainsString('stale-while-revalidate=120', $cacheControl);
        $this->assertStringContainsString('stale-if-error=120', $cacheControl);
        $public->assertSee('Acme Status');
        $public->assertSee('UP');
        $public->assertSee('DOWN');
        $public->assertSee('Some systems are experiencing issues.');
        $public->assertSee('Incident trend (7 days)');
        $public->assertSee('Click a bar to filter recent incidents by day.');
        $public->assertSee('incident_day='.now()->toDateString(), false);
        $public->assertSee('Components filter');
        $public->assertSee('component_state=down', false);
        $public->assertSee('API outage today');
        $public->assertSee('Issue yesterday');

        $filteredPublic = $this->get(route('status-pages.public.show', [
            'slug' => $statusPage->slug,
            'incident_day' => now()->toDateString(),
        ]));

        $filteredPublic->assertOk();
        $filteredPublic->assertSee('Filtered day: '.now()->format('d M Y'));
        $filteredPublic->assertSee('API outage today');
        $filteredPublic->assertDontSee('Issue yesterday');

        $downComponents = $this->get(route('status-pages.public.show', [
            'slug' => $statusPage->slug,
            'component_state' => 'down',
        ]));

        $downComponents->assertOk();
        $downComponents->assertSee('Filtered components: DOWN');
        $downComponents->assertSee('API');
        $downComponents->assertDontSee('Website');
        $downComponents->assertSee('API outage today');
        $downComponents->assertDontSee('Issue yesterday');

        $this->assertSame('status.acme.test', $statusPage->custom_domain);
        $this->assertSame(120, $statusPage->cache_ttl_seconds);

        $this->post(route('logout'))->assertRedirect('/');

        $customDomain = $this->get('http://status.acme.test/');

        $customDomain->assertOk();
        $customDomain->assertSee('Acme Status');
        $customDomain->assertHeader('X-Status-Cache-TTL', '120');
    }

    public function test_public_status_page_returns_404_when_not_public(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-11 12:00:00', 'UTC'));
        Cache::flush();

        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
        ]);

        $page = StatusPage::query()->create([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $owner->id,
            'title' => 'Acme Private',
            'slug' => 'acme-private',
            'description' => null,
            'is_public' => false,
        ]);

        $this->get(route('status-pages.public.show', $page->slug))->assertNotFound();
    }

    public function test_member_can_view_index_but_cannot_create(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-11 12:00:00', 'UTC'));

        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        $member = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'member',
        ]);

        $this->actingAs($member);

        $this->get(route('status-pages.index'))->assertOk();
        $this->get(route('status-pages.create'))->assertForbidden();
    }
}
