<?php

namespace App\Services;

use App\Models\Incident;

class IncidentService
{
    /**
     * Create a new incident or update an existing open/ack incident with the same fingerprint.
     *
     * @param  array<string, mixed>  $incidentData
     */
    public function upsert(array $incidentData): Incident
    {
        $tenantId = $incidentData['tenant_id'] ?? null;
        $monitoredUrlId = $incidentData['monitored_url_id'] ?? null;
        $kind = $incidentData['kind'] ?? null;

        if (! is_int($tenantId) || ! is_int($monitoredUrlId) || ! is_string($kind) || $kind === '') {
            throw new \InvalidArgumentException('Invalid incident payload.');
        }

        $fingerprint = $incidentData['fingerprint'] ?? null;

        if (! is_string($fingerprint) || $fingerprint === '') {
            $details = $incidentData['details'] ?? null;
            $json = json_encode($details, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $fingerprint = hash('sha256', $kind.'|'.(is_string($json) ? $json : ''));
            $incidentData['fingerprint'] = $fingerprint;
        }

        $status = (string) ($incidentData['status'] ?? 'open');

        $existing = Incident::query()
            ->where('tenant_id', $tenantId)
            ->where('monitored_url_id', $monitoredUrlId)
            ->where('kind', $kind)
            ->whereIn('status', ['open', 'ack'])
            ->where('fingerprint', $fingerprint)
            ->orderByDesc('id')
            ->first();

        if ($existing) {
            $existing->forceFill([
                'current_snapshot_id' => $incidentData['current_snapshot_id'] ?? $existing->current_snapshot_id,
                'last_seen_at' => $incidentData['last_seen_at'] ?? $incidentData['detected_at'] ?? now(),
                'occurrences' => (int) ($existing->occurrences ?? 1) + 1,
                'severity' => max((int) $existing->severity, (int) ($incidentData['severity'] ?? $existing->severity)),
            ]);

            if ($existing->previous_snapshot_id === null && isset($incidentData['previous_snapshot_id'])) {
                $existing->previous_snapshot_id = $incidentData['previous_snapshot_id'];
            }

            if (isset($incidentData['details'])) {
                $existing->details = $incidentData['details'];
            }

            if (isset($incidentData['summary'])) {
                $existing->summary = $incidentData['summary'];
            }

            if (in_array($status, ['open', 'ack', 'resolved'], true)) {
                if ($status === 'resolved') {
                    $existing->status = 'resolved';
                    $existing->resolved_at = $existing->resolved_at ?? now();
                } elseif ($status === 'ack') {
                    $existing->status = 'ack';
                } elseif ($status === 'open' && $existing->status !== 'ack') {
                    $existing->status = 'open';
                }
            }

            $existing->save();

            return $existing;
        }

        if (! isset($incidentData['last_seen_at'])) {
            $incidentData['last_seen_at'] = $incidentData['detected_at'] ?? now();
        }

        if (! isset($incidentData['occurrences'])) {
            $incidentData['occurrences'] = 1;
        }

        if ($status === 'resolved') {
            $incidentData['resolved_at'] = $incidentData['resolved_at'] ?? now();
        }

        return Incident::query()->create($incidentData);
    }
}
