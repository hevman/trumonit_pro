<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Incident;
use App\Models\IncidentComment;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\TagService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class IncidentController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = $request->user()?->tenant_id;

        if ($tenantId === null) {
            return response()->json(['message' => 'User has no tenant.'], 400);
        }

        $filters = $request->validate([
            'status' => ['nullable', 'string', Rule::in(['open', 'ack', 'resolved'])],
            'kind' => ['nullable', 'string', 'max:50'],
            'monitored_url_id' => ['nullable', 'integer', 'min:1'],
            'assigned_user_id' => ['nullable', 'integer', 'min:1'],
            'snoozed' => ['nullable', 'string', Rule::in(['1', '0'])],
            'tag' => ['nullable', 'string', 'max:64'],
            'severity_min' => ['nullable', 'integer', 'min:1', 'max:100'],
            'severity_max' => ['nullable', 'integer', 'min:1', 'max:100'],
            'q' => ['nullable', 'string', 'max:200'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $perPage = (int) ($filters['per_page'] ?? 50);

        $status = $filters['status'] ?? null;
        $kind = $filters['kind'] ?? null;
        $monitoredUrlId = is_numeric($filters['monitored_url_id'] ?? null) ? (int) $filters['monitored_url_id'] : null;
        $assignedUserId = is_numeric($filters['assigned_user_id'] ?? null) ? (int) $filters['assigned_user_id'] : null;
        $snoozedFilter = is_string($filters['snoozed'] ?? null) ? (string) $filters['snoozed'] : null;
        $tagInput = is_string($filters['tag'] ?? null) ? trim((string) $filters['tag']) : null;
        $tag = null;
        $severityMin = is_numeric($filters['severity_min'] ?? null) ? (int) $filters['severity_min'] : null;
        $severityMax = is_numeric($filters['severity_max'] ?? null) ? (int) $filters['severity_max'] : null;
        $search = is_string($filters['q'] ?? null) ? trim((string) $filters['q']) : null;
        $searchId = null;
        $now = now();

        if (is_string($search) && $search !== '') {
            $candidate = ltrim($search, '#');

            if (ctype_digit($candidate)) {
                $searchId = (int) $candidate;
            }
        }

        if (is_string($tagInput) && $tagInput !== '') {
            $tag = app(TagService::class)->normalize($tagInput);

            if ($tag === null) {
                throw ValidationException::withMessages([
                    'tag' => ['Invalid tag. Use a-z0-9:_- (max 32 chars).'],
                ]);
            }
        }

        if (is_int($severityMin) && is_int($severityMax) && $severityMin > $severityMax) {
            [$severityMin, $severityMax] = [$severityMax, $severityMin];
        }

        return Incident::query()
            ->with(['assignedToUser:id,name,email'])
            ->where('tenant_id', $tenantId)
            ->when(is_string($status) && $status !== '', fn ($q) => $q->where('status', $status))
            ->when(is_string($kind) && $kind !== '', fn ($q) => $q->where('kind', $kind))
            ->when($monitoredUrlId !== null, fn ($q) => $q->where('monitored_url_id', $monitoredUrlId))
            ->when($assignedUserId !== null, fn ($q) => $q->where('assigned_user_id', $assignedUserId))
            ->when($snoozedFilter === '1', fn ($q) => $q->whereNotNull('snoozed_until')->where('snoozed_until', '>', $now))
            ->when($snoozedFilter === '0', fn ($q) => $q->where(function ($qq) use ($now): void {
                $qq->whereNull('snoozed_until')
                    ->orWhere('snoozed_until', '<=', $now);
            }))
            ->when(is_string($tag) && $tag !== '', fn ($q) => $q->whereHas('monitoredUrl.tags', fn ($tq) => $tq->where('name', $tag)))
            ->when($severityMin !== null, fn ($q) => $q->where('severity', '>=', $severityMin))
            ->when($severityMax !== null, fn ($q) => $q->where('severity', '<=', $severityMax))
            ->when(is_string($search) && $search !== '', function ($query) use ($search, $searchId): void {
                $needle = mb_strtolower($search);

                $query->where(function ($q) use ($needle, $searchId): void {
                    if (is_int($searchId)) {
                        $q->orWhere('id', $searchId);
                    }

                    $q->orWhereRaw('LOWER(summary) LIKE ?', ['%'.$needle.'%'])
                        ->orWhereRaw('LOWER(kind) LIKE ?', ['%'.$needle.'%'])
                        ->orWhereHas('monitoredUrl', function ($mq) use ($needle): void {
                            $mq->whereRaw('LOWER(url) LIKE ?', ['%'.$needle.'%'])
                                ->orWhereRaw('LOWER(display_name) LIKE ?', ['%'.$needle.'%']);
                        });
                });
            })
            ->orderByDesc('detected_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function update(Request $request, int $id)
    {
        $tenantId = $request->user()?->tenant_id;

        if ($tenantId === null) {
            return response()->json(['message' => 'User has no tenant.'], 400);
        }

        $data = $request->validate([
            'status' => ['nullable', 'string', Rule::in(['open', 'ack', 'resolved'])],
            'assigned_user_id' => ['nullable', 'integer', 'min:1'],
            'clear_assignment' => ['nullable', 'boolean'],
            'snooze_minutes' => ['nullable', 'integer', 'min:0', 'max:10080'],
            'snoozed_until' => ['nullable', 'date', 'after:now'],
            'clear_snooze' => ['nullable', 'boolean'],
        ]);

        $incident = Incident::query()
            ->where('tenant_id', $tenantId)
            ->findOrFail($id);

        $statusProvided = array_key_exists('status', $data);
        $assignmentProvided = array_key_exists('assigned_user_id', $data) || ((bool) ($data['clear_assignment'] ?? false));
        $snoozeProvided = array_key_exists('snooze_minutes', $data)
            || array_key_exists('snoozed_until', $data)
            || ((bool) ($data['clear_snooze'] ?? false));

        if (! $statusProvided && ! $assignmentProvided && ! $snoozeProvided) {
            throw ValidationException::withMessages([
                'status' => ['Provide at least one update field.'],
            ]);
        }

        $beforeStatus = (string) $incident->status;
        $beforeAssignedUserId = $incident->assigned_user_id !== null ? (int) $incident->assigned_user_id : null;
        $beforeSnoozedUntilIso = $incident->snoozed_until?->toISOString();

        $updates = [];

        if ($statusProvided) {
            $newStatus = (string) $data['status'];
            $resolvedAt = $incident->resolved_at;

            if ($newStatus === 'resolved') {
                $resolvedAt ??= now();
            } else {
                $resolvedAt = null;
            }

            $updates['status'] = $newStatus;
            $updates['resolved_at'] = $resolvedAt;
        }

        if ($assignmentProvided) {
            $assignedUserId = null;

            if (! ((bool) ($data['clear_assignment'] ?? false)) && array_key_exists('assigned_user_id', $data) && $data['assigned_user_id'] !== null) {
                $candidateId = (int) $data['assigned_user_id'];

                $exists = User::query()
                    ->where('tenant_id', $tenantId)
                    ->where('id', $candidateId)
                    ->exists();

                if (! $exists) {
                    throw ValidationException::withMessages([
                        'assigned_user_id' => ['User not found in this workspace.'],
                    ]);
                }

                $assignedUserId = $candidateId;
            }

            $updates['assigned_user_id'] = $assignedUserId;
        }

        if ($snoozeProvided) {
            $snoozedUntil = null;

            if (! ((bool) ($data['clear_snooze'] ?? false))) {
                if (array_key_exists('snoozed_until', $data) && $data['snoozed_until'] !== null) {
                    $snoozedUntil = Carbon::parse((string) $data['snoozed_until']);
                } elseif (array_key_exists('snooze_minutes', $data)) {
                    $minutes = max(0, (int) ($data['snooze_minutes'] ?? 0));
                    $snoozedUntil = $minutes > 0 ? now()->addMinutes($minutes) : null;
                }
            }

            $updates['snoozed_until'] = $snoozedUntil;
        }

        if ($updates !== []) {
            $incident->forceFill($updates)->save();
        }

        if ($statusProvided && $beforeStatus !== $incident->status) {
            app(AuditLogService::class)->log(
                tenantId: $tenantId,
                actorUserId: $request->user()?->id,
                action: 'incident.status_changed',
                auditableType: Incident::class,
                auditableId: $incident->id,
                data: [
                    'before' => $beforeStatus,
                    'after' => $incident->status,
                ],
                request: $request,
            );
        }

        if ($assignmentProvided && $beforeAssignedUserId !== $incident->assigned_user_id) {
            app(AuditLogService::class)->log(
                tenantId: $tenantId,
                actorUserId: $request->user()?->id,
                action: 'incident.assignment_changed',
                auditableType: Incident::class,
                auditableId: $incident->id,
                data: [
                    'before' => $beforeAssignedUserId,
                    'after' => $incident->assigned_user_id,
                ],
                request: $request,
            );
        }

        $afterSnoozedUntilIso = $incident->snoozed_until?->toISOString();

        if ($snoozeProvided && $beforeSnoozedUntilIso !== $afterSnoozedUntilIso) {
            app(AuditLogService::class)->log(
                tenantId: $tenantId,
                actorUserId: $request->user()?->id,
                action: 'incident.snooze_changed',
                auditableType: Incident::class,
                auditableId: $incident->id,
                data: [
                    'before' => $beforeSnoozedUntilIso,
                    'after' => $afterSnoozedUntilIso,
                ],
                request: $request,
            );
        }

        return response()->json($incident->load('assignedToUser:id,name,email'));
    }

    public function comment(Request $request, int $id)
    {
        $tenantId = $request->user()?->tenant_id;

        if ($tenantId === null) {
            return response()->json(['message' => 'User has no tenant.'], 400);
        }

        $data = $request->validate([
            'body' => ['required', 'string', 'max:4000'],
        ]);

        $incident = Incident::query()
            ->where('tenant_id', $tenantId)
            ->findOrFail($id);

        $body = trim((string) $data['body']);

        if ($body === '') {
            throw ValidationException::withMessages([
                'body' => ['Comment cannot be empty.'],
            ]);
        }

        $comment = IncidentComment::query()->create([
            'tenant_id' => $tenantId,
            'incident_id' => $incident->id,
            'author_user_id' => $request->user()?->id,
            'body' => $body,
        ]);

        app(AuditLogService::class)->log(
            tenantId: $tenantId,
            actorUserId: $request->user()?->id,
            action: 'incident.comment_added',
            auditableType: Incident::class,
            auditableId: $incident->id,
            data: [
                'comment_id' => $comment->id,
                'body_preview' => mb_substr($comment->body, 0, 200),
            ],
            request: $request,
        );

        return response()->json($comment->load('author:id,name,email'), 201);
    }
}
