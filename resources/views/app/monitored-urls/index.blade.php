<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <h2 class="font-semibold text-xl leading-tight text-gray-900 dark:text-gray-100">
                {{ __('messages.monitored_urls.title') }}
            </h2>
            @php
                $isOwner = auth()->user()?->role !== 'member';
            @endphp
            @if ($isOwner)
                <div class="flex items-center gap-2">
                    <a href="{{ route('maintenance-windows.index') }}" class="app-button-secondary">
                        {{ __('Maintenance windows') }}
                    </a>
                    <a href="{{ route('monitored-urls.bulk') }}" class="app-button-secondary">
                        {{ __('messages.actions.import_export') }}
                    </a>
                    <a href="{{ route('onboarding.show') }}" class="app-button-primary">
                        {{ __('messages.dashboard.action_create_monitor') }}
                    </a>
                    <a href="{{ route('monitored-urls.create') }}" class="app-button-secondary">
                        {{ __('messages.dashboard.onboarding_advanced') }}
                    </a>
                </div>
            @endif
        </div>
    </x-slot>

    <div class="app-page">
        <div class="app-container">
            @php
                $activeFilters = array_filter([
                    'q' => $filters['q'] ?? null,
                    'tag' => $filters['tag'] ?? null,
                    'scan_mode' => $filters['scan_mode'] ?? null,
                    'is_active' => $filters['is_active'] ?? null,
                    'muted' => $filters['muted'] ?? null,
                    'state' => $filters['state'] ?? null,
                    'frequency_min' => $filters['frequency_min'] ?? null,
                    'frequency_max' => $filters['frequency_max'] ?? null,
                    'timeout_min' => $filters['timeout_min'] ?? null,
                    'timeout_max' => $filters['timeout_max'] ?? null,
                    'last_scanned_from' => $filters['last_scanned_from'] ?? null,
                    'last_scanned_to' => $filters['last_scanned_to'] ?? null,
                ], fn ($value) => $value !== null && $value !== '');
            @endphp

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
                <section class="app-card">
                    <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('messages.monitored_urls.results') }}</div>
                    <div class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $summary['total_results'] ?? 0 }}</div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('messages.monitored_urls.visible_on_page') }}: {{ $summary['visible_count'] ?? 0 }}</div>
                </section>

                <section class="app-card">
                    <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('messages.monitored_urls.visible_uptime') }}</div>
                    <div class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">
                        @if (($summary['visible_uptime_percent'] ?? null) !== null)
                            {{ number_format((float) $summary['visible_uptime_percent'], 1) }}%
                        @else
                            -
                        @endif
                    </div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('messages.monitored_urls.up_monitors') }}: {{ $summary['state_counts']['up'] ?? 0 }}/{{ $summary['active_visible_count'] ?? 0 }}</div>
                </section>

                <section class="app-card">
                    <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('messages.monitored_urls.state_breakdown') }}</div>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <span class="app-chip-success">{{ __('messages.monitored_urls.state_up') }} {{ $summary['state_counts']['up'] ?? 0 }}</span>
                        <span class="app-chip-danger">{{ __('messages.monitored_urls.state_down') }} {{ $summary['state_counts']['down'] ?? 0 }}</span>
                        <span class="app-chip">{{ __('messages.monitored_urls.state_unknown') }} {{ $summary['state_counts']['unknown'] ?? 0 }}</span>
                        <span class="app-chip-warning">{{ __('messages.monitored_urls.state_muted') }} {{ $summary['state_counts']['muted'] ?? 0 }}</span>
                        <span class="app-chip">{{ __('messages.monitored_urls.state_paused') }} {{ $summary['state_counts']['paused'] ?? 0 }}</span>
                    </div>
                </section>

                <section class="app-card">
                    <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('messages.monitored_urls.open_incidents') }}</div>
                    <div class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $summary['open_incidents_visible_total'] ?? 0 }}</div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('messages.monitored_urls.for_visible_monitors') }}</div>
                </section>

                <section class="app-card">
                    <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('messages.monitored_urls.avg_response') }}</div>
                    <div class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">
                        @if (($summary['latest_healthy_response_ms'] ?? null) !== null)
                            {{ (int) $summary['latest_healthy_response_ms'] }} ms
                        @else
                            -
                        @endif
                    </div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('messages.monitored_urls.from_latest_healthy_checks') }}</div>
                </section>
            </div>

            <div class="app-card">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('messages.monitored_urls.quick_state') }}</span>
                    @php
                        $stateBase = request()->except(['state', 'page']);
                    @endphp
                    <a href="{{ route('monitored-urls.index', $stateBase) }}" class="{{ empty($filters['state'] ?? null) ? 'app-chip bg-gray-900 text-white dark:bg-gray-100 dark:text-gray-900' : 'app-chip hover:bg-gray-200 dark:hover:bg-gray-700' }}">{{ __('messages.monitored_urls.all_state') }}</a>
                    <a href="{{ route('monitored-urls.index', array_merge($stateBase, ['state' => 'up'])) }}" class="{{ ($filters['state'] ?? null) === 'up' ? 'app-chip bg-emerald-600 text-white dark:bg-emerald-500 dark:text-gray-900' : 'app-chip hover:bg-gray-200 dark:hover:bg-gray-700' }}">{{ __('messages.monitored_urls.state_up') }}</a>
                    <a href="{{ route('monitored-urls.index', array_merge($stateBase, ['state' => 'down'])) }}" class="{{ ($filters['state'] ?? null) === 'down' ? 'app-chip bg-red-600 text-white dark:bg-red-500 dark:text-gray-900' : 'app-chip hover:bg-gray-200 dark:hover:bg-gray-700' }}">{{ __('messages.monitored_urls.state_down') }}</a>
                    <a href="{{ route('monitored-urls.index', array_merge($stateBase, ['state' => 'muted'])) }}" class="{{ ($filters['state'] ?? null) === 'muted' ? 'app-chip bg-amber-600 text-white dark:bg-amber-500 dark:text-gray-900' : 'app-chip hover:bg-gray-200 dark:hover:bg-gray-700' }}">{{ __('messages.monitored_urls.state_muted') }}</a>
                    <a href="{{ route('monitored-urls.index', array_merge($stateBase, ['state' => 'paused'])) }}" class="{{ ($filters['state'] ?? null) === 'paused' ? 'app-chip bg-gray-700 text-white dark:bg-gray-200 dark:text-gray-900' : 'app-chip hover:bg-gray-200 dark:hover:bg-gray-700' }}">{{ __('messages.monitored_urls.state_paused') }}</a>
                    <span class="ml-auto text-xs text-gray-500 dark:text-gray-400">{{ __('messages.monitored_urls.active_filters') }}: {{ count($activeFilters) }}</span>
                </div>
            </div>

            @php
                $uptimeTrend = is_array($fleetHealthAnalytics['uptime_trend'] ?? null) ? $fleetHealthAnalytics['uptime_trend'] : [];
                $responseP95Trend = is_array($fleetHealthAnalytics['response_p95_trend'] ?? null) ? $fleetHealthAnalytics['response_p95_trend'] : [];
                $uptimeTrendStarts = is_array($uptimeTrend['starts_at'] ?? null) ? $uptimeTrend['starts_at'] : [];
                $uptimeTrendEnds = is_array($uptimeTrend['ends_at'] ?? null) ? $uptimeTrend['ends_at'] : [];
                $responseP95TrendStarts = is_array($responseP95Trend['starts_at'] ?? null) ? $responseP95Trend['starts_at'] : [];
                $responseP95TrendEnds = is_array($responseP95Trend['ends_at'] ?? null) ? $responseP95Trend['ends_at'] : [];
                $uptimeTrendDrilldownUrls = collect($uptimeTrendStarts)
                    ->map(function ($startAt, $index) use ($uptimeTrendEnds) {
                        $endAt = $uptimeTrendEnds[$index] ?? $startAt;

                        return route('monitored-urls.index', [
                            'last_scanned_from' => $startAt,
                            'last_scanned_to' => $endAt,
                        ]);
                    })
                    ->values()
                    ->all();
                $responseP95TrendDrilldownUrls = collect($responseP95TrendStarts)
                    ->map(function ($startAt, $index) use ($responseP95TrendEnds) {
                        $endAt = $responseP95TrendEnds[$index] ?? $startAt;

                        return route('monitored-urls.index', [
                            'last_scanned_from' => $startAt,
                            'last_scanned_to' => $endAt,
                        ]);
                    })
                    ->values()
                    ->all();
            @endphp

            <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
                <section class="app-card app-card-lg">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('messages.monitored_urls.uptime_trend_24h') }}</h3>
                        <span class="app-chip">
                            {{ __('messages.monitored_urls.checks_sampled') }}: {{ (int) ($uptimeTrend['total_samples'] ?? 0) }}
                        </span>
                    </div>

                    <x-analytics.bar-chart
                        :values="$uptimeTrend['values'] ?? []"
                        :labels="$uptimeTrend['labels'] ?? []"
                        :drilldown-urls="$uptimeTrendDrilldownUrls"
                        :has-data="(bool) ($uptimeTrend['has_data'] ?? false)"
                        :max-value="100"
                        :series-name="__('messages.monitored_urls.series_uptime_percent')"
                        :legend="__('messages.monitored_urls.legend_uptime_hourly')"
                        unit="%"
                        tone="emerald"
                        height-class="h-40"
                        :label-step="4"
                        :empty-text="__('messages.monitored_urls.no_uptime_data_24h')"
                    />
                </section>

                <section class="app-card app-card-lg">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('messages.monitored_urls.response_p95_trend_24h') }}</h3>
                        <span class="app-chip">
                            @if (($responseP95Trend['latest_p95_ms'] ?? null) !== null)
                                {{ __('messages.monitored_urls.latest_p95') }}: {{ (int) $responseP95Trend['latest_p95_ms'] }} ms
                            @else
                                {{ __('messages.monitored_urls.latest_p95') }}: -
                            @endif
                        </span>
                    </div>

                    <x-analytics.bar-chart
                        :values="$responseP95Trend['values'] ?? []"
                        :labels="$responseP95Trend['labels'] ?? []"
                        :drilldown-urls="$responseP95TrendDrilldownUrls"
                        :has-data="(bool) ($responseP95Trend['has_data'] ?? false)"
                        :max-value="max(($responseP95Trend['values'] ?? []) ?: [0])"
                        :series-name="__('messages.monitored_urls.series_response_p95')"
                        :legend="__('messages.monitored_urls.legend_response_p95_hourly')"
                        unit="ms"
                        tone="amber"
                        height-class="h-40"
                        :label-step="4"
                        :empty-text="__('messages.monitored_urls.no_response_data_24h')"
                    />
                </section>
            </div>

            <div class="app-card">
                <div class="space-y-3 mb-3">
                    <div class="flex flex-wrap gap-2 items-center">
                        <div class="text-xs text-gray-600 dark:text-gray-400">{{ __('messages.saved_views.title') }}:</div>
                        @if ($isOwner)
                            <a href="{{ route('saved-views.index', ['scope' => 'monitored_urls']) }}" class="app-link text-xs underline">{{ __('messages.actions.manage') }}</a>
                        @endif
                        @forelse ($savedViews as $savedView)
                            @php
                                $savedFilters = is_array($savedView->filters) ? $savedView->filters : [];
                            @endphp
                            <div class="inline-flex items-center gap-1">
                                <a href="{{ route('monitored-urls.index', $savedFilters) }}" class="app-chip hover:bg-gray-200 dark:hover:bg-gray-700">
                                    {{ $savedView->name }}
                                </a>
                                @if ($isOwner)
                                    <form method="POST" action="{{ route('saved-views.destroy', $savedView) }}" onsubmit="return confirm('{{ __('messages.saved_views.delete_confirm') }}');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="text-xs text-gray-500 hover:text-red-700 px-1" type="submit">x</button>
                                    </form>
                                @endif
                            </div>
                        @empty
                            <span class="text-xs text-gray-500">{{ __('messages.saved_views.none') }}</span>
                        @endforelse
                    </div>

                    @if ($isOwner)
                        <form method="POST" action="{{ route('saved-views.store') }}" class="flex flex-wrap gap-2 items-end">
                            @csrf
                            <input type="hidden" name="scope" value="monitored_urls" />
                            @foreach ($activeFilters as $key => $value)
                                <input type="hidden" name="filters[{{ $key }}]" value="{{ $value }}" />
                            @endforeach

                            <div class="w-full sm:w-72">
                                <label class="app-label">{{ __('messages.saved_views.save_as') }}</label>
                                <input name="name" value="" class="app-input" placeholder="e.g. Production monitors" />
                            </div>
                            <div>
                                <button class="app-button-primary" type="submit" {{ $activeFilters === [] ? 'disabled' : '' }}>
                                    {{ __('messages.saved_views.save') }}
                                </button>
                            </div>
                            @if ($activeFilters === [])
                                <div class="text-xs text-gray-500">{{ __('messages.saved_views.empty_hint') }}</div>
                            @endif
                        </form>
                    @endif
                </div>

                @php
                    $lastScannedFromValue = null;
                    $lastScannedToValue = null;
                    $advancedMonitoredFilterValues = [
                        $filters['frequency_min'] ?? null,
                        $filters['frequency_max'] ?? null,
                        $filters['timeout_min'] ?? null,
                        $filters['timeout_max'] ?? null,
                        $filters['last_scanned_from'] ?? null,
                        $filters['last_scanned_to'] ?? null,
                    ];
                    $advancedMonitoredFilterCount = count(array_filter($advancedMonitoredFilterValues, fn ($value) => $value !== null && $value !== ''));
                    $monitoredAdvancedOpen = $advancedMonitoredFilterCount > 0;

                    if (is_string($filters['last_scanned_from'] ?? null) && trim((string) $filters['last_scanned_from']) !== '') {
                        $lastScannedFromValue = \Illuminate\Support\Carbon::parse((string) $filters['last_scanned_from'])->format('Y-m-d\TH:i');
                    }

                    if (is_string($filters['last_scanned_to'] ?? null) && trim((string) $filters['last_scanned_to']) !== '') {
                        $lastScannedToValue = \Illuminate\Support\Carbon::parse((string) $filters['last_scanned_to'])->format('Y-m-d\TH:i');
                    }
                @endphp
                <form method="GET" action="{{ route('monitored-urls.index') }}" class="flex flex-wrap gap-3 items-end" x-data="{ advancedOpen: @js($monitoredAdvancedOpen) }">
                    <div class="w-full flex items-center justify-between">
                        <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            {{ __('messages.actions.advanced_filters') }}
                        </div>
                        <button type="button" class="app-button-secondary" @click="advancedOpen = !advancedOpen" :aria-expanded="advancedOpen.toString()">
                            <span x-show="!advancedOpen">{{ __('messages.actions.show_advanced') }}</span>
                            <span x-show="advancedOpen" x-cloak>{{ __('messages.actions.hide_advanced') }}</span>
                            <span class="app-chip ml-2">{{ $advancedMonitoredFilterCount }}</span>
                        </button>
                    </div>

                    <div class="w-full sm:w-80">
                        <label class="app-label">{{ __('messages.actions.search') }}</label>
                        <input name="q" value="{{ $filters['q'] ?? '' }}" class="app-input" placeholder="url / name" />
                    </div>

                    <div class="w-full sm:w-56">
                        <label class="app-label">{{ __('messages.monitored_urls.tags') }}</label>
                        <select name="tag" class="app-input">
                            <option value="">{{ __('messages.actions.all') }}</option>
                            @foreach ($tags as $tag)
                                <option value="{{ $tag }}" {{ ($filters['tag'] ?? '') === $tag ? 'selected' : '' }}>{{ $tag }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="w-full sm:w-44">
                        <label class="app-label">{{ __('messages.monitored_urls.state') }}</label>
                        <select name="state" class="app-input">
                            <option value="">{{ __('messages.actions.all') }}</option>
                            <option value="up" {{ ($filters['state'] ?? '') === 'up' ? 'selected' : '' }}>{{ __('messages.monitored_urls.state_up') }}</option>
                            <option value="down" {{ ($filters['state'] ?? '') === 'down' ? 'selected' : '' }}>{{ __('messages.monitored_urls.state_down') }}</option>
                            <option value="paused" {{ ($filters['state'] ?? '') === 'paused' ? 'selected' : '' }}>{{ __('messages.monitored_urls.state_paused') }}</option>
                            <option value="muted" {{ ($filters['state'] ?? '') === 'muted' ? 'selected' : '' }}>{{ __('messages.monitored_urls.state_muted') }}</option>
                        </select>
                    </div>

                    <div class="w-full sm:w-44">
                        <label class="app-label">{{ __('messages.monitored_urls.mode') }}</label>
                        <select name="scan_mode" class="app-input">
                            <option value="">{{ __('messages.actions.all') }}</option>
                            <option value="http" {{ ($filters['scan_mode'] ?? '') === 'http' ? 'selected' : '' }}>{{ __('messages.monitored_urls.mode_http') }}</option>
                            <option value="journey" {{ ($filters['scan_mode'] ?? '') === 'journey' ? 'selected' : '' }}>{{ __('messages.monitored_urls.mode_journey') }}</option>
                        </select>
                    </div>

                    <div class="w-full sm:w-44">
                        <label class="app-label">{{ __('messages.monitored_urls.is_active') }}</label>
                        <select name="is_active" class="app-input">
                            <option value="">{{ __('messages.actions.all') }}</option>
                            <option value="1" {{ ($filters['is_active'] ?? '') === '1' ? 'selected' : '' }}>{{ __('messages.actions.yes') }}</option>
                            <option value="0" {{ ($filters['is_active'] ?? '') === '0' ? 'selected' : '' }}>{{ __('messages.actions.no') }}</option>
                        </select>
                    </div>

                    <div class="w-full sm:w-44">
                        <label class="app-label">{{ __('messages.monitored_urls.muted') }}</label>
                        <select name="muted" class="app-input">
                            <option value="">{{ __('messages.actions.all') }}</option>
                            <option value="1" {{ ($filters['muted'] ?? '') === '1' ? 'selected' : '' }}>{{ __('messages.actions.yes') }}</option>
                            <option value="0" {{ ($filters['muted'] ?? '') === '0' ? 'selected' : '' }}>{{ __('messages.actions.no') }}</option>
                        </select>
                    </div>

                    <div class="w-full flex flex-wrap gap-3 items-end" x-show="advancedOpen" x-cloak>
                        <div class="w-full sm:w-32">
                            <label class="app-label">{{ __('messages.monitored_urls.freq_min') }}</label>
                            <input type="number" min="1" max="43200" name="frequency_min" value="{{ $filters['frequency_min'] ?? '' }}" class="app-input" />
                        </div>

                        <div class="w-full sm:w-32">
                            <label class="app-label">{{ __('messages.monitored_urls.freq_max') }}</label>
                            <input type="number" min="1" max="43200" name="frequency_max" value="{{ $filters['frequency_max'] ?? '' }}" class="app-input" />
                        </div>

                        <div class="w-full sm:w-32">
                            <label class="app-label">{{ __('messages.monitored_urls.timeout_min') }}</label>
                            <input type="number" min="1" max="120" name="timeout_min" value="{{ $filters['timeout_min'] ?? '' }}" class="app-input" />
                        </div>

                        <div class="w-full sm:w-32">
                            <label class="app-label">{{ __('messages.monitored_urls.timeout_max') }}</label>
                            <input type="number" min="1" max="120" name="timeout_max" value="{{ $filters['timeout_max'] ?? '' }}" class="app-input" />
                        </div>

                        <div class="w-full sm:w-56">
                            <label class="app-label">{{ __('messages.monitored_urls.last_scanned_from') }}</label>
                            <input type="datetime-local" name="last_scanned_from" value="{{ $lastScannedFromValue ?? '' }}" class="app-input" />
                        </div>

                        <div class="w-full sm:w-56">
                            <label class="app-label">{{ __('messages.monitored_urls.last_scanned_to') }}</label>
                            <input type="datetime-local" name="last_scanned_to" value="{{ $lastScannedToValue ?? '' }}" class="app-input" />
                        </div>
                    </div>

                    <div class="flex gap-2">
                        <button class="app-button-primary" type="submit">
                            {{ __('messages.actions.filter') }}
                        </button>
                        <a class="app-link px-3 py-2" href="{{ route('monitored-urls.index') }}">
                            {{ __('messages.actions.reset') }}
                        </a>
                    </div>
                </form>
            </div>

            @if ($isOwner)
                <div class="app-card">
                    <form id="bulk-form" method="POST" action="{{ route('monitored-urls.bulk.actions') }}" class="flex flex-wrap gap-3 items-end">
                        @csrf

                        <div class="w-full sm:w-64">
                            <label class="app-label">{{ __('messages.monitored_urls.bulk_action') }}</label>
                            <select name="action" class="app-input">
                                <option value="pause">{{ __('messages.monitored_urls.bulk_pause') }}</option>
                                <option value="resume">{{ __('messages.monitored_urls.bulk_resume') }}</option>
                                <option value="set_frequency">{{ __('messages.monitored_urls.bulk_set_frequency') }}</option>
                                <option value="mute">{{ __('messages.monitored_urls.bulk_mute') }}</option>
                                <option value="unmute">{{ __('messages.monitored_urls.bulk_unmute') }}</option>
                                <option value="add_tags">{{ __('messages.monitored_urls.bulk_add_tags') }}</option>
                                <option value="set_tags">{{ __('messages.monitored_urls.bulk_set_tags') }}</option>
                                <option value="remove_tags">{{ __('messages.monitored_urls.bulk_remove_tags') }}</option>
                            </select>
                        </div>

                        <div class="w-full sm:w-44">
                            <label class="app-label">{{ __('messages.monitored_urls.frequency_minutes') }}</label>
                            <input name="frequency_minutes" type="number" min="1" max="43200" class="app-input" placeholder="e.g. 5" />
                        </div>

                        <div class="w-full sm:w-44">
                            <label class="app-label">{{ __('messages.monitored_urls.mute_minutes') }}</label>
                            <input name="mute_minutes" type="number" min="5" max="10080" class="app-input" placeholder="e.g. 60" />
                        </div>

                        <div class="w-full sm:w-72">
                            <label class="app-label">{{ __('messages.monitored_urls.tags') }}</label>
                            <input name="tags" class="app-input" placeholder="prod, staging" />
                        </div>

                        <div>
                            <button class="app-button-primary" type="submit" onclick="return confirm('{{ __('messages.monitored_urls.bulk_apply_confirm') }}');">
                                {{ __('messages.actions.apply') }}
                            </button>
                        </div>
                    </form>
                    <div class="text-xs text-gray-600 dark:text-gray-400 mt-2">{{ __('messages.monitored_urls.bulk_hint') }}</div>
                </div>
            @endif

            <div class="app-table overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-gray-600 dark:bg-gray-900/70 dark:text-gray-300">
                        <tr>
                            @if ($isOwner)
                                <th class="text-left px-4 py-2 w-10">
                                    <input id="bulk-select-all" type="checkbox" class="app-checkbox" />
                                </th>
                            @endif
                            <th class="text-left px-4 py-2">{{ __('messages.monitored_urls.title') }}</th>
                            <th class="text-left px-4 py-2">{{ __('messages.monitored_urls.status') }}</th>
                            <th class="text-left px-4 py-2">{{ __('messages.monitored_urls.http') }}</th>
                            <th class="text-left px-4 py-2">{{ __('messages.monitored_urls.time_ms') }}</th>
                            <th class="text-left px-4 py-2">{{ __('messages.monitored_urls.last_scanned_at') }}</th>
                            <th class="text-left px-4 py-2">{{ __('messages.monitored_urls.health_24h') }}</th>
                            <th class="text-left px-4 py-2">{{ __('messages.monitored_urls.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($monitoredUrls as $url)
                            <tr class="border-t">
                                @if ($isOwner)
                                    <td class="px-4 py-2">
                                        <input form="bulk-form" type="checkbox" name="ids[]" value="{{ $url->id }}" class="bulk-select app-checkbox" />
                                    </td>
                                @endif
                                <td class="px-4 py-2">
                                    <a class="text-gray-900 hover:underline" href="{{ route('monitored-urls.show', $url) }}">
                                        {{ $url->display_name ?? $url->url }}
                                    </a>
                                    @if ($url->display_name)
                                        <div class="text-xs text-gray-600 dark:text-gray-400 break-all">{{ $url->url }}</div>
                                    @endif
                                    @if ($url->tags?->count())
                                        <div class="mt-2 flex flex-wrap gap-2">
                                            @foreach ($url->tags->sortBy('name') as $tag)
                                                <span class="app-chip">{{ $tag->name }}</span>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                                <td class="px-4 py-2">
                                    @php
                                        $latest = $url->latestSnapshot;
                                        $isMuted = $url->muted_until && $url->muted_until->isFuture();
                                    @endphp
                                    @if (! $url->is_active)
                                        <span class="app-chip">{{ __('messages.monitored_urls.state_paused') }}</span>
                                    @elseif (! $latest)
                                        <span class="app-chip">{{ __('messages.monitored_urls.na') }}</span>
                                    @elseif ($latest->error_message)
                                        <span class="app-chip-danger">{{ __('messages.monitored_urls.state_down') }}</span>
                                        <div class="text-xs text-red-700 dark:text-red-300 mt-1">{{ \Illuminate\Support\Str::limit($latest->error_message, 80) }}</div>
                                    @elseif ($latest->http_status !== null && $latest->http_status >= $url->expected_status_min && $latest->http_status <= $url->expected_status_max)
                                        <span class="app-chip-success">{{ __('messages.monitored_urls.state_up') }}</span>
                                    @else
                                        <span class="app-chip-danger">{{ __('messages.monitored_urls.state_down') }}</span>
                                    @endif

                                    @if ($isMuted)
                                        <div class="mt-1">
                                            <span class="app-chip-warning">{{ __('messages.monitored_urls.state_muted') }}</span>
                                            <div class="app-help">{{ $url->muted_until->toDateTimeString() }}</div>
                                        </div>
                                    @endif
                                </td>
                                <td class="px-4 py-2">{{ $latest?->http_status ?? '-' }}</td>
                                <td class="px-4 py-2">{{ $latest?->response_time_ms ?? '-' }}</td>
                                <td class="px-4 py-2">{{ $url->last_scanned_at?->toDateTimeString() ?? '-' }}</td>
                                <td class="px-4 py-2">
                                    @php
                                        $analytics = $monitorHealthAnalytics[$url->id] ?? null;
                                        $openIncidentsCount = (int) ($openIncidentsByMonitor[$url->id] ?? 0);
                                    @endphp
                                    @if (is_array($analytics))
                                        <div class="space-y-1">
                                            <div class="flex items-center gap-2">
                                                @if (($analytics['uptime_percent_24h'] ?? null) !== null)
                                                    <span class="app-chip-success">{{ number_format((float) $analytics['uptime_percent_24h'], 1) }}%</span>
                                                @else
                                                    <span class="app-chip">{{ __('messages.monitored_urls.na') }}</span>
                                                @endif
                                                <span class="text-xs text-gray-500 dark:text-gray-400">{{ (int) ($analytics['samples_24h'] ?? 0) }} {{ __('messages.monitored_urls.samples') }}</span>
                                            </div>
                                            @php
                                                $bars = is_array($analytics['sparkline_24h'] ?? null) ? $analytics['sparkline_24h'] : [];
                                            @endphp
                                            @if ($bars !== [])
                                                <div class="flex items-end gap-0.5 h-8">
                                                    @foreach ($bars as $barValue)
                                                        @php
                                                            $height = $barValue === null ? 10 : max(12, (int) round(($barValue / 100) * 30));
                                                            $barClass = $barValue === null
                                                                ? 'bg-gray-300 dark:bg-gray-700'
                                                                : ($barValue >= 80
                                                                    ? 'bg-emerald-500'
                                                                    : ($barValue >= 50 ? 'bg-amber-500' : 'bg-red-500'));
                                                            $tooltip = $barValue === null ? __('messages.monitored_urls.no_data_yet') : ($barValue.'%');
                                                        @endphp
                                                        <span class="group relative inline-flex w-1.5 items-end">
                                                            <span class="app-analytics-chart-tooltip">{{ $tooltip }}</span>
                                                            <span class="w-1.5 rounded-t {{ $barClass }}" style="height: {{ $height }}px" title="{{ $tooltip }}"></span>
                                                        </span>
                                                    @endforeach
                                                </div>
                                            @endif
                                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                                @if (($analytics['avg_response_ms_24h'] ?? null) !== null)
                                                    {{ __('messages.monitored_urls.avg') }} {{ (int) $analytics['avg_response_ms_24h'] }} ms
                                                @else
                                                    {{ __('messages.monitored_urls.avg') }} -
                                                @endif
                                                &middot;
                                                {{ __('messages.monitored_urls.open_incidents_short') }} {{ $openIncidentsCount }}
                                            </div>
                                        </div>
                                    @else
                                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ __('messages.monitored_urls.no_data_yet') }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2">
                                    <div class="flex gap-2">
                                        @if ($isOwner)
                                            <a class="app-link" href="{{ route('monitored-urls.edit', $url) }}">{{ __('messages.actions.edit') }}</a>
                                            <form method="POST" action="{{ route('monitored-urls.scan', $url) }}">
                                                @csrf
                                                <button class="app-link" type="submit">{{ __('messages.actions.scan_now') }}</button>
                                            </form>
                                            <form method="POST" action="{{ route('monitored-urls.destroy', $url) }}" onsubmit="return confirm('{{ __('Delete this monitor?') }}');">
                                                @csrf
                                                @method('DELETE')
                                                <button class="text-red-700 dark:text-red-300 hover:text-red-900 dark:hover:text-red-200" type="submit">{{ __('messages.actions.delete') }}</button>
                                            </form>
                                        @else
                                            <span class="text-gray-500 text-xs">{{ __('messages.actions.read_only') }}</span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr class="border-t">
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-400" colspan="{{ $isOwner ? 8 : 7 }}">{{ __('messages.monitored_urls.no_monitored_urls_yet') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div>
                {{ $monitoredUrls->links() }}
            </div>
        </div>
    </div>

    @if ($isOwner)
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var selectAll = document.getElementById('bulk-select-all');
                if (!selectAll) return;

                selectAll.addEventListener('change', function () {
                    var checkboxes = document.querySelectorAll('input.bulk-select');
                    for (var i = 0; i < checkboxes.length; i++) {
                        checkboxes[i].checked = selectAll.checked;
                    }
                });
            });
        </script>
    @endif
</x-app-layout>

