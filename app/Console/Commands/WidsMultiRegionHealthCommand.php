<?php

namespace App\Console\Commands;

use App\Services\MultiRegionHealthService;
use Illuminate\Console\Command;

class WidsMultiRegionHealthCommand extends Command
{
    protected $signature = 'wids:multi-region-health {--format=text : text|json}';

    protected $description = 'Show multi-region health/SLA snapshot and policy-driven region selection';

    public function handle(): int
    {
        $format = strtolower(trim((string) $this->option('format')));
        $format = in_array($format, ['text', 'json'], true) ? $format : 'text';

        $regions = config('wids.multi_region.regions', []);
        $regions = is_array($regions) ? $regions : [];
        $regions = array_values(array_filter(array_map(fn ($value) => is_string($value) ? strtolower(trim($value)) : '', $regions)));

        $service = app(MultiRegionHealthService::class);
        $controls = $service->controls();
        $policy = $service->applyRegionPolicy($regions);
        $regionHealth = $service->healthMapForRegions($regions);

        $payload = [
            'generated_at' => now()->toISOString(),
            'controls' => $controls,
            'configured_regions' => $regions,
            'policy' => $policy,
            'region_health' => $regionHealth,
        ];

        if ($format === 'json') {
            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('Multi-region health snapshot:');
        $this->line('  Controls enabled: '.($controls['enabled'] ? 'yes' : 'no'));
        $this->line('  Window hours: '.(int) $controls['window_hours']);
        $this->line('  Min samples: '.(int) $controls['min_samples']);
        $this->line('  Max failure ratio: '.(float) $controls['max_failure_ratio']);
        $this->line('  Policy relaxed: '.(($policy['policy_relaxed'] ?? false) ? 'yes' : 'no'));
        $this->line('  Selected regions: '.implode(', ', (array) ($policy['selected_regions'] ?? [])));

        foreach ($regionHealth as $region => $stats) {
            $this->line(sprintf(
                '  - %s attempts=%d failures=%d ratio=%.3f unhealthy=%s',
                (string) $region,
                (int) ($stats['attempts'] ?? 0),
                (int) ($stats['failures'] ?? 0),
                (float) ($stats['failure_ratio'] ?? 0),
                ((bool) ($stats['unhealthy'] ?? false)) ? 'yes' : 'no'
            ));
        }

        return self::SUCCESS;
    }
}
