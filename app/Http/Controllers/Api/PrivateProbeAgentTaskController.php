<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PrivateProbe;
use App\Models\PrivateProbeScanTask;
use App\Services\PrivateProbeTaskService;
use Illuminate\Http\Request;

class PrivateProbeAgentTaskController extends Controller
{
    public function claim(Request $request)
    {
        /** @var PrivateProbe|null $probe */
        $probe = $request->attributes->get('privateProbe');

        if (! $probe) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        $claimed = app(PrivateProbeTaskService::class)->claimNextTask($probe);

        if (! is_array($claimed)) {
            return response()->noContent();
        }

        /** @var PrivateProbeScanTask $task */
        $task = $claimed['task'];
        $leaseToken = (string) ($claimed['lease_token'] ?? '');
        $monitor = $task->monitoredUrl;

        return response()->json([
            'task' => [
                'id' => $task->id,
                'status' => $task->status,
                'priority' => (int) $task->priority,
                'lease_expires_at' => optional($task->lease_expires_at)->toISOString(),
                'signature_alg' => 'hmac-sha256',
                'lease_token' => $leaseToken,
                'monitored_url' => [
                    'id' => (int) ($monitor?->id ?? 0),
                    'url' => (string) ($monitor?->url ?? ''),
                    'display_name' => $monitor?->display_name,
                    'expected_status_min' => (int) ($monitor?->expected_status_min ?? 200),
                    'expected_status_max' => (int) ($monitor?->expected_status_max ?? 399),
                    'timeout_seconds' => (int) ($monitor?->timeout_seconds ?? 30),
                    'follow_redirects' => (bool) ($monitor?->follow_redirects ?? true),
                    'scan_mode' => (string) ($monitor?->scan_mode ?? 'http'),
                ],
                'meta' => is_array($task->meta) ? $task->meta : [],
            ],
        ]);
    }

    public function result(Request $request, PrivateProbeScanTask $privateProbeScanTask)
    {
        /** @var PrivateProbe|null $probe */
        $probe = $request->attributes->get('privateProbe');

        if (! $probe) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        $data = $request->validate([
            'lease_token' => ['required', 'string', 'min:20', 'max:255'],
            'result' => ['required', 'array'],
        ]);

        $signature = $request->header('X-Wids-Task-Signature');
        $rawBody = (string) $request->getContent();

        $task = app(PrivateProbeTaskService::class)->completeTask(
            privateProbe: $probe,
            task: $privateProbeScanTask,
            leaseToken: (string) $data['lease_token'],
            signature: is_string($signature) ? $signature : null,
            rawBody: $rawBody,
            resultPayload: is_array($data['result']) ? $data['result'] : [],
            requestIp: $request->ip(),
            requestUserAgent: $request->userAgent(),
        );

        return response()->json([
            'ok' => true,
            'task' => [
                'id' => $task->id,
                'status' => $task->status,
                'result_received_at' => optional($task->result_received_at)->toISOString(),
                'completed_at' => optional($task->completed_at)->toISOString(),
            ],
        ]);
    }
}
