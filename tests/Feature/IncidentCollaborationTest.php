<?php

namespace Tests\Feature;

use App\Models\Incident;
use App\Models\MonitoredUrl;
use App\Models\Tenant;
use App\Models\UrlSnapshot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class IncidentCollaborationTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_owner_can_assign_and_snooze_incident(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-11 13:00:00', 'UTC'));

        $context = $this->createIncidentContext('web-owner');
        $tenant = $context['tenant'];
        $owner = $context['owner'];
        $incident = $context['incident'];

        $assignee = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'member',
        ]);

        $this->actingAs($owner);

        $response = $this->patch(route('incidents.update', $incident), [
            'assigned_user_id' => $assignee->id,
            'snooze_minutes' => 45,
        ]);

        $response->assertRedirect();

        $incident->refresh();
        $this->assertSame($assignee->id, $incident->assigned_user_id);
        $this->assertNotNull($incident->snoozed_until);
        $this->assertSame(now()->addMinutes(45)->toDateTimeString(), $incident->snoozed_until?->toDateTimeString());
    }

    public function test_web_owner_cannot_assign_user_from_other_tenant(): void
    {
        $contextA = $this->createIncidentContext('tenant-a');
        $ownerA = $contextA['owner'];
        $incidentA = $contextA['incident'];

        $contextB = $this->createIncidentContext('tenant-b');
        $ownerB = $contextB['owner'];

        $this->actingAs($ownerA);

        $response = $this->patch(route('incidents.update', $incidentA), [
            'assigned_user_id' => $ownerB->id,
        ]);

        $response->assertSessionHasErrors('assigned_user_id');

        $incidentA->refresh();
        $this->assertNull($incidentA->assigned_user_id);
    }

    public function test_web_user_can_add_incident_comment(): void
    {
        $context = $this->createIncidentContext('web-comment');
        $tenant = $context['tenant'];
        $incident = $context['incident'];

        $member = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'member',
        ]);

        $this->actingAs($member);

        $response = $this->post(route('incidents.comments.store', $incident), [
            'body' => 'Please verify this change.',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('incident_comments', [
            'tenant_id' => $tenant->id,
            'incident_id' => $incident->id,
            'author_user_id' => $member->id,
            'body' => 'Please verify this change.',
        ]);
    }

    public function test_web_incidents_index_can_filter_by_assignee_and_snoozed(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-11 13:00:00', 'UTC'));

        $context = $this->createIncidentContext('web-filter');
        $tenant = $context['tenant'];
        $owner = $context['owner'];
        $incident = $context['incident'];

        $assignee = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'member',
        ]);

        $incident->forceFill([
            'summary' => 'Assigned and snoozed',
            'assigned_user_id' => $assignee->id,
            'snoozed_until' => now()->addMinutes(30),
        ])->save();

        $otherMonitor = MonitoredUrl::query()->create([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $owner->id,
            'url' => 'https://other.example.com',
            'display_name' => 'Other',
            'is_active' => true,
            'frequency_minutes' => 1440,
            'timeout_seconds' => 30,
            'follow_redirects' => true,
            'expected_status_min' => 200,
            'expected_status_max' => 399,
        ]);

        $otherSnapshot = UrlSnapshot::query()->create([
            'monitored_url_id' => $otherMonitor->id,
            'fetched_at' => now(),
            'request_url' => $otherMonitor->url,
            'final_url' => $otherMonitor->url,
            'http_status' => 500,
            'headers' => [],
            'response_time_ms' => 140,
            'body_size' => 120,
            'body_sha256' => hash('sha256', 'other-body'),
        ]);

        Incident::query()->create([
            'tenant_id' => $tenant->id,
            'monitored_url_id' => $otherMonitor->id,
            'previous_snapshot_id' => null,
            'current_snapshot_id' => $otherSnapshot->id,
            'kind' => 'unexpected_status',
            'severity' => 90,
            'status' => 'open',
            'summary' => 'Unassigned incident',
            'details' => ['http_status' => 500],
            'detected_at' => now(),
            'last_seen_at' => now(),
            'occurrences' => 1,
            'fingerprint' => hash('sha256', 'fp-other-'.(string) now()),
        ]);

        $this->actingAs($owner);

        $response = $this->get(route('incidents.index', [
            'assigned_user_id' => $assignee->id,
            'snoozed' => '1',
        ]));

        $response->assertOk();
        $response->assertSee('Assigned and snoozed');
        $response->assertDontSee('Unassigned incident');
    }

    public function test_api_owner_can_assign_and_snooze_incident(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-11 13:00:00', 'UTC'));

        $context = $this->createIncidentContext('api-owner');
        $tenant = $context['tenant'];
        $owner = $context['owner'];
        $incident = $context['incident'];

        $assignee = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'member',
        ]);

        Sanctum::actingAs($owner, ['wids.read', 'wids.write']);

        $response = $this->patchJson('/api/incidents/'.$incident->id, [
            'assigned_user_id' => $assignee->id,
            'snooze_minutes' => 30,
        ]);

        $response->assertOk();
        $response->assertJsonPath('assigned_user_id', $assignee->id);
        $response->assertJsonPath('assigned_to_user.id', $assignee->id);

        $incident->refresh();
        $this->assertSame($assignee->id, $incident->assigned_user_id);
        $this->assertSame(now()->addMinutes(30)->toDateTimeString(), $incident->snoozed_until?->toDateTimeString());
    }

    public function test_api_owner_cannot_assign_user_from_other_tenant(): void
    {
        $contextA = $this->createIncidentContext('api-tenant-a');
        $ownerA = $contextA['owner'];
        $incidentA = $contextA['incident'];

        $contextB = $this->createIncidentContext('api-tenant-b');
        $ownerB = $contextB['owner'];

        Sanctum::actingAs($ownerA, ['wids.read', 'wids.write']);

        $response = $this->patchJson('/api/incidents/'.$incidentA->id, [
            'assigned_user_id' => $ownerB->id,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('assigned_user_id');

        $incidentA->refresh();
        $this->assertNull($incidentA->assigned_user_id);
    }

    public function test_api_member_can_add_incident_comment(): void
    {
        $context = $this->createIncidentContext('api-comment');
        $tenant = $context['tenant'];
        $incident = $context['incident'];

        $member = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'member',
        ]);

        Sanctum::actingAs($member, ['wids.read', 'wids.write']);

        $response = $this->postJson('/api/incidents/'.$incident->id.'/comments', [
            'body' => 'Observed from API.',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('body', 'Observed from API.');
        $response->assertJsonPath('author.id', $member->id);

        $this->assertDatabaseHas('incident_comments', [
            'tenant_id' => $tenant->id,
            'incident_id' => $incident->id,
            'author_user_id' => $member->id,
            'body' => 'Observed from API.',
        ]);
    }

    /**
     * @return array{tenant: Tenant, owner: User, incident: Incident}
     */
    private function createIncidentContext(string $label): array
    {
        $tenant = Tenant::query()->create([
            'name' => 'Tenant '.$label,
            'slug' => 'tenant-'.$label.'-'.Str::lower(Str::random(6)),
        ]);

        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
        ]);

        $monitoredUrl = MonitoredUrl::query()->create([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $owner->id,
            'url' => 'https://'.$label.'.example.com',
            'display_name' => 'Monitor '.$label,
            'is_active' => true,
            'frequency_minutes' => 1440,
            'timeout_seconds' => 30,
            'follow_redirects' => true,
            'expected_status_min' => 200,
            'expected_status_max' => 399,
        ]);

        $snapshot = UrlSnapshot::query()->create([
            'monitored_url_id' => $monitoredUrl->id,
            'fetched_at' => now(),
            'request_url' => $monitoredUrl->url,
            'final_url' => $monitoredUrl->url,
            'http_status' => 500,
            'headers' => [],
            'response_time_ms' => 120,
            'body_size' => 100,
            'body_sha256' => hash('sha256', 'body-'.$label),
        ]);

        $incident = Incident::query()->create([
            'tenant_id' => $tenant->id,
            'monitored_url_id' => $monitoredUrl->id,
            'previous_snapshot_id' => null,
            'current_snapshot_id' => $snapshot->id,
            'kind' => 'unexpected_status',
            'severity' => 90,
            'status' => 'open',
            'summary' => 'Incident '.$label,
            'details' => ['http_status' => 500],
            'detected_at' => now(),
            'last_seen_at' => now(),
            'occurrences' => 1,
            'fingerprint' => hash('sha256', 'fp-'.$label.'-'.(string) now()),
        ]);

        return [
            'tenant' => $tenant,
            'owner' => $owner,
            'incident' => $incident,
        ];
    }
}

