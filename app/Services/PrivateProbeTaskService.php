<?php

namespace App\Services;

use App\Models\Incident;
use App\Models\MonitoredUrl;
use App\Models\PrivateProbe;
use App\Models\PrivateProbeScanTask;
use App\Models\UrlSnapshot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PrivateProbeTaskService
{
    /**
     * @param  array<string, mixed>|null  $meta
     */
    public function queueTask(
        PrivateProbe $privateProbe,
        MonitoredUrl $monitoredUrl,
        ?int $requestedByUserId = null,
        int $priority = 50,
        ?array $meta = null
    ): PrivateProbeScanTask {
        if ((int) $privateProbe->tenant_id !== (int) $monitoredUrl->tenant_id) {
            throw ValidationException::withMessages([
                'monitored_url_id' => ['Monitor does not belong to probe tenant.'],
            ]);
        }

        if (! $privateProbe->is_active) {
            throw ValidationException::withMessages([
                'private_probe_id' => ['Private probe is inactive.'],
            ]);
        }

        $hasInflightTask = PrivateProbeScanTask::query()
            ->where('tenant_id', $privateProbe->tenant_id)
            ->where('monitored_url_id', $monitoredUrl->id)
            ->where(function ($query): void {
                $query->where('status', 'queued')
                    ->orWhere(function ($leaseQuery): void {
                        $leaseQuery->where('status', 'leased')
                            ->where(function ($q): void {
                                $q->whereNull('lease_expires_at')
                                    ->orWhere('lease_expires_at', '>', now());
                            });
                    });
            })
            ->exists();

        if ($hasInflightTask) {
            throw ValidationException::withMessages([
                'monitored_url_id' => ['Monitor already has an in-flight private probe task.'],
            ]);
        }

        $priority = max(1, min(100, $priority));

        return PrivateProbeScanTask::query()->create([
            'tenant_id' => (int) $privateProbe->tenant_id,
            'private_probe_id' => $privateProbe->id,
            'monitored_url_id' => $monitoredUrl->id,
            'requested_by_user_id' => $requestedByUserId,
            'status' => 'queued',
            'priority' => $priority,
            'meta' => is_array($meta) ? $meta : null,
        ]);
    }

    /**
     * @return array{task: PrivateProbeScanTask, lease_token: string}|null
     */
    public function claimNextTask(PrivateProbe $privateProbe): ?array
    {
        if (! $privateProbe->is_active) {
            return null;
        }

        $leaseSeconds = max(30, min(3600, (int) config('wids.private_probes.lease_seconds', 180)));
        $driver = DB::connection()->getDriverName();

        return DB::transaction(function () use ($privateProbe, $leaseSeconds, $driver): ?array {
            $updateTimestamp = now();

            PrivateProbeScanTask::query()
                ->where('tenant_id', $privateProbe->tenant_id)
                ->where('private_probe_id', $privateProbe->id)
                ->where('status', 'leased')
                ->whereNotNull('lease_expires_at')
                ->where('lease_expires_at', '<=', $updateTimestamp)
                ->update([
                    'status' => 'queued',
                    'lease_token_hash' => null,
                    'lease_expires_at' => null,
                    'claimed_at' => null,
                    'updated_at' => $updateTimestamp,
                ]);

            $query = PrivateProbeScanTask::query()
                ->where('tenant_id', $privateProbe->tenant_id)
                ->where('private_probe_id', $privateProbe->id)
                ->where('status', 'queued')
                ->orderBy('priority')
                ->orderBy('id');

            if (in_array($driver, ['pgsql', 'mysql'], true)) {
                $query->lock('for update skip locked');
            } else {
                $query->lockForUpdate();
            }

            /** @var PrivateProbeScanTask|null $task */
            $task = $query->first();

            if (! $task) {
                return null;
            }

            $leaseToken = Str::random(64);

            $task->forceFill([
                'status' => 'leased',
                'lease_token_hash' => hash('sha256', $leaseToken),
                'lease_expires_at' => now()->addSeconds($leaseSeconds),
                'claimed_at' => now(),
            ])->save();

            return [
                'task' => $task->fresh(['monitoredUrl:id,tenant_id,url,display_name,expected_status_min,expected_status_max,timeout_seconds,follow_redirects,scan_mode']),
                'lease_token' => $leaseToken,
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $resultPayload
     */
    public function completeTask(
        PrivateProbe $privateProbe,
        PrivateProbeScanTask $task,
        string $leaseToken,
        ?string $signature,
        string $rawBody,
        array $resultPayload,
        ?string $requestIp = null,
        ?string $requestUserAgent = null,
    ): PrivateProbeScanTask {
        if ((int) $task->tenant_id !== (int) $privateProbe->tenant_id || (int) $task->private_probe_id !== (int) $privateProbe->id) {
            throw ValidationException::withMessages([
                'task_id' => ['Task is not assigned to this probe.'],
            ]);
        }

        if ($task->status !== 'leased') {
            throw ValidationException::withMessages([
                'task_status' => ['Task is not in leased state.'],
            ]);
        }

        if (! $task->lease_expires_at || $task->lease_expires_at->isPast()) {
            throw ValidationException::withMessages([
                'lease_token' => ['Task lease expired. Claim a new task.'],
            ]);
        }

        if ($task->lease_token_hash === null || ! hash_equals((string) $task->lease_token_hash, hash('sha256', $leaseToken))) {
            throw ValidationException::withMessages([
                'lease_token' => ['Invalid lease token.'],
            ]);
        }

        $this->assertSignature($leaseToken, $signature, $rawBody);

        $monitor = MonitoredUrl::query()
            ->where('tenant_id', $task->tenant_id)
            ->find($task->monitored_url_id);

        if (! $monitor) {
            $task->forceFill([
                'status' => 'failed',
                'error_message' => 'Monitored URL not found.',
                'result_payload' => $resultPayload,
                'result_received_at' => now(),
                'completed_at' => now(),
                'lease_token_hash' => null,
                'lease_expires_at' => null,
            ])->save();

            return $task->refresh();
        }

        $snapshot = $this->ingestSnapshot($monitor, $privateProbe, $task, $resultPayload);
        $this->processResultIncidents($monitor, $privateProbe, $task, $snapshot);

        $meta = is_array($task->meta) ? $task->meta : [];
        $meta['result_ingest'] = [
            'snapshot_id' => $snapshot->id,
            'request_ip' => $requestIp,
            'request_user_agent' => $requestUserAgent,
            'ingested_at' => now()->toISOString(),
        ];

        $task->forceFill([
            'status' => 'completed',
            'result_payload' => $resultPayload,
            'result_received_at' => now(),
            'completed_at' => now(),
            'lease_token_hash' => null,
            'lease_expires_at' => null,
            'meta' => $meta,
            'error_message' => null,
        ])->save();

        return $task->refresh();
    }

    private function assertSignature(string $leaseToken, ?string $signature, string $rawBody): void
    {
        $required = (bool) config('wids.private_probes.result_signature_required', true);

        if (! $required) {
            return;
        }

        $signature = is_string($signature) ? trim($signature) : '';

        if ($signature === '') {
            throw ValidationException::withMessages([
                'signature' => ['Missing result signature.'],
            ]);
        }

        $normalized = strtolower($signature);

        if (str_starts_with($normalized, 'sha256=')) {
            $normalized = substr($normalized, 7);
        }

        $expected = hash_hmac('sha256', $rawBody, $leaseToken);

        if (! hash_equals($expected, $normalized)) {
            throw ValidationException::withMessages([
                'signature' => ['Invalid result signature.'],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $resultPayload
     */
    private function ingestSnapshot(
        MonitoredUrl $monitor,
        PrivateProbe $privateProbe,
        PrivateProbeScanTask $task,
        array $resultPayload
    ): UrlSnapshot {
        $fetchedAt = now();

        if (is_string($resultPayload['fetched_at'] ?? null) && trim((string) $resultPayload['fetched_at']) !== '') {
            try {
                $fetchedAt = \Carbon\Carbon::parse((string) $resultPayload['fetched_at']);
            } catch (\Throwable) {
                $fetchedAt = now();
            }
        }

        $requestUrl = is_string($resultPayload['request_url'] ?? null) && trim((string) $resultPayload['request_url']) !== ''
            ? trim((string) $resultPayload['request_url'])
            : (string) $monitor->url;

        $finalUrl = is_string($resultPayload['final_url'] ?? null) && trim((string) $resultPayload['final_url']) !== ''
            ? trim((string) $resultPayload['final_url'])
            : null;

        $httpStatus = is_numeric($resultPayload['http_status'] ?? null) ? (int) $resultPayload['http_status'] : null;
        $httpStatus = is_int($httpStatus) && $httpStatus >= 100 && $httpStatus <= 599 ? $httpStatus : null;

        $responseTimeMs = is_numeric($resultPayload['response_time_ms'] ?? null) ? (int) $resultPayload['response_time_ms'] : null;
        $responseTimeMs = is_int($responseTimeMs) && $responseTimeMs >= 0 ? $responseTimeMs : null;

        $bodySize = is_numeric($resultPayload['body_size'] ?? null) ? (int) $resultPayload['body_size'] : null;
        $bodySize = is_int($bodySize) && $bodySize >= 0 ? $bodySize : null;

        $bodySha256 = is_string($resultPayload['body_sha256'] ?? null) ? strtolower(trim((string) $resultPayload['body_sha256'])) : null;
        $bodySha256 = is_string($bodySha256) && preg_match('/\A[0-9a-f]{64}\z/', $bodySha256) ? $bodySha256 : null;

        $headers = is_array($resultPayload['headers'] ?? null) ? $resultPayload['headers'] : null;
        $errorMessage = is_string($resultPayload['error_message'] ?? null) ? trim((string) $resultPayload['error_message']) : null;
        $errorMessage = $errorMessage !== '' ? $errorMessage : null;

        $meta = is_array($resultPayload['meta'] ?? null) ? $resultPayload['meta'] : [];
        $meta['source'] = 'private_probe';
        $meta['private_probe'] = [
            'probe_id' => $privateProbe->id,
            'probe_name' => $privateProbe->name,
            'task_id' => $task->id,
        ];

        return UrlSnapshot::query()->create([
            'monitored_url_id' => $monitor->id,
            'fetched_at' => $fetchedAt,
            'request_url' => $requestUrl,
            'final_url' => $finalUrl,
            'http_status' => $httpStatus,
            'headers' => $headers,
            'response_time_ms' => $responseTimeMs,
            'body_size' => $bodySize,
            'body_sha256' => $bodySha256,
            'error_message' => $errorMessage,
            'meta' => $meta,
        ]);
    }

    private function processResultIncidents(
        MonitoredUrl $monitor,
        PrivateProbe $privateProbe,
        PrivateProbeScanTask $task,
        UrlSnapshot $snapshot
    ): void {
        $isFetchError = is_string($snapshot->error_message) && trim((string) $snapshot->error_message) !== '';
        $isUnexpectedStatus = $snapshot->http_status !== null
            && ((int) $snapshot->http_status < (int) $monitor->expected_status_min || (int) $snapshot->http_status > (int) $monitor->expected_status_max);

        $isDown = $isFetchError || $isUnexpectedStatus;

        $consecutiveFailures = $isDown
            ? max(0, (int) ($monitor->consecutive_failures ?? 0)) + 1
            : 0;

        $alertAfterFailures = max(1, min(10, (int) ($monitor->alert_after_failures ?? 1)));
        $shouldEmit = ! $isDown || $consecutiveFailures >= $alertAfterFailures;

        $monitor->forceFill([
            'last_scanned_at' => $snapshot->fetched_at,
            'next_scan_at' => $snapshot->fetched_at->copy()->addMinutes(max(1, min(43200, (int) ($monitor->frequency_minutes ?? 1440)))),
            'consecutive_failures' => $consecutiveFailures,
        ])->save();

        if ($isDown && ! $shouldEmit) {
            return;
        }

        $incidentService = app(IncidentService::class);

        if ($isFetchError) {
            $incidentService->upsert([
                'tenant_id' => (int) $monitor->tenant_id,
                'monitored_url_id' => (int) $monitor->id,
                'current_snapshot_id' => $snapshot->id,
                'kind' => 'fetch_error',
                'fingerprint' => hash('sha256', 'fetch_error|'.$monitor->id.'|'.$privateProbe->id.'|'.(string) $snapshot->error_message),
                'severity' => 85,
                'status' => 'open',
                'summary' => 'Fetch error (private probe)',
                'details' => [
                    'source' => 'private_probe',
                    'private_probe_id' => $privateProbe->id,
                    'task_id' => $task->id,
                    'error' => $snapshot->error_message,
                ],
                'detected_at' => $snapshot->fetched_at,
                'last_seen_at' => $snapshot->fetched_at,
            ]);
        }

        if ($isUnexpectedStatus) {
            $status = (int) $snapshot->http_status;

            $incidentService->upsert([
                'tenant_id' => (int) $monitor->tenant_id,
                'monitored_url_id' => (int) $monitor->id,
                'current_snapshot_id' => $snapshot->id,
                'kind' => 'unexpected_status',
                'fingerprint' => hash('sha256', 'unexpected_status|'.$monitor->id.'|'.$privateProbe->id.'|'.$status),
                'severity' => 70,
                'status' => 'open',
                'summary' => 'Unexpected HTTP status (private probe)',
                'details' => [
                    'source' => 'private_probe',
                    'private_probe_id' => $privateProbe->id,
                    'task_id' => $task->id,
                    'http_status' => $status,
                    'expected_status_min' => (int) $monitor->expected_status_min,
                    'expected_status_max' => (int) $monitor->expected_status_max,
                ],
                'detected_at' => $snapshot->fetched_at,
                'last_seen_at' => $snapshot->fetched_at,
            ]);
        }

        if (! $isDown) {
            Incident::query()
                ->where('tenant_id', $monitor->tenant_id)
                ->where('monitored_url_id', $monitor->id)
                ->whereIn('kind', ['fetch_error', 'unexpected_status'])
                ->whereIn('status', ['open', 'ack'])
                ->update([
                    'status' => 'resolved',
                    'resolved_at' => now(),
                    'updated_at' => now(),
                ]);
        }
    }
}
