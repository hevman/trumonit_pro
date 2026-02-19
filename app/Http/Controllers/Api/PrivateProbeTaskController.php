<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MonitoredUrl;
use App\Models\PrivateProbe;
use App\Models\PrivateProbeScanTask;
use App\Services\AuditLogService;
use App\Services\PrivateProbeTaskService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PrivateProbeTaskController extends Controller
{
    public function index(Request $request, PrivateProbe $privateProbe)
    {
        $tenantId = (int) ($request->user()?->tenant_id ?? 0);

        if ($tenantId <= 0) {
            return response()->json(['message' => 'User has no tenant.'], 400);
        }

        if ((int) $privateProbe->tenant_id !== $tenantId) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $filters = $request->validate([
            'status' => ['nullable', 'string', Rule::in(['queued', 'leased', 'completed', 'failed'])],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $limit = is_numeric($filters['limit'] ?? null) ? (int) $filters['limit'] : 50;
        $status = is_string($filters['status'] ?? null) ? trim((string) $filters['status']) : null;

        $tasks = PrivateProbeScanTask::query()
            ->where('tenant_id', $tenantId)
            ->where('private_probe_id', $privateProbe->id)
            ->when($status !== null && $status !== '', fn ($query) => $query->where('status', $status))
            ->with(['monitoredUrl:id,tenant_id,url,display_name'])
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (PrivateProbeScanTask $task): array => [
                'id' => $task->id,
                'status' => $task->status,
                'priority' => (int) $task->priority,
                'monitored_url_id' => (int) $task->monitored_url_id,
                'monitored_url' => [
                    'id' => (int) ($task->monitoredUrl?->id ?? 0),
                    'url' => (string) ($task->monitoredUrl?->url ?? ''),
                    'display_name' => $task->monitoredUrl?->display_name,
                ],
                'claimed_at' => optional($task->claimed_at)->toISOString(),
                'lease_expires_at' => optional($task->lease_expires_at)->toISOString(),
                'result_received_at' => optional($task->result_received_at)->toISOString(),
                'completed_at' => optional($task->completed_at)->toISOString(),
                'error_message' => $task->error_message,
                'created_at' => optional($task->created_at)->toISOString(),
            ])
            ->values();

        return response()->json([
            'private_probe_id' => $privateProbe->id,
            'tasks' => $tasks,
        ]);
    }

    public function store(Request $request, PrivateProbe $privateProbe)
    {
        if (! (bool) config('wids.private_probes.enabled', false)) {
            return response()->json(['message' => 'Private probes are disabled.'], 404);
        }

        $tenantId = (int) ($request->user()?->tenant_id ?? 0);

        if ($tenantId <= 0) {
            return response()->json(['message' => 'User has no tenant.'], 400);
        }

        if ((int) $privateProbe->tenant_id !== $tenantId) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $data = $request->validate([
            'monitored_url_id' => ['required', 'integer', 'min:1'],
            'priority' => ['nullable', 'integer', 'min:1', 'max:100'],
            'meta' => ['nullable', 'array'],
        ]);

        $monitor = MonitoredUrl::query()
            ->where('tenant_id', $tenantId)
            ->findOrFail((int) $data['monitored_url_id']);

        $task = app(PrivateProbeTaskService::class)->queueTask(
            privateProbe: $privateProbe,
            monitoredUrl: $monitor,
            requestedByUserId: $request->user()?->id,
            priority: is_numeric($data['priority'] ?? null) ? (int) $data['priority'] : 50,
            meta: is_array($data['meta'] ?? null) ? $data['meta'] : null
        );

        app(AuditLogService::class)->log(
            tenantId: $tenantId,
            actorUserId: $request->user()?->id,
            action: 'private_probe.task_queued',
            auditableType: PrivateProbeScanTask::class,
            auditableId: $task->id,
            data: [
                'private_probe_id' => $privateProbe->id,
                'monitored_url_id' => $monitor->id,
                'priority' => (int) $task->priority,
            ],
            request: $request,
        );

        return response()->json([
            'task' => [
                'id' => $task->id,
                'status' => $task->status,
                'priority' => (int) $task->priority,
                'private_probe_id' => $task->private_probe_id,
                'monitored_url_id' => $task->monitored_url_id,
                'created_at' => optional($task->created_at)->toISOString(),
            ],
        ], 201);
    }
}
