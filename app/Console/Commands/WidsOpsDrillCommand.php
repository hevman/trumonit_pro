<?php

namespace App\Console\Commands;

use App\Services\OpsDrillArtifactService;
use App\Services\OpsDrillService;
use Illuminate\Console\Command;

class WidsOpsDrillCommand extends Command
{
    protected $signature = 'wids:ops-drill {--format=text : text|json} {--window-hours=} {--save : Save JSON artifact to storage/app/ops-drills}';

    protected $description = 'Run operational drill checks and emit tuning recommendations';

    public function handle(): int
    {
        $format = strtolower(trim((string) $this->option('format')));
        $format = in_array($format, ['text', 'json'], true) ? $format : 'text';

        $windowHoursOption = $this->option('window-hours');
        $windowHours = is_numeric($windowHoursOption) ? (int) $windowHoursOption : null;

        $report = app(OpsDrillService::class)->analyze($windowHours);

        if ($format === 'json') {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->renderTextReport($report);
        }

        if ((bool) $this->option('save')) {
            $stored = app(OpsDrillArtifactService::class)->store($report);
            $this->line('Artifact saved: '.(string) ($stored['storage_path'] ?? ''));
        }

        return self::SUCCESS;
    }

    /**
     * @param  array{
     *   generated_at: string,
     *   window_hours: int,
     *   overall_status: string,
     *   checks: array<string, array{status: string, message: string, data: array<string, mixed>}>,
     *   recommendations: list<string>
     * }  $report
     */
    private function renderTextReport(array $report): void
    {
        $this->info('Ops drill report');
        $this->line('Generated at: '.(string) ($report['generated_at'] ?? now()->toISOString()));
        $this->line('Window hours: '.(int) ($report['window_hours'] ?? 24));
        $this->line('');

        $checks = is_array($report['checks'] ?? null) ? $report['checks'] : [];

        foreach ($checks as $key => $check) {
            $status = strtoupper((string) ($check['status'] ?? 'PASS'));
            $message = (string) ($check['message'] ?? '');
            $this->line(sprintf('- %s: %s - %s', $key, $status, $message));
        }

        $this->line('');
        $this->line('Overall: '.strtoupper((string) ($report['overall_status'] ?? 'pass')));

        $recommendations = is_array($report['recommendations'] ?? null) ? $report['recommendations'] : [];

        if ($recommendations === []) {
            $this->line('Recommendations: none');

            return;
        }

        $this->line('Recommendations:');
        foreach ($recommendations as $index => $recommendation) {
            $this->line(sprintf('  %d. %s', $index + 1, (string) $recommendation));
        }
    }

}
