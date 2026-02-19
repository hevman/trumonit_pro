<?php

namespace App\Console\Commands;

use App\Models\PrivateProbe;
use App\Services\PrivateProbeDispatchService;
use Illuminate\Console\Command;

class WidsPrivateProbesDispatchCommand extends Command
{
    protected $signature = 'wids:private-probes-dispatch {--tenant_id=} {--force} {--max=}';

    protected $description = 'Auto-queue due private-probe monitors using fair probe routing';

    public function handle(): int
    {
        $tenantId = is_numeric($this->option('tenant_id')) ? (int) $this->option('tenant_id') : null;
        $tenantId = is_int($tenantId) && $tenantId > 0 ? $tenantId : null;

        $max = is_numeric($this->option('max')) ? (int) $this->option('max') : null;
        $max = is_int($max) && $max > 0 ? $max : null;

        $summary = app(PrivateProbeDispatchService::class)->dispatchDueMonitors(
            tenantId: $tenantId,
            force: (bool) $this->option('force'),
            maxTasks: $max,
        );

        if (! (bool) ($summary['enabled'] ?? false)) {
            $this->info('Private probes are disabled.');

            return self::SUCCESS;
        }

        $message = sprintf(
            'Queued %d private-probe task(s). processed=%d available_probes=%d',
            (int) ($summary['queued_total'] ?? 0),
            (int) ($summary['processed'] ?? 0),
            (int) ($summary['available_probes_total'] ?? 0),
        );

        $parts = [];

        foreach ([
            'skipped_inflight',
            'skipped_no_probe',
            'skipped_tenant_cap',
            'skipped_validation',
            'skipped_pinned_probe_missing',
            'skipped_requested_region_policy',
            'probes_filtered_residency_policy',
            'region_fallback_total',
        ] as $key) {
            $value = (int) ($summary[$key] ?? 0);

            if ($value > 0) {
                $parts[] = $key.'='.$value;
            }
        }

        if ($parts !== []) {
            $message .= ' '.implode(' ', $parts);
        }

        $queuedByProbe = is_array($summary['queued_by_probe'] ?? null) ? $summary['queued_by_probe'] : [];

        if ($queuedByProbe !== []) {
            $probeIds = array_map(fn ($id): int => (int) $id, array_keys($queuedByProbe));
            $probeNames = PrivateProbe::query()
                ->whereIn('id', $probeIds)
                ->pluck('name', 'id')
                ->all();

            ksort($queuedByProbe);

            $split = [];
            foreach ($queuedByProbe as $probeId => $count) {
                $probeLabel = (string) ($probeNames[(int) $probeId] ?? ('probe#'.$probeId));
                $split[] = $probeLabel.'='.(int) $count;
            }

            $message .= ' probe_split=['.implode(', ', $split).']';
        }

        $this->info($message);

        return self::SUCCESS;
    }
}
