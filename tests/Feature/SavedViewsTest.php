<?php

namespace Tests\Feature;

use App\Models\Incident;
use App\Models\MonitoredUrl;
use App\Models\SavedView;
use App\Models\Tenant;
use App\Models\UrlSnapshot;
use App\Models\User;
use App\Services\TagService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SavedViewsTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_save_incident_view_and_use_it(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-11 12:00:00', 'UTC'));

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
            'url' => 'https://example.com',
            'display_name' => 'Main',
            'is_active' => true,
            'frequency_minutes' => 5,
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
            'response_time_ms' => 140,
            'body_size' => 100,
            'body_sha256' => hash('sha256', 'x'),
            'error_message' => 'Timeout',
        ]);

        Incident::query()->create([
            'tenant_id' => $tenant->id,
            'monitored_url_id' => $monitor->id,
            'current_snapshot_id' => $snapshot->id,
            'kind' => 'fetch_error',
            'severity' => 80,
            'status' => 'open',
            'summary' => 'Open fetch issue',
            'details' => ['error' => 'Timeout'],
            'detected_at' => now(),
            'last_seen_at' => now(),
            'occurrences' => 1,
            'fingerprint' => hash('sha256', 'a'),
        ]);

        Incident::query()->create([
            'tenant_id' => $tenant->id,
            'monitored_url_id' => $monitor->id,
            'current_snapshot_id' => $snapshot->id,
            'kind' => 'content_change',
            'severity' => 50,
            'status' => 'resolved',
            'summary' => 'Resolved change',
            'details' => ['changed' => true],
            'detected_at' => now(),
            'last_seen_at' => now(),
            'occurrences' => 1,
            'resolved_at' => now(),
            'fingerprint' => hash('sha256', 'b'),
        ]);

        $this->actingAs($owner);

        $response = $this->post(route('saved-views.store'), [
            'scope' => 'incidents',
            'name' => 'Open fetch',
            'filters' => [
                'status' => 'open',
                'kind' => 'fetch_error',
            ],
        ]);

        $response->assertRedirect(route('incidents.index', [
            'status' => 'open',
            'kind' => 'fetch_error',
        ]));

        $this->assertDatabaseHas('saved_views', [
            'tenant_id' => $tenant->id,
            'scope' => 'incidents',
            'name' => 'Open fetch',
        ]);

        $list = $this->get(route('incidents.index'));
        $list->assertOk();
        $list->assertSee('Open fetch');

        $filtered = $this->get(route('incidents.index', [
            'status' => 'open',
            'kind' => 'fetch_error',
        ]));
        $filtered->assertOk();
        $filtered->assertSee('Open fetch issue');
        $filtered->assertDontSee('Resolved change');
    }

    public function test_owner_can_save_and_delete_monitored_url_view(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-11 12:00:00', 'UTC'));

        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
        ]);

        $prod = MonitoredUrl::query()->create([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $owner->id,
            'url' => 'https://prod.example.com',
            'display_name' => 'Prod monitor',
            'is_active' => true,
            'frequency_minutes' => 5,
            'timeout_seconds' => 30,
            'follow_redirects' => true,
            'expected_status_min' => 200,
            'expected_status_max' => 399,
        ]);

        $staging = MonitoredUrl::query()->create([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $owner->id,
            'url' => 'https://staging.example.com',
            'display_name' => 'Staging monitor',
            'is_active' => true,
            'frequency_minutes' => 5,
            'timeout_seconds' => 30,
            'follow_redirects' => true,
            'expected_status_min' => 200,
            'expected_status_max' => 399,
        ]);

        app(TagService::class)->syncMonitoredUrlTags($prod, ['prod']);
        app(TagService::class)->syncMonitoredUrlTags($staging, ['staging']);

        $this->actingAs($owner);

        $store = $this->post(route('saved-views.store'), [
            'scope' => 'monitored_urls',
            'name' => 'Production only',
            'filters' => [
                'tag' => 'prod',
            ],
        ]);

        $store->assertRedirect(route('monitored-urls.index', ['tag' => 'prod']));

        $savedView = SavedView::query()
            ->where('tenant_id', $tenant->id)
            ->where('scope', 'monitored_urls')
            ->where('name', 'Production only')
            ->firstOrFail();

        $list = $this->get(route('monitored-urls.index'));
        $list->assertOk();
        $list->assertSee('Production only');

        $filtered = $this->get(route('monitored-urls.index', ['tag' => 'prod']));
        $filtered->assertOk();
        $filtered->assertSee('Prod monitor');
        $filtered->assertDontSee('Staging monitor');

        $delete = $this->delete(route('saved-views.destroy', $savedView));
        $delete->assertRedirect(route('monitored-urls.index'));

        $this->assertDatabaseMissing('saved_views', [
            'id' => $savedView->id,
        ]);
    }

    public function test_member_cannot_manage_saved_views(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-11 12:00:00', 'UTC'));

        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
        ]);

        $member = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'member',
        ]);

        $savedView = SavedView::query()->create([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $owner->id,
            'scope' => 'incidents',
            'name' => 'Open only',
            'filters' => ['status' => 'open'],
        ]);

        $this->actingAs($member);

        $this->post(route('saved-views.store'), [
            'scope' => 'incidents',
            'name' => 'Member attempt',
            'filters' => ['status' => 'open'],
        ])->assertForbidden();

        $this->get(route('saved-views.index'))->assertForbidden();
        $this->patch(route('saved-views.update', $savedView), ['name' => 'Nope'])->assertForbidden();
        $this->delete(route('saved-views.destroy', $savedView))->assertForbidden();
    }

    public function test_owner_can_save_incident_view_with_assignment_and_snooze_filters(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-11 12:00:00', 'UTC'));

        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
        ]);

        $assignee = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'member',
        ]);

        $this->actingAs($owner);

        $response = $this->post(route('saved-views.store'), [
            'scope' => 'incidents',
            'name' => 'Assigned snoozed',
            'filters' => [
                'status' => 'open',
                'assigned_user_id' => (string) $assignee->id,
                'snoozed' => '1',
            ],
        ]);

        $response->assertRedirect(route('incidents.index', [
            'status' => 'open',
            'assigned_user_id' => $assignee->id,
            'snoozed' => '1',
        ]));

        $savedView = SavedView::query()
            ->where('tenant_id', $tenant->id)
            ->where('scope', 'incidents')
            ->where('name', 'Assigned snoozed')
            ->firstOrFail();

        $this->assertSame('open', $savedView->filters['status'] ?? null);
        $this->assertSame($assignee->id, $savedView->filters['assigned_user_id'] ?? null);
        $this->assertSame('1', $savedView->filters['snoozed'] ?? null);
    }

    public function test_owner_can_save_and_manage_advanced_incident_search_view(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-11 12:00:00', 'UTC'));

        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
        ]);

        $this->actingAs($owner);

        $store = $this->post(route('saved-views.store'), [
            'scope' => 'incidents',
            'name' => 'Advanced incidents',
            'filters' => [
                'status' => 'open',
                'has_comments' => '1',
                'occurrences_min' => 5,
                'detected_from' => '2026-02-10',
                'detected_to' => '2026-02-12',
            ],
        ]);

        $store->assertRedirect(route('incidents.index', [
            'status' => 'open',
            'has_comments' => '1',
            'occurrences_min' => 5,
            'detected_from' => '2026-02-10',
            'detected_to' => '2026-02-12',
        ]));

        $savedView = SavedView::query()
            ->where('tenant_id', $tenant->id)
            ->where('scope', 'incidents')
            ->where('name', 'Advanced incidents')
            ->firstOrFail();

        $manage = $this->get(route('saved-views.index', ['scope' => 'incidents']));
        $manage->assertOk();
        $manage->assertSee('Advanced incidents');

        $rename = $this->patch(route('saved-views.update', $savedView), [
            'name' => 'Advanced incidents v2',
        ]);
        $rename->assertRedirect();

        $savedView->refresh();
        $this->assertSame('Advanced incidents v2', $savedView->name);
        $this->assertSame('1', $savedView->filters['has_comments'] ?? null);
        $this->assertSame(5, $savedView->filters['occurrences_min'] ?? null);
    }
}
