<?php

namespace App\Services\SeoRisk;

use App\Models\SeoDailyMetric;
use App\Models\SeoBacklinkMetric;
use App\Models\SeoIndexMetric;
use App\Models\SeoIntegration;
use App\Models\SeoProperty;
use App\Models\Tenant;
use App\Services\IncidentService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SeoRiskSyncService
{
    public function __construct(
        private readonly GoogleSearchConsoleService $gscService,
        private readonly GoogleAnalyticsService $ga4Service,
        private readonly BacklinkSignalService $backlinkService,
        private readonly CoreUpdateSignalService $coreUpdateService,
        private readonly SeoRiskPlanService $planService,
        private readonly IncidentService $incidentService,
    ) {}

    /**
     * @return array{
     *   tenant_id: int,
     *   date: string,
     *   properties_total: int,
     *   properties_synced: int,
     *   incidents_created: int,
     *   errors: list<string>
     * }
     */
    public function syncTenant(Tenant $tenant, CarbonInterface $metricDate): array
    {
        $metricDate = CarbonImmutable::parse($metricDate->toDateString())->startOfDay();

        $gscIntegration = $this->integrationForTenant($tenant, 'gsc');
        $ga4Integration = $this->integrationForTenant($tenant, 'ga4');
        $properties = $tenant->seoProperties()
            ->where('is_enabled', true)
            ->with(['monitoredUrl.latestSnapshot'])
            ->orderBy('id')
            ->get();

        $summary = [
            'tenant_id' => (int) $tenant->id,
            'date' => $metricDate->toDateString(),
            'properties_total' => $properties->count(),
            'properties_synced' => 0,
            'incidents_created' => 0,
            'errors' => [],
        ];
        $gscHasError = false;
        $ga4HasError = false;
        $backlinksEnabled = (bool) config('wids.seo_risk.backlinks.enabled', false)
            && $this->planService->allowsBacklinksForTenant($tenant);
        $indexMonitorEnabled = (bool) config('wids.seo_risk.index_monitor.enabled', true);
        $coreUpdateEvents = $this->coreUpdateService->eventsAroundDate($metricDate);

        foreach ($properties as $property) {
            $gscMetrics = ['clicks' => 0, 'impressions' => 0, 'ctr' => 0.0, 'position' => 0.0, 'raw' => []];
            $ga4Metrics = ['sessions' => 0, 'conversions' => 0, 'raw' => []];
            $indexMetrics = [
                'indexed_urls' => 0,
                'submitted_urls' => 0,
                'coverage_ratio' => 0.0,
                'sitemaps_count' => 0,
                'raw' => [],
            ];
            $backlinkMetrics = [
                'referring_domains_total' => 0,
                'backlinks_total' => 0,
                'lost_referring_domains' => 0,
                'new_referring_domains' => 0,
                'raw' => [],
            ];
            $hasAnyDataSource = false;

            try {
                if ($gscIntegration?->is_enabled) {
                    $gscMetrics = $this->gscService->fetchDailyMetrics($gscIntegration, $property, $metricDate);
                    $hasAnyDataSource = true;
                    if ($indexMonitorEnabled) {
                        $indexMetrics = $this->gscService->fetchIndexCoverageMetrics($gscIntegration, $property);
                    }
                }
            } catch (\Throwable $e) {
                $summary['errors'][] = 'property#'.$property->id.' gsc: '.$e->getMessage();
                $this->markIntegrationError($gscIntegration, $e->getMessage());
                $gscHasError = true;
            }

            try {
                if ($ga4Integration?->is_enabled) {
                    $ga4Metrics = $this->ga4Service->fetchOrganicDailyMetrics($ga4Integration, $property, $metricDate);
                    $hasAnyDataSource = true;
                }
            } catch (\Throwable $e) {
                $summary['errors'][] = 'property#'.$property->id.' ga4: '.$e->getMessage();
                $this->markIntegrationError($ga4Integration, $e->getMessage());
                $ga4HasError = true;
            }

            if ($backlinksEnabled) {
                try {
                    $backlinkMetrics = $this->backlinkService->fetchDailyMetrics($property, $metricDate);
                    $hasAnyDataSource = true;
                } catch (\Throwable $e) {
                    $summary['errors'][] = 'property#'.$property->id.' backlinks: '.$e->getMessage();
                }
            }

            if (! $hasAnyDataSource) {
                continue;
            }

            DB::transaction(function () use (
                $tenant,
                $property,
                $metricDate,
                $gscMetrics,
                $ga4Metrics,
                $indexMetrics,
                $backlinkMetrics,
                $coreUpdateEvents,
                &$summary
            ): void {
                $dailyMetric = SeoDailyMetric::query()->updateOrCreate(
                    [
                        'tenant_id' => (int) $tenant->id,
                        'seo_property_id' => (int) $property->id,
                        'metric_date' => $metricDate->toDateString(),
                    ],
                    [
                        'organic_clicks' => (int) ($gscMetrics['clicks'] ?? 0),
                        'organic_impressions' => (int) ($gscMetrics['impressions'] ?? 0),
                        'organic_ctr' => (float) ($gscMetrics['ctr'] ?? 0.0),
                        'avg_position' => (float) ($gscMetrics['position'] ?? 0.0),
                        'organic_sessions' => (int) ($ga4Metrics['sessions'] ?? 0),
                        'organic_conversions' => (int) ($ga4Metrics['conversions'] ?? 0),
                        'source_payload' => [
                            'gsc' => $gscMetrics['raw'] ?? [],
                            'ga4' => $ga4Metrics['raw'] ?? [],
                        ],
                    ]
                );

                $indexMetric = null;
                if ((bool) config('wids.seo_risk.index_monitor.enabled', true)) {
                    $indexMetric = SeoIndexMetric::query()->updateOrCreate(
                        [
                            'tenant_id' => (int) $tenant->id,
                            'seo_property_id' => (int) $property->id,
                            'metric_date' => $metricDate->toDateString(),
                        ],
                        [
                            'indexed_urls' => max(0, (int) ($indexMetrics['indexed_urls'] ?? 0)),
                            'submitted_urls' => max(0, (int) ($indexMetrics['submitted_urls'] ?? 0)),
                            'coverage_ratio' => max(0.0, (float) ($indexMetrics['coverage_ratio'] ?? 0.0)),
                            'source_payload' => [
                                'sitemaps_count' => max(0, (int) ($indexMetrics['sitemaps_count'] ?? 0)),
                                'raw' => $indexMetrics['raw'] ?? [],
                            ],
                        ]
                    );
                }

                $backlinkMetric = null;
                if ($backlinksEnabled) {
                    $backlinkMetric = SeoBacklinkMetric::query()->updateOrCreate(
                        [
                            'tenant_id' => (int) $tenant->id,
                            'seo_property_id' => (int) $property->id,
                            'metric_date' => $metricDate->toDateString(),
                        ],
                        [
                            'referring_domains_total' => max(0, (int) ($backlinkMetrics['referring_domains_total'] ?? 0)),
                            'backlinks_total' => max(0, (int) ($backlinkMetrics['backlinks_total'] ?? 0)),
                            'lost_referring_domains' => max(0, (int) ($backlinkMetrics['lost_referring_domains'] ?? 0)),
                            'new_referring_domains' => max(0, (int) ($backlinkMetrics['new_referring_domains'] ?? 0)),
                            'source_payload' => [
                                'provider' => (string) config('wids.seo_risk.backlinks.provider', 'generic'),
                                'raw' => $backlinkMetrics['raw'] ?? [],
                            ],
                        ]
                    );
                }

                $property->last_evaluated_at = now();
                $property->save();

                $summary['properties_synced']++;
                $summary['incidents_created'] += $this->evaluateAndCreateIncidents(
                    $property,
                    $dailyMetric,
                    $metricDate,
                    $indexMetric,
                    $backlinkMetric,
                    $coreUpdateEvents,
                );
            });
        }

        if ($gscIntegration && $gscIntegration->is_enabled && ! $gscHasError) {
            $gscIntegration->last_synced_at = now();
            $gscIntegration->last_error = null;
            $gscIntegration->save();
        }

        if ($ga4Integration && $ga4Integration->is_enabled && ! $ga4HasError) {
            $ga4Integration->last_synced_at = now();
            $ga4Integration->last_error = null;
            $ga4Integration->save();
        }

        return $summary;
    }

    private function integrationForTenant(Tenant $tenant, string $provider): ?SeoIntegration
    {
        return $tenant->seoIntegrations()
            ->where('provider', $provider)
            ->first();
    }

    private function markIntegrationError(?SeoIntegration $integration, string $message): void
    {
        if (! $integration) {
            return;
        }

        $integration->last_error = $message;
        $integration->save();
    }

    private function evaluateAndCreateIncidents(
        SeoProperty $property,
        SeoDailyMetric $dailyMetric,
        CarbonInterface $metricDate,
        ?SeoIndexMetric $indexMetric = null,
        ?SeoBacklinkMetric $backlinkMetric = null,
        array $coreUpdateEvents = [],
    ): int {
        $baselineDays = max(
            (int) config('wids.seo_risk.min_baseline_days', 3),
            min(
                (int) config('wids.seo_risk.max_baseline_days', 30),
                (int) ($property->baseline_days ?: (int) config('wids.seo_risk.default_baseline_days', 7))
            )
        );

        $history = SeoDailyMetric::query()
            ->where('seo_property_id', (int) $property->id)
            ->where('metric_date', '<', $metricDate->toDateString())
            ->orderByDesc('metric_date')
            ->limit($baselineDays)
            ->get();

        if ($history->count() < $baselineDays) {
            return 0;
        }

        $baseline = [
            'clicks' => (float) $history->avg('organic_clicks'),
            'impressions' => (float) $history->avg('organic_impressions'),
            'sessions' => (float) $history->avg('organic_sessions'),
        ];

        $incidentsCreated = 0;
        $monitoredUrl = $property->monitoredUrl;
        $snapshotId = (int) ($monitoredUrl?->latestSnapshot?->id ?? 0);

        if ($snapshotId <= 0 || ! $monitoredUrl) {
            Log::warning('SEO risk sync skipped incident creation due to missing monitor snapshot.', [
                'seo_property_id' => (int) $property->id,
                'tenant_id' => (int) $property->tenant_id,
            ]);

            return 0;
        }

        $metricDefinitions = [
            [
                'key' => 'clicks',
                'kind' => 'seo_clicks_drop',
                'current' => (float) $dailyMetric->organic_clicks,
                'baseline' => $baseline['clicks'],
                'threshold' => (int) $property->alert_clicks_drop_percent,
                'summary' => 'Organic clicks dropped sharply.',
                'action' => [
                    'Open Google Search Console coverage and performance reports.',
                    'Check robots.txt, canonical, and noindex changes from last deploy.',
                    'Verify key money pages for status 200 and unchanged title/meta.',
                ],
            ],
            [
                'key' => 'impressions',
                'kind' => 'seo_impressions_drop',
                'current' => (float) $dailyMetric->organic_impressions,
                'baseline' => $baseline['impressions'],
                'threshold' => (int) $property->alert_impressions_drop_percent,
                'summary' => 'Organic impressions dropped sharply.',
                'action' => [
                    'Check indexing coverage and sitemap freshness.',
                    'Inspect template-level meta tags and canonical consistency.',
                    'Review recent content removals or URL structure changes.',
                ],
            ],
            [
                'key' => 'sessions',
                'kind' => 'seo_sessions_drop',
                'current' => (float) $dailyMetric->organic_sessions,
                'baseline' => $baseline['sessions'],
                'threshold' => (int) $property->alert_sessions_drop_percent,
                'summary' => 'Organic sessions dropped sharply.',
                'action' => [
                    'Compare GA4 organic sessions by landing page and country.',
                    'Verify tracking consistency and consent mode behavior.',
                    'Cross-check with GSC clicks to isolate analytics vs visibility issue.',
                ],
            ],
        ];

        foreach ($metricDefinitions as $definition) {
            $baselineValue = (float) ($definition['baseline'] ?? 0.0);
            $currentValue = (float) ($definition['current'] ?? 0.0);
            $threshold = max(5, min(90, (int) ($definition['threshold'] ?? 30)));

            if ($baselineValue <= 0.0) {
                continue;
            }

            $dropPercent = (($baselineValue - $currentValue) / $baselineValue) * 100;
            if ($dropPercent < $threshold) {
                continue;
            }

            $severity = min(100, max(60, (int) round(60 + (($dropPercent - $threshold) * 1.5))));
            $fingerprint = hash('sha256', implode('|', [
                (string) $definition['kind'],
                (string) $property->id,
                $metricDate->toDateString(),
            ]));

            $coreUpdateContext = $this->coreUpdateContext($coreUpdateEvents, $metricDate);
            $summary = (string) $definition['summary'];
            if ($coreUpdateContext !== null) {
                $summary .= ' Possible Google core update impact.';
            }

            $this->incidentService->upsert([
                'tenant_id' => (int) $property->tenant_id,
                'monitored_url_id' => (int) $property->monitored_url_id,
                'current_snapshot_id' => $snapshotId,
                'kind' => (string) $definition['kind'],
                'severity' => $severity,
                'status' => 'open',
                'summary' => $summary,
                'detected_at' => now(),
                'fingerprint' => $fingerprint,
                'details' => [
                    'metric_key' => $definition['key'],
                    'metric_date' => $metricDate->toDateString(),
                    'property_id' => (int) $property->id,
                    'property_name' => (string) $property->name,
                    'domain' => (string) $property->domain,
                    'current_value' => round($currentValue, 2),
                    'baseline_value_7d' => round($baselineValue, 2),
                    'drop_percent' => round($dropPercent, 2),
                    'threshold_percent' => $threshold,
                    'playbook' => $definition['action'],
                    'playbook_title' => 'Recovery checklist',
                    'core_update_context' => $coreUpdateContext,
                ],
            ]);

            $incidentsCreated++;
        }

        $incidentsCreated += $this->evaluateBacklinkIncidents(
            property: $property,
            metricDate: $metricDate,
            backlinkMetric: $backlinkMetric,
            baselineDays: $baselineDays,
            snapshotId: $snapshotId,
        );

        $incidentsCreated += $this->evaluateIndexIncidents(
            property: $property,
            metricDate: $metricDate,
            indexMetric: $indexMetric,
            baselineDays: $baselineDays,
            snapshotId: $snapshotId,
        );

        return $incidentsCreated;
    }

    private function evaluateIndexIncidents(
        SeoProperty $property,
        CarbonInterface $metricDate,
        ?SeoIndexMetric $indexMetric,
        int $baselineDays,
        int $snapshotId,
    ): int {
        if (! $indexMetric) {
            return 0;
        }

        $history = SeoIndexMetric::query()
            ->where('seo_property_id', (int) $property->id)
            ->where('metric_date', '<', $metricDate->toDateString())
            ->orderByDesc('metric_date')
            ->limit($baselineDays)
            ->get();

        if ($history->count() < max(3, min($baselineDays, 7))) {
            return 0;
        }

        $baselineIndexed = max(0.0, (float) $history->avg('indexed_urls'));
        $currentIndexed = max(0.0, (float) $indexMetric->indexed_urls);
        if ($baselineIndexed <= 0.0) {
            return 0;
        }

        $dropAbsolute = max(0.0, $baselineIndexed - $currentIndexed);
        $dropPercent = ($dropAbsolute / $baselineIndexed) * 100;
        $minDropPercent = max(1, min(95, (int) config('wids.seo_risk.index_monitor.min_drop_percent', 15)));
        $minDropAbsolute = max(1, (int) config('wids.seo_risk.index_monitor.min_drop_absolute', 25));

        if ($dropPercent < $minDropPercent || $dropAbsolute < $minDropAbsolute) {
            return 0;
        }

        $severity = min(
            100,
            max(
                60,
                (int) round(60 + (($dropPercent - $minDropPercent) * 1.6) + (($dropAbsolute - $minDropAbsolute) * 0.12))
            )
        );

        $this->incidentService->upsert([
            'tenant_id' => (int) $property->tenant_id,
            'monitored_url_id' => (int) $property->monitored_url_id,
            'current_snapshot_id' => $snapshotId,
            'kind' => 'seo_indexed_drop',
            'severity' => $severity,
            'status' => 'open',
            'summary' => 'Indexed URLs dropped and deindex risk increased.',
            'detected_at' => now(),
            'fingerprint' => hash('sha256', implode('|', [
                'seo_indexed_drop',
                (string) $property->id,
                $metricDate->toDateString(),
            ])),
            'details' => [
                'metric_key' => 'indexed_urls',
                'metric_date' => $metricDate->toDateString(),
                'property_id' => (int) $property->id,
                'property_name' => (string) $property->name,
                'domain' => (string) $property->domain,
                'current_indexed_urls' => (int) round($currentIndexed),
                'current_submitted_urls' => (int) $indexMetric->submitted_urls,
                'current_coverage_ratio' => round((float) $indexMetric->coverage_ratio, 4),
                'baseline_indexed_urls' => round($baselineIndexed, 2),
                'drop_absolute' => round($dropAbsolute, 2),
                'drop_percent' => round($dropPercent, 2),
                'threshold_percent' => $minDropPercent,
                'threshold_absolute' => $minDropAbsolute,
                'playbook_title' => 'Index recovery checklist',
                'playbook' => [
                    'Open GSC Indexing > Pages and verify exclusion/error trend since last deploy.',
                    'Validate robots.txt, meta robots, canonicals, and accidental noindex in templates.',
                    'Check sitemap freshness and ensure key URLs return HTTP 200 without redirect loops.',
                    'Request reindexing for priority landing pages after fixes are live.',
                ],
            ],
        ]);

        return 1;
    }

    private function evaluateBacklinkIncidents(
        SeoProperty $property,
        CarbonInterface $metricDate,
        ?SeoBacklinkMetric $backlinkMetric,
        int $baselineDays,
        int $snapshotId,
    ): int {
        if (! $backlinkMetric) {
            return 0;
        }

        $minLostDomains = max(1, (int) config('wids.seo_risk.backlinks.minimum_lost_domains', 5));
        $minLostPercent = max(0.1, (float) config('wids.seo_risk.backlinks.minimum_lost_domains_percent', 3));
        $spikeMultiplier = max(1.0, (float) config('wids.seo_risk.backlinks.history_spike_multiplier', 2.0));

        $history = SeoBacklinkMetric::query()
            ->where('seo_property_id', (int) $property->id)
            ->where('metric_date', '<', $metricDate->toDateString())
            ->orderByDesc('metric_date')
            ->limit($baselineDays)
            ->get();

        if ($history->count() < max(3, min($baselineDays, 7))) {
            return 0;
        }

        $currentLost = (int) $backlinkMetric->lost_referring_domains;
        $currentRefDomains = max(1, (int) $backlinkMetric->referring_domains_total);
        $currentLossPercent = ($currentLost / $currentRefDomains) * 100;
        $historyAvgLoss = max(0.0, (float) $history->avg('lost_referring_domains'));
        $historySpikeThreshold = max((float) $minLostDomains, $historyAvgLoss * $spikeMultiplier);

        if (
            $currentLost < $minLostDomains
            || $currentLossPercent < $minLostPercent
            || $currentLost < $historySpikeThreshold
        ) {
            return 0;
        }

        $severity = min(
            100,
            max(
                60,
                (int) round(60 + (($currentLost - $historySpikeThreshold) * 2.5) + ($currentLossPercent * 1.2))
            )
        );

        $this->incidentService->upsert([
            'tenant_id' => (int) $property->tenant_id,
            'monitored_url_id' => (int) $property->monitored_url_id,
            'current_snapshot_id' => $snapshotId,
            'kind' => 'seo_backlinks_loss',
            'severity' => $severity,
            'status' => 'open',
            'summary' => 'Referring domains dropped faster than normal.',
            'detected_at' => now(),
            'fingerprint' => hash('sha256', implode('|', [
                'seo_backlinks_loss',
                (string) $property->id,
                $metricDate->toDateString(),
            ])),
            'details' => [
                'metric_key' => 'lost_referring_domains',
                'metric_date' => $metricDate->toDateString(),
                'property_id' => (int) $property->id,
                'property_name' => (string) $property->name,
                'domain' => (string) $property->domain,
                'current_lost_domains' => $currentLost,
                'current_referring_domains_total' => $currentRefDomains,
                'current_loss_percent' => round($currentLossPercent, 2),
                'history_avg_lost_domains' => round($historyAvgLoss, 2),
                'history_spike_threshold' => round($historySpikeThreshold, 2),
                'playbook_title' => 'Backlink recovery checklist',
                'playbook' => [
                    'Review lost linking domains and flag high-authority losses first.',
                    'Check top landing pages for redirects, 404s, or canonical drift.',
                    'Recover broken backlinks and request relinks for lost placements.',
                    'Increase internal links toward affected commercial landing pages.',
                ],
            ],
        ]);

        return 1;
    }

    /**
     * @param  array<int, \App\Models\SeoCoreUpdateEvent>  $coreUpdateEvents
     * @return array<string, mixed>|null
     */
    private function coreUpdateContext(array $coreUpdateEvents, CarbonInterface $metricDate): ?array
    {
        if ($coreUpdateEvents === []) {
            return null;
        }

        $first = $coreUpdateEvents[0] ?? null;
        if (! $first) {
            return null;
        }

        return [
            'matched' => true,
            'metric_date' => $metricDate->toDateString(),
            'event_count' => count($coreUpdateEvents),
            'top_event' => [
                'title' => (string) $first->title,
                'published_at' => optional($first->published_at)?->toDateString(),
                'effective_from' => optional($first->effective_from)?->toDateString(),
                'effective_to' => optional($first->effective_to)?->toDateString(),
                'source_url' => (string) ($first->source_url ?? ''),
            ],
        ];
    }
}
