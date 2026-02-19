<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\WeeklyDigestReport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class WeeklyDigestReportService
{
    /**
     * @param  array<string, mixed>  $digest
     * @param  list<string>  $recipients
     */
    public function storeReport(Tenant $tenant, array $digest, array $recipients = [], string $dispatchMode = 'queue'): WeeklyDigestReport
    {
        $windowStart = $this->safeDate((string) data_get($digest, 'window.start', now()->toDateString())) ?? now()->toDateString();
        $windowEnd = $this->safeDate((string) data_get($digest, 'window.end', now()->toDateString())) ?? now()->toDateString();
        $generatedAt = now()->utc();
        $dispatchMode = in_array($dispatchMode, ['queue', 'sync', 'manual'], true) ? $dispatchMode : 'queue';

        $pathBase = sprintf(
            'reports/weekly-digest/tenant-%d/%s_%s_%s',
            (int) $tenant->id,
            $windowStart,
            $windowEnd,
            $generatedAt->format('Ymd_His_u')
        );

        $jsonStoragePath = $pathBase.'.json';
        $csvStoragePath = $pathBase.'.csv';
        $jsonPayload = (string) json_encode($digest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $csvPayload = $this->buildCsv($digest);

        Storage::disk('local')->put($jsonStoragePath, $jsonPayload);
        Storage::disk('local')->put($csvStoragePath, $csvPayload);

        $overview = is_array($digest['overview'] ?? null) ? $digest['overview'] : [];
        $summary = [
            'monitors_total' => (int) ($overview['monitors_total'] ?? 0),
            'active_monitors' => (int) ($overview['active_monitors'] ?? 0),
            'scans_total' => (int) ($overview['scans_total'] ?? 0),
            'scans_error_total' => (int) ($overview['scans_error_total'] ?? 0),
            'uptime_percent' => is_numeric($overview['uptime_percent'] ?? null)
                ? round((float) $overview['uptime_percent'], 2)
                : null,
            'incidents_opened_total' => (int) ($overview['incidents_opened_total'] ?? 0),
            'incidents_resolved_total' => (int) ($overview['incidents_resolved_total'] ?? 0),
            'incidents_critical_total' => (int) ($overview['incidents_critical_total'] ?? 0),
            'mttr_minutes' => is_numeric($overview['mttr_minutes'] ?? null)
                ? (int) $overview['mttr_minutes']
                : null,
        ];

        $normalizedRecipients = collect($recipients)
            ->map(fn ($value) => is_string($value) ? trim(strtolower($value)) : '')
            ->filter(fn (string $value): bool => $value !== '' && filter_var($value, FILTER_VALIDATE_EMAIL) !== false)
            ->unique()
            ->values()
            ->all();

        return WeeklyDigestReport::query()->create([
            'tenant_id' => (int) $tenant->id,
            'generated_by' => 'wids:weekly-digest',
            'week_start_date' => $windowStart,
            'week_end_date' => $windowEnd,
            'dispatch_mode' => $dispatchMode,
            'recipients' => $normalizedRecipients,
            'summary' => $summary,
            'json_storage_path' => $jsonStoragePath,
            'csv_storage_path' => $csvStoragePath,
            'report_format_version' => '1',
            'generated_at' => $generatedAt,
        ]);
    }

    /**
     * @return array{content: string, content_type: string, filename: string}|null
     */
    public function resolveDownloadPayload(WeeklyDigestReport $report, string $format): ?array
    {
        $format = strtolower(trim($format));
        $isJson = $format === 'json';
        $path = $isJson
            ? trim((string) $report->json_storage_path)
            : trim((string) $report->csv_storage_path);

        if ($path === '' || ! Storage::disk('local')->exists($path)) {
            return null;
        }

        $content = Storage::disk('local')->get($path);
        $windowStart = $report->week_start_date?->toDateString() ?? now()->toDateString();
        $windowEnd = $report->week_end_date?->toDateString() ?? now()->toDateString();
        $extension = $isJson ? 'json' : 'csv';
        $filename = sprintf('weekly-digest-%d-%s-%s.%s', (int) $report->tenant_id, $windowStart, $windowEnd, $extension);

        return [
            'content' => $content,
            'content_type' => $isJson ? 'application/json; charset=UTF-8' : 'text/csv; charset=UTF-8',
            'filename' => $filename,
        ];
    }

    public function cleanupExpired(int $retentionDays): int
    {
        if ($retentionDays <= 0) {
            return 0;
        }

        $cutoff = now()->subDays($retentionDays);
        $deleted = 0;

        WeeklyDigestReport::query()
            ->where(function ($query) use ($cutoff): void {
                $query->whereNotNull('generated_at')
                    ->where('generated_at', '<', $cutoff);
            })
            ->orWhere(function ($query) use ($cutoff): void {
                $query->whereNull('generated_at')
                    ->where('created_at', '<', $cutoff);
            })
            ->chunkById(200, function ($reports) use (&$deleted): void {
                foreach ($reports as $report) {
                    /** @var WeeklyDigestReport $report */
                    $jsonPath = trim((string) $report->json_storage_path);
                    $csvPath = trim((string) $report->csv_storage_path);

                    if ($jsonPath !== '' && Storage::disk('local')->exists($jsonPath)) {
                        Storage::disk('local')->delete($jsonPath);
                    }

                    if ($csvPath !== '' && Storage::disk('local')->exists($csvPath)) {
                        Storage::disk('local')->delete($csvPath);
                    }

                    $report->delete();
                    $deleted++;
                }
            });

        return $deleted;
    }

    /**
     * @param  array<string, mixed>  $digest
     */
    private function buildCsv(array $digest): string
    {
        $windowStart = (string) data_get($digest, 'window.start', '');
        $windowEnd = (string) data_get($digest, 'window.end', '');
        $overview = is_array($digest['overview'] ?? null) ? $digest['overview'] : [];
        $topIncidentKinds = is_array($digest['top_incident_kinds'] ?? null) ? $digest['top_incident_kinds'] : [];
        $unstableMonitors = is_array($digest['unstable_monitors'] ?? null) ? $digest['unstable_monitors'] : [];
        $dailyUsage = is_array($digest['daily_usage'] ?? null) ? $digest['daily_usage'] : [];
        $dates = is_array($dailyUsage['dates'] ?? null) ? $dailyUsage['dates'] : [];
        $labels = is_array($dailyUsage['labels'] ?? null) ? $dailyUsage['labels'] : [];
        $scans = is_array($dailyUsage['scans'] ?? null) ? $dailyUsage['scans'] : [];
        $incidents = is_array($dailyUsage['incidents'] ?? null) ? $dailyUsage['incidents'] : [];
        $apiRequests = is_array($dailyUsage['api_requests'] ?? null) ? $dailyUsage['api_requests'] : [];

        $handle = fopen('php://temp', 'w+');

        if (! $handle) {
            return '';
        }

        fputcsv($handle, ['section', 'key', 'value']);
        fputcsv($handle, ['window', 'start', $windowStart]);
        fputcsv($handle, ['window', 'end', $windowEnd]);

        foreach ($overview as $key => $value) {
            fputcsv($handle, ['overview', (string) $key, is_scalar($value) || $value === null ? (string) $value : json_encode($value)]);
        }

        fputcsv($handle, []);
        fputcsv($handle, ['top_incident_kinds', 'kind', 'total']);
        foreach ($topIncidentKinds as $row) {
            fputcsv($handle, ['top_incident_kinds', (string) ($row['kind'] ?? ''), (int) ($row['total'] ?? 0)]);
        }

        fputcsv($handle, []);
        fputcsv($handle, ['unstable_monitors', 'label', 'url', 'total_checks', 'down_checks', 'down_ratio_percent', 'avg_response_ms']);
        foreach ($unstableMonitors as $row) {
            fputcsv($handle, [
                'unstable_monitors',
                (string) ($row['label'] ?? ''),
                (string) ($row['url'] ?? ''),
                (int) ($row['total_checks'] ?? 0),
                (int) ($row['down_checks'] ?? 0),
                is_numeric($row['down_ratio_percent'] ?? null) ? (float) $row['down_ratio_percent'] : 0,
                is_numeric($row['avg_response_ms'] ?? null) ? (int) $row['avg_response_ms'] : '',
            ]);
        }

        fputcsv($handle, []);
        fputcsv($handle, ['daily_usage', 'date', 'label', 'scans', 'incidents', 'api_requests']);

        foreach ($dates as $index => $date) {
            fputcsv($handle, [
                'daily_usage',
                (string) $date,
                (string) ($labels[$index] ?? ''),
                (int) ($scans[$index] ?? 0),
                (int) ($incidents[$index] ?? 0),
                (int) ($apiRequests[$index] ?? 0),
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return is_string($csv) ? $csv : '';
    }

    private function safeDate(string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}

