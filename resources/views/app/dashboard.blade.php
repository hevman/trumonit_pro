<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl leading-tight text-gray-900 dark:text-gray-100">
            {{ __('messages.dashboard.title') }}
        </h2>
    </x-slot>

    <div class="app-page">
        <div class="app-container">
            @if ($showOnboarding)
                <div class="app-card app-card-info">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <div class="text-sm font-semibold">{{ __('messages.dashboard.onboarding_title') }}</div>
                            <div class="text-sm">{{ __('messages.dashboard.onboarding_text') }}</div>
                        </div>
                        <div class="flex gap-2">
                            <button type="button" class="app-button-primary" x-on:click="$dispatch('open-modal', 'quick-add-monitor')">
                                {{ __('messages.dashboard.action_create_monitor') }}
                            </button>
                            <a href="{{ route('onboarding.show') }}" class="app-button-secondary">
                                {{ __('messages.dashboard.onboarding_open') }}
                            </a>
                            <a href="{{ route('monitored-urls.create') }}" class="app-button-secondary">
                                {{ __('messages.dashboard.onboarding_advanced') }}
                            </a>
                        </div>
                    </div>
                </div>
            @endif

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <section class="app-card">
                    <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('messages.dashboard.monitors_total') }}</div>
                    <div class="mt-2 text-3xl font-semibold text-gray-900 dark:text-gray-100">{{ $urlsCount }}</div>
                </section>

                <section class="app-card">
                    <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('messages.dashboard.uptime_ratio') }}</div>
                    <div class="mt-2 text-3xl font-semibold text-gray-900 dark:text-gray-100">
                        @if ($uptimePercent !== null)
                            {{ number_format($uptimePercent, 1) }}%
                        @else
                            -
                        @endif
                    </div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        {{ __('messages.dashboard.up_monitors') }}: {{ $upMonitorsCount }}/{{ $uptimeBase }}
                    </div>
                </section>

                <section class="app-card">
                    <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('messages.dashboard.open_incidents') }}</div>
                    <div class="mt-2 text-3xl font-semibold text-gray-900 dark:text-gray-100">{{ $openIncidentsCount }}</div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        {{ __('messages.dashboard.critical_open') }}: {{ $criticalOpenIncidentsCount }}
                    </div>
                </section>

                <section class="app-card">
                    <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('messages.dashboard.avg_response_latest') }}</div>
                    <div class="mt-2 text-3xl font-semibold text-gray-900 dark:text-gray-100">
                        @if ($avgHealthyResponseMs !== null)
                            {{ $avgHealthyResponseMs }} ms
                        @else
                            -
                        @endif
                    </div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        {{ __('messages.dashboard.new_incidents_24h') }}: {{ $newIncidents24hCount }}
                    </div>
                </section>
            </div>

            @if (($operationalMonitors ?? []) !== [])
                <section class="app-card app-card-lg mt-4">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                            {{ __('messages.dashboard.operational_title') }}
                        </h3>
                        <a class="app-link text-sm" href="{{ route('monitored-urls.index') }}">
                            {{ __('messages.dashboard.operational_view_all') }}
                        </a>
                    </div>

                    <div class="mt-4 overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 text-gray-600 dark:bg-gray-900/70 dark:text-gray-300">
                                <tr>
                                    <th class="text-left px-4 py-2">{{ __('messages.monitored_urls.display_name') }}</th>
                                    <th class="text-left px-4 py-2">{{ __('messages.monitored_urls.status') }}</th>
                                    <th class="text-left px-4 py-2">{{ __('messages.dashboard.last_check') }}</th>
                                    <th class="text-left px-4 py-2">{{ __('messages.monitored_urls.time_ms') }}</th>
                                    <th class="text-left px-4 py-2">{{ __('messages.monitored_urls.open_incidents') }}</th>
                                    @if ($isOwner)
                                        <th class="text-left px-4 py-2">{{ __('messages.monitored_urls.actions') }}</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($operationalMonitors as $row)
                                    @php
                                        $tone = match ($row['state']) {
                                            'up' => 'app-chip-success',
                                            'down' => 'app-chip-danger',
                                            'paused' => 'app-chip',
                                            'muted' => 'app-chip-warning',
                                            default => 'app-chip',
                                        };
                                        $label = match ($row['state']) {
                                            'up' => __('messages.dashboard.state_up'),
                                            'down' => __('messages.dashboard.state_down'),
                                            'paused' => __('messages.dashboard.state_paused'),
                                            'muted' => __('messages.dashboard.state_muted'),
                                            default => __('messages.dashboard.state_unknown'),
                                        };
                                    @endphp
                                    <tr class="border-t border-gray-200/70 dark:border-gray-800">
                                        <td class="px-4 py-2">
                                            <a class="text-gray-900 hover:underline dark:text-gray-100" href="{{ route('monitored-urls.show', $row['id']) }}">
                                                {{ $row['display_name'] ?? $row['url'] }}
                                            </a>
                                            <div class="text-xs text-gray-500 dark:text-gray-400 break-all">{{ $row['url'] }}</div>
                                        </td>
                                        <td class="px-4 py-2">
                                            <span class="{{ $tone }}">{{ $label }}</span>
                                        </td>
                                        <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $row['last_fetched_at'] ?? '-' }}</td>
                                        <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $row['response_time_ms'] ?? '-' }}</td>
                                        <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ (int) $row['open_incidents'] }}</td>
                                        @if ($isOwner)
                                            <td class="px-4 py-2">
                                                <form method="POST" action="{{ route('monitored-urls.scan', $row['id']) }}">
                                                    @csrf
                                                    <button type="submit" class="app-button-secondary">
                                                        {{ __('messages.actions.scan_now') }}
                                                    </button>
                                                </form>
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif

            <div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
                <section class="app-card app-card-lg xl:col-span-2">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                            {{ __('messages.dashboard.incident_trend_7d') }}
                        </h3>
                        <span class="app-chip">{{ $incidentTrend['total'] }}</span>
                    </div>
                    @php
                        $incidentTrendDrilldownUrls = collect($incidentTrend['dates'] ?? [])
                            ->map(fn ($date) => route('incidents.index', [
                                'detected_from' => $date,
                                'detected_to' => $date,
                            ]))
                            ->values()
                            ->all();
                    @endphp

                    <x-analytics.bar-chart
                        :values="$incidentTrend['values']"
                        :labels="$incidentTrend['labels']"
                        :drilldown-urls="$incidentTrendDrilldownUrls"
                        :has-data="$incidentTrend['has_data']"
                        :max-value="max($incidentTrend['values'] ?: [0])"
                        :series-name="__('messages.dashboard.series_incidents')"
                        :legend="__('messages.dashboard.legend_daily_incidents')"
                        unit="incidents"
                        tone="indigo"
                        height-class="h-44"
                        :label-step="1"
                        :empty-text="__('messages.dashboard.no_incidents_7d')"
                    />
                </section>

                <section class="app-card app-card-lg">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                        {{ __('messages.dashboard.state_distribution') }}
                    </h3>
                    @php
                        $monitorStateDrilldownMap = [
                            'up' => ['state' => 'up'],
                            'down' => ['state' => 'down'],
                            'muted' => ['state' => 'muted'],
                            'paused' => ['state' => 'paused'],
                            'unknown' => null,
                        ];
                    @endphp
                    <div class="mt-4 space-y-3">
                        @foreach ($stateDistribution as $stateRow)
                            @php
                                $stateDrilldownParams = $monitorStateDrilldownMap[$stateRow['key']] ?? null;
                                $stateDrilldownUrl = is_array($stateDrilldownParams) && ($stateRow['count'] ?? 0) > 0
                                    ? route('monitored-urls.index', $stateDrilldownParams)
                                    : null;
                            @endphp
                            <div class="space-y-1">
                                <div class="flex items-center justify-between text-xs text-gray-600 dark:text-gray-300">
                                    <span>{{ __('messages.dashboard.state_'.$stateRow['key']) }}</span>
                                    @if (is_string($stateDrilldownUrl))
                                        <a href="{{ $stateDrilldownUrl }}" class="app-link text-xs underline">
                                            {{ $stateRow['count'] }} ({{ number_format($stateRow['percent'], 1) }}%)
                                        </a>
                                    @else
                                        <span>{{ $stateRow['count'] }} ({{ number_format($stateRow['percent'], 1) }}%)</span>
                                    @endif
                                </div>
                                <div class="h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                                    <div class="h-full rounded-full {{ $stateRow['color_class'] }}" style="width: {{ min(100, max(0, $stateRow['percent'])) }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-5 border-t border-gray-200 pt-4 dark:border-gray-800">
                        <div class="mb-2 text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            {{ __('messages.dashboard.quick_actions') }}
                        </div>
                        <div class="grid grid-cols-1 gap-2">
                            @if ($isOwner)
                                <a href="{{ route('onboarding.show') }}" class="app-button-primary">{{ __('messages.dashboard.action_create_monitor') }}</a>
                                <a href="{{ route('monitored-urls.create') }}" class="app-button-secondary">{{ __('messages.dashboard.onboarding_advanced') }}</a>
                            @endif
                            <a href="{{ route('incidents.index') }}" class="app-button-secondary">{{ __('messages.dashboard.action_view_incidents') }}</a>
                            @if ($isOwner)
                                <a href="{{ route('notification-channels.edit') }}" class="app-button-secondary">{{ __('messages.dashboard.action_manage_notifications') }}</a>
                            @endif
                            <a href="{{ route('status-pages.index') }}" class="app-button-secondary">{{ __('messages.dashboard.action_status_pages') }}</a>
                        </div>
                    </div>
                </section>
            </div>

            <div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
                <section class="app-card app-card-lg xl:col-span-2">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                            {{ __('messages.dashboard.response_trend_24h') }}
                        </h3>
                        <span class="app-chip">
                            @if ($responseTrend['latest_avg_ms'] !== null)
                                {{ $responseTrend['latest_avg_ms'] }} ms
                            @else
                                -
                            @endif
                        </span>
                    </div>
                    @php
                        $responseStarts = is_array($responseTrend['starts_at'] ?? null) ? $responseTrend['starts_at'] : [];
                        $responseEnds = is_array($responseTrend['ends_at'] ?? null) ? $responseTrend['ends_at'] : [];
                        $responseTrendDrilldownUrls = collect($responseStarts)
                            ->map(function ($startAt, $index) use ($responseEnds) {
                                $endAt = $responseEnds[$index] ?? $startAt;

                                return route('monitored-urls.index', [
                                    'last_scanned_from' => $startAt,
                                    'last_scanned_to' => $endAt,
                                ]);
                            })
                            ->values()
                            ->all();
                    @endphp

                    <x-analytics.bar-chart
                        :values="$responseTrend['values']"
                        :labels="$responseTrend['labels']"
                        :drilldown-urls="$responseTrendDrilldownUrls"
                        :has-data="$responseTrend['has_data']"
                        :max-value="max($responseTrend['values'] ?: [0])"
                        :series-name="__('messages.dashboard.series_avg_response')"
                        :legend="__('messages.dashboard.legend_avg_response_hourly')"
                        unit="ms"
                        tone="teal"
                        height-class="h-44"
                        :label-step="3"
                        :empty-text="__('messages.dashboard.no_response_24h')"
                    />
                    @if ($responseTrend['has_data'])
                        <div class="mt-2 text-[11px] text-gray-500 dark:text-gray-400">
                            {{ __('messages.dashboard.samples') }}: {{ $responseTrend['samples'] }}
                        </div>
                    @endif
                </section>

                <section class="app-card app-card-lg">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                        {{ __('messages.dashboard.top_incident_kinds') }}
                    </h3>

                    <div class="mt-4 space-y-3">
                        @forelse ($topIncidentKinds as $kindRow)
                            @php
                                $widthPercent = (int) round(($kindRow['total'] / $topIncidentKindsMax) * 100);
                            @endphp
                            <div class="space-y-1">
                                <div class="flex items-center justify-between gap-3 text-xs">
                                    <span class="font-mono text-gray-700 dark:text-gray-200">{{ $kindRow['kind'] }}</span>
                                    <span class="text-gray-500 dark:text-gray-400">{{ $kindRow['total'] }}</span>
                                </div>
                                <div class="h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                                    <div class="h-full rounded-full bg-indigo-500" style="width: {{ min(100, max(4, $widthPercent)) }}%"></div>
                                </div>
                            </div>
                        @empty
                            <div class="rounded-xl border border-dashed border-gray-300 px-4 py-6 text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                                {{ __('messages.dashboard.no_incident_kinds') }}
                            </div>
                        @endforelse
                    </div>
                </section>
            </div>

            <div class="app-table">
                <div class="px-4 py-3 border-b font-medium">{{ __('messages.dashboard.latest_incidents') }}</div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-gray-600 dark:bg-gray-900/70 dark:text-gray-300">
                            <tr>
                                <th class="text-left px-4 py-2">{{ __('messages.incidents.detected_at') }}</th>
                                <th class="text-left px-4 py-2">{{ __('messages.monitored_urls.display_name') }}</th>
                                <th class="text-left px-4 py-2">{{ __('messages.incidents.severity') }}</th>
                                <th class="text-left px-4 py-2">{{ __('messages.incidents.kind') }}</th>
                                <th class="text-left px-4 py-2">{{ __('messages.incidents.summary') }}</th>
                                <th class="text-left px-4 py-2">{{ __('messages.incidents.status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($latestIncidents as $incident)
                                @php
                                    $severity = (int) $incident->severity;
                                    $severityClass = $severity >= 80 ? 'app-chip-danger' : ($severity >= 50 ? 'app-chip-warning' : 'app-chip');
                                    $statusClass = match ((string) $incident->status) {
                                        'open' => 'app-chip-danger',
                                        'ack' => 'app-chip-warning',
                                        'resolved' => 'app-chip-success',
                                        default => 'app-chip',
                                    };
                                    $monitorLabel = $incident->monitoredUrl?->display_name ?? $incident->monitoredUrl?->url ?? '-';
                                @endphp
                                <tr class="border-t">
                                    <td class="px-4 py-2">{{ $incident->detected_at?->toDateTimeString() }}</td>
                                    <td class="px-4 py-2 text-gray-700 dark:text-gray-200">{{ $monitorLabel }}</td>
                                    <td class="px-4 py-2">
                                        <span class="{{ $severityClass }}">{{ $incident->severity }}</span>
                                    </td>
                                    <td class="px-4 py-2 font-mono text-xs">{{ $incident->kind }}</td>
                                    <td class="px-4 py-2">
                                        <a class="text-gray-900 hover:underline dark:text-gray-100" href="{{ route('incidents.show', $incident) }}">
                                            {{ $incident->summary }}
                                        </a>
                                    </td>
                                    <td class="px-4 py-2">
                                        <span class="{{ $statusClass }}">{{ strtoupper((string) $incident->status) }}</span>
                                    </td>
                                </tr>
                            @empty
                                <tr class="border-t">
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-400" colspan="6">
                                        {{ __('messages.dashboard.no_incidents_yet') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="px-4 py-3 border-t">
                    <a href="{{ route('incidents.index') }}" class="app-link">
                        {{ __('messages.nav.incidents') }} -&gt;
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
