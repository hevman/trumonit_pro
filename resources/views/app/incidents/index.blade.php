<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <h2 class="font-semibold text-xl leading-tight text-gray-900 dark:text-gray-100">
                {{ __('messages.incidents.title') }}
            </h2>

            <div class="flex flex-wrap items-center gap-2">
                @php
                    $status = $filters['status'] ?? null;
                    $queryBase = request()->except(['page', 'status']);
                @endphp

                <a href="{{ route('incidents.index', $queryBase) }}" class="{{ empty($status) ? 'app-chip bg-gray-900 text-white dark:bg-gray-100 dark:text-gray-900' : 'app-chip hover:bg-gray-200 dark:hover:bg-gray-700' }}">{{ __('messages.actions.all') }}</a>
                <a href="{{ route('incidents.index', array_merge($queryBase, ['status' => 'open'])) }}" class="{{ $status === 'open' ? 'app-chip bg-gray-900 text-white dark:bg-gray-100 dark:text-gray-900' : 'app-chip hover:bg-gray-200 dark:hover:bg-gray-700' }}">{{ __('messages.incidents.open') }}</a>
                <a href="{{ route('incidents.index', array_merge($queryBase, ['status' => 'ack'])) }}" class="{{ $status === 'ack' ? 'app-chip bg-gray-900 text-white dark:bg-gray-100 dark:text-gray-900' : 'app-chip hover:bg-gray-200 dark:hover:bg-gray-700' }}">{{ __('messages.incidents.ack') }}</a>
                <a href="{{ route('incidents.index', array_merge($queryBase, ['status' => 'resolved'])) }}" class="{{ $status === 'resolved' ? 'app-chip bg-gray-900 text-white dark:bg-gray-100 dark:text-gray-900' : 'app-chip hover:bg-gray-200 dark:hover:bg-gray-700' }}">{{ __('messages.incidents.resolved') }}</a>
            </div>
        </div>
    </x-slot>

    <div class="app-page">
        <div class="app-container">
            @php
                $isOwner = auth()->user()?->role !== 'member';
                $activeFilters = array_filter([
                    'status' => $filters['status'] ?? null,
                    'kind' => $filters['kind'] ?? null,
                    'monitored_url_id' => $filters['monitored_url_id'] ?? null,
                    'assigned_user_id' => $filters['assigned_user_id'] ?? null,
                    'snoozed' => $filters['snoozed'] ?? null,
                    'has_comments' => $filters['has_comments'] ?? null,
                    'tag' => $filters['tag'] ?? null,
                    'severity_min' => $filters['severity_min'] ?? null,
                    'severity_max' => $filters['severity_max'] ?? null,
                    'occurrences_min' => $filters['occurrences_min'] ?? null,
                    'occurrences_max' => $filters['occurrences_max'] ?? null,
                    'detected_from' => $filters['detected_from'] ?? null,
                    'detected_to' => $filters['detected_to'] ?? null,
                    'resolved_from' => $filters['resolved_from'] ?? null,
                    'resolved_to' => $filters['resolved_to'] ?? null,
                    'q' => $filters['q'] ?? null,
                ], fn ($value) => $value !== null && $value !== '');
            @endphp

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
                <section class="app-card">
                    <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('messages.incidents.open') }}</div>
                    <div class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $analytics['status_counts']['open'] ?? 0 }}</div>
                </section>
                <section class="app-card">
                    <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('messages.incidents.ack') }}</div>
                    <div class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $analytics['status_counts']['ack'] ?? 0 }}</div>
                </section>
                <section class="app-card">
                    <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('messages.incidents.resolved') }}</div>
                    <div class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $analytics['status_counts']['resolved'] ?? 0 }}</div>
                </section>
                <section class="app-card">
                    <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('messages.incidents.critical_open') }}</div>
                    <div class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $analytics['critical_open_count'] ?? 0 }}</div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('messages.incidents.critical_open_hint') }}</div>
                </section>
                <section class="app-card">
                    <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('messages.incidents.mttr_30d') }}</div>
                    <div class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">
                        @if (($analytics['mttr_minutes_30d'] ?? null) !== null)
                            {{ (int) $analytics['mttr_minutes_30d'] }} min
                        @else
                            -
                        @endif
                    </div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        {{ __('messages.incidents.incidents_24h') }}: {{ $analytics['incidents_24h_count'] ?? 0 }}
                    </div>
                </section>
            </div>

            <div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
                <section class="app-card app-card-lg xl:col-span-2">
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('messages.incidents.incident_trend_14d') }}</h3>
                        <span class="app-chip">{{ $analytics['trend_total_14d'] ?? 0 }}</span>
                    </div>
                    @php
                        $incidentTrendDrilldownUrls = collect($analytics['trend_dates_14d'] ?? [])
                            ->map(fn ($date) => route('incidents.index', [
                                'detected_from' => $date,
                                'detected_to' => $date,
                            ]))
                            ->values()
                            ->all();
                    @endphp

                    <x-analytics.bar-chart
                        :values="$analytics['trend_values_14d'] ?? []"
                        :labels="$analytics['trend_labels_14d'] ?? []"
                        :drilldown-urls="$incidentTrendDrilldownUrls"
                        :has-data="($analytics['trend_total_14d'] ?? 0) > 0"
                        :series-name="__('messages.incidents.series_incidents')"
                        :legend="__('messages.incidents.legend_daily_incidents')"
                        unit="incidents"
                        tone="indigo"
                        height-class="h-36"
                        :label-step="2"
                        :empty-text="__('messages.incidents.no_incidents_in_window')"
                    />
                </section>

                <section class="app-card app-card-lg">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('messages.incidents.top_kinds') }}</h3>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        {{ __('messages.incidents.avg_severity') }}:
                        @if (($analytics['average_severity'] ?? null) !== null)
                            {{ number_format((float) $analytics['average_severity'], 1) }}
                        @else
                            -
                        @endif
                    </div>
                    <div class="mt-4 space-y-3">
                        @forelse (($analytics['kind_distribution'] ?? []) as $kindRow)
                            @php
                                $kindWidth = (int) round(((int) ($kindRow['total'] ?? 0) / max(1, (int) ($analytics['kind_distribution_max'] ?? 1))) * 100);
                                $kindFilterValue = is_string($kindRow['kind'] ?? null) ? (string) $kindRow['kind'] : null;
                                $kindDrilldownUrl = is_string($kindFilterValue) && $kindFilterValue !== ''
                                    ? route('incidents.index', array_merge(request()->except(['page', 'kind']), ['kind' => $kindFilterValue]))
                                    : null;
                            @endphp
                            <div class="space-y-1">
                                <div class="flex items-center justify-between gap-2 text-xs">
                                    @if (is_string($kindDrilldownUrl))
                                        <a href="{{ $kindDrilldownUrl }}" class="font-mono text-gray-700 underline decoration-dotted underline-offset-2 hover:text-gray-900 dark:text-gray-200 dark:hover:text-gray-100">
                                            {{ $kindRow['kind'] ?? '-' }}
                                        </a>
                                    @else
                                        <span class="font-mono text-gray-700 dark:text-gray-200">{{ $kindRow['kind'] ?? '-' }}</span>
                                    @endif
                                    <span class="text-gray-500 dark:text-gray-400">{{ $kindRow['total'] ?? 0 }}</span>
                                </div>
                                <div class="h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                                    <div class="h-full rounded-full bg-indigo-500" style="width: {{ min(100, max(6, $kindWidth)) }}%"></div>
                                </div>
                            </div>
                        @empty
                            <div class="rounded-xl border border-dashed border-gray-300 px-4 py-6 text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                                {{ __('messages.incidents.no_kind_data') }}
                            </div>
                        @endforelse
                    </div>
                </section>
            </div>

            @php
                $resolvedTrendDrilldownUrls = collect($analytics['resolved_trend_dates_14d'] ?? [])
                    ->map(fn ($date) => route('incidents.index', [
                        'status' => 'resolved',
                        'resolved_from' => $date,
                        'resolved_to' => $date,
                    ]))
                    ->values()
                    ->all();

                $mttrTrendDrilldownUrls = collect($analytics['resolved_trend_dates_14d'] ?? [])
                    ->map(fn ($date) => route('incidents.index', [
                        'status' => 'resolved',
                        'resolved_from' => $date,
                        'resolved_to' => $date,
                    ]))
                    ->values()
                    ->all();
            @endphp

            <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
                <section class="app-card app-card-lg">
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('messages.incidents.resolved_trend_14d') }}</h3>
                        <span class="app-chip">{{ $analytics['resolved_trend_total_14d'] ?? 0 }}</span>
                    </div>

                    <x-analytics.bar-chart
                        :values="$analytics['resolved_trend_values_14d'] ?? []"
                        :labels="$analytics['resolved_trend_labels_14d'] ?? []"
                        :drilldown-urls="$resolvedTrendDrilldownUrls"
                        :has-data="($analytics['resolved_trend_total_14d'] ?? 0) > 0"
                        :series-name="__('messages.incidents.series_resolved')"
                        :legend="__('messages.incidents.legend_daily_resolved')"
                        unit="incidents"
                        tone="emerald"
                        height-class="h-36"
                        :label-step="2"
                        :empty-text="__('messages.incidents.no_resolved_14d')"
                    />
                </section>

                <section class="app-card app-card-lg">
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('messages.incidents.mttr_trend_14d') }}</h3>
                        <span class="app-chip">
                            @if (($analytics['mttr_latest_minutes_14d'] ?? null) !== null)
                                {{ __('messages.incidents.latest_mttr') }}: {{ (int) $analytics['mttr_latest_minutes_14d'] }} min
                            @else
                                {{ __('messages.incidents.latest_mttr') }}: -
                            @endif
                        </span>
                    </div>

                    <x-analytics.bar-chart
                        :values="$analytics['mttr_trend_values_14d'] ?? []"
                        :labels="$analytics['resolved_trend_labels_14d'] ?? []"
                        :drilldown-urls="$mttrTrendDrilldownUrls"
                        :has-data="(bool) ($analytics['mttr_trend_has_data_14d'] ?? false)"
                        :max-value="max(($analytics['mttr_trend_values_14d'] ?? []) ?: [0])"
                        :series-name="__('messages.incidents.series_mttr')"
                        :legend="__('messages.incidents.legend_daily_mttr')"
                        unit="min"
                        tone="amber"
                        height-class="h-36"
                        :label-step="2"
                        :empty-text="__('messages.incidents.no_mttr_14d')"
                    />
                </section>
            </div>

            <div class="app-card">
                <div class="mb-3 flex flex-wrap items-center gap-2">
                    <span class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('messages.incidents.active_filters') }}: {{ count($activeFilters) }}</span>
                    @if ($activeFilters !== [])
                        <a href="{{ route('incidents.index') }}" class="app-link text-xs underline">{{ __('messages.actions.reset') }}</a>
                    @endif
                </div>
                <div class="space-y-3 mb-3">
                    <div class="flex flex-wrap gap-2 items-center">
                        <div class="text-xs text-gray-600 dark:text-gray-400">{{ __('messages.saved_views.title') }}:</div>
                        @if ($isOwner)
                            <a href="{{ route('saved-views.index', ['scope' => 'incidents']) }}" class="app-link text-xs underline">{{ __('messages.actions.manage') }}</a>
                        @endif
                        @forelse ($savedViews as $savedView)
                            @php
                                $savedFilters = is_array($savedView->filters) ? $savedView->filters : [];
                            @endphp
                            <div class="inline-flex items-center gap-1">
                                <a href="{{ route('incidents.index', $savedFilters) }}" class="app-chip hover:bg-gray-200 dark:hover:bg-gray-700">
                                    {{ $savedView->name }}
                                </a>
                                @if ($isOwner)
                                    <form method="POST" action="{{ route('saved-views.destroy', $savedView) }}" data-confirm-submit="{{ __('messages.saved_views.delete_confirm') }}">
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
                            <input type="hidden" name="scope" value="incidents" />
                            @foreach ($activeFilters as $key => $value)
                                <input type="hidden" name="filters[{{ $key }}]" value="{{ $value }}" />
                            @endforeach

                            <div class="w-full sm:w-72">
                                <label class="app-label">{{ __('messages.saved_views.save_as') }}</label>
                                <input name="name" value="" class="app-input" placeholder="e.g. Open high severity" />
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
                    $incidentAdvancedFilterValues = [
                        $filters['assigned_user_id'] ?? null,
                        $filters['snoozed'] ?? null,
                        $filters['has_comments'] ?? null,
                        $filters['tag'] ?? null,
                        $filters['severity_min'] ?? null,
                        $filters['severity_max'] ?? null,
                        $filters['occurrences_min'] ?? null,
                        $filters['occurrences_max'] ?? null,
                        $filters['detected_from'] ?? null,
                        $filters['detected_to'] ?? null,
                        $filters['resolved_from'] ?? null,
                        $filters['resolved_to'] ?? null,
                    ];
                    $advancedIncidentFilterCount = count(array_filter($incidentAdvancedFilterValues, fn ($value) => $value !== null && $value !== ''));
                    $incidentAdvancedOpen = $advancedIncidentFilterCount > 0;
                @endphp
                <form method="GET" action="{{ route('incidents.index') }}" class="flex flex-wrap gap-3 items-end" x-data="{ advancedOpen: @js($incidentAdvancedOpen) }">
                    <div class="w-full flex items-center justify-between">
                        <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            {{ __('messages.actions.advanced_filters') }}
                        </div>
                        <button type="button" class="app-button-secondary" @click="advancedOpen = !advancedOpen" :aria-expanded="advancedOpen.toString()">
                            <span x-show="!advancedOpen">{{ __('messages.actions.show_advanced') }}</span>
                            <span x-show="advancedOpen" x-cloak>{{ __('messages.actions.hide_advanced') }}</span>
                            <span class="app-chip ml-2">{{ $advancedIncidentFilterCount }}</span>
                        </button>
                    </div>

                    <div class="w-full sm:w-72">
                        <label class="app-label">{{ __('messages.actions.search') }}</label>
                        <input name="q" value="{{ $filters['q'] ?? '' }}" class="app-input" placeholder="url, summary, kind, #id" />
                    </div>

                    <div class="w-full sm:w-40">
                        <label class="app-label">{{ __('messages.incidents.status') }}</label>
                        <select name="status" class="app-input">
                            <option value="">{{ __('messages.actions.all') }}</option>
                            <option value="open" {{ ($filters['status'] ?? '') === 'open' ? 'selected' : '' }}>{{ __('messages.incidents.open') }}</option>
                            <option value="ack" {{ ($filters['status'] ?? '') === 'ack' ? 'selected' : '' }}>{{ __('messages.incidents.ack') }}</option>
                            <option value="resolved" {{ ($filters['status'] ?? '') === 'resolved' ? 'selected' : '' }}>{{ __('messages.incidents.resolved') }}</option>
                        </select>
                    </div>

                    <div class="w-full sm:w-56">
                        <label class="app-label">{{ __('messages.incidents.kind') }}</label>
                        <select name="kind" class="app-input">
                            <option value="">{{ __('messages.actions.all') }}</option>
                            @foreach ($kinds as $kind)
                                <option value="{{ $kind }}" {{ ($filters['kind'] ?? '') === $kind ? 'selected' : '' }}>{{ $kind }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="w-full sm:w-72">
                        <label class="app-label">{{ __('messages.monitored_urls.url') }}</label>
                        <select name="monitored_url_id" class="app-input">
                            <option value="">{{ __('messages.actions.all') }}</option>
                            @foreach ($monitoredUrls as $url)
                                @php
                                    $label = $url->display_name ? $url->display_name.' - '.$url->url : $url->url;
                                @endphp
                                <option value="{{ $url->id }}" {{ (string) ($filters['monitored_url_id'] ?? '') === (string) $url->id ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="w-full flex flex-wrap gap-3 items-end" x-show="advancedOpen" x-cloak>
                        <div class="w-full sm:w-60">
                            <label class="app-label">{{ __('messages.incidents.assigned') }}</label>
                            <select name="assigned_user_id" class="app-input">
                                <option value="">{{ __('messages.actions.all') }}</option>
                                @foreach ($tenantUsers as $tenantUser)
                                    <option value="{{ $tenantUser->id }}" {{ (string) ($filters['assigned_user_id'] ?? '') === (string) $tenantUser->id ? 'selected' : '' }}>
                                        {{ $tenantUser->name }} ({{ $tenantUser->email }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="w-full sm:w-44">
                            <label class="app-label">{{ __('messages.incidents.snoozed') }}</label>
                            <select name="snoozed" class="app-input">
                                <option value="">{{ __('messages.actions.all') }}</option>
                                <option value="1" {{ ($filters['snoozed'] ?? '') === '1' ? 'selected' : '' }}>{{ __('messages.incidents.snoozed_active') }}</option>
                                <option value="0" {{ ($filters['snoozed'] ?? '') === '0' ? 'selected' : '' }}>{{ __('messages.incidents.snoozed_not_active') }}</option>
                            </select>
                        </div>

                        <div class="w-full sm:w-44">
                            <label class="app-label">{{ __('messages.incidents.comments') }}</label>
                            <select name="has_comments" class="app-input">
                                <option value="">{{ __('messages.actions.all') }}</option>
                                <option value="1" {{ ($filters['has_comments'] ?? '') === '1' ? 'selected' : '' }}>{{ __('messages.incidents.comments_with') }}</option>
                                <option value="0" {{ ($filters['has_comments'] ?? '') === '0' ? 'selected' : '' }}>{{ __('messages.incidents.comments_without') }}</option>
                            </select>
                        </div>

                        <div class="w-full sm:w-44">
                            <label class="app-label">{{ __('messages.monitored_urls.tags') }}</label>
                            <select name="tag" class="app-input">
                                <option value="">{{ __('messages.actions.all') }}</option>
                                @foreach ($tags as $tagName)
                                    <option value="{{ $tagName }}" {{ ($filters['tag'] ?? '') === $tagName ? 'selected' : '' }}>{{ $tagName }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="w-full sm:w-32">
                            <label class="app-label">{{ __('messages.incidents.severity') }} (min)</label>
                            <input type="number" min="1" max="100" name="severity_min" value="{{ $filters['severity_min'] ?? '' }}" class="app-input" />
                        </div>

                        <div class="w-full sm:w-32">
                            <label class="app-label">{{ __('messages.incidents.severity') }} (max)</label>
                            <input type="number" min="1" max="100" name="severity_max" value="{{ $filters['severity_max'] ?? '' }}" class="app-input" />
                        </div>

                        <div class="w-full sm:w-32">
                            <label class="app-label">{{ __('messages.incidents.occurrences_min') }}</label>
                            <input type="number" min="1" max="1000000" name="occurrences_min" value="{{ $filters['occurrences_min'] ?? '' }}" class="app-input" />
                        </div>

                        <div class="w-full sm:w-32">
                            <label class="app-label">{{ __('messages.incidents.occurrences_max') }}</label>
                            <input type="number" min="1" max="1000000" name="occurrences_max" value="{{ $filters['occurrences_max'] ?? '' }}" class="app-input" />
                        </div>

                        <div class="w-full sm:w-44">
                            <label class="app-label">{{ __('messages.incidents.detected_from') }}</label>
                            <input type="date" name="detected_from" value="{{ $filters['detected_from'] ?? '' }}" class="app-input" />
                        </div>

                        <div class="w-full sm:w-44">
                            <label class="app-label">{{ __('messages.incidents.detected_to') }}</label>
                            <input type="date" name="detected_to" value="{{ $filters['detected_to'] ?? '' }}" class="app-input" />
                        </div>

                        <div class="w-full sm:w-44">
                            <label class="app-label">{{ __('messages.incidents.resolved_from') }}</label>
                            <input type="date" name="resolved_from" value="{{ $filters['resolved_from'] ?? '' }}" class="app-input" />
                        </div>

                        <div class="w-full sm:w-44">
                            <label class="app-label">{{ __('messages.incidents.resolved_to') }}</label>
                            <input type="date" name="resolved_to" value="{{ $filters['resolved_to'] ?? '' }}" class="app-input" />
                        </div>
                    </div>

                    <div class="flex gap-2">
                        <button class="app-button-primary" type="submit">
                            {{ __('messages.actions.filter') }}
                        </button>
                        <a class="app-link px-3 py-2" href="{{ route('incidents.index') }}">
                            {{ __('messages.actions.reset') }}
                        </a>
                    </div>
                </form>
            </div>

            <div class="app-table overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-gray-600 dark:bg-gray-900/70 dark:text-gray-300">
                        <tr>
                            <th class="text-left px-4 py-2">{{ __('messages.incidents.detected_at') }}</th>
                            <th class="text-left px-4 py-2">{{ __('messages.monitored_urls.url') }}</th>
                            <th class="text-left px-4 py-2">{{ __('messages.incidents.severity') }}</th>
                            <th class="text-left px-4 py-2">{{ __('messages.incidents.kind') }}</th>
                            <th class="text-left px-4 py-2">{{ __('messages.incidents.summary') }}</th>
                            <th class="text-left px-4 py-2">{{ __('messages.incidents.status') }}</th>
                            <th class="text-left px-4 py-2">{{ __('messages.incidents.seen') }}</th>
                            <th class="text-left px-4 py-2">{{ __('messages.incidents.assigned') }}</th>
                            <th class="text-left px-4 py-2">{{ __('messages.incidents.snoozed') }}</th>
                            <th class="text-left px-4 py-2">{{ __('messages.monitored_urls.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($incidents as $incident)
                            <tr class="border-t">
                                <td class="px-4 py-2">{{ $incident->detected_at?->toDateTimeString() }}</td>
                                <td class="px-4 py-2">
                                    @if ($incident->monitoredUrl)
                                        <a class="text-gray-900 hover:underline" href="{{ route('monitored-urls.show', $incident->monitoredUrl) }}">
                                            {{ $incident->monitoredUrl->display_name ?? $incident->monitoredUrl->url }}
                                        </a>
                                        @if ($incident->monitoredUrl->tags?->count())
                                            <div class="mt-1 flex flex-wrap gap-2">
                                                @foreach ($incident->monitoredUrl->tags->sortBy('name') as $tag)
                                                    <span class="app-chip">{{ $tag->name }}</span>
                                                @endforeach
                                            </div>
                                        @endif
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="px-4 py-2">{{ $incident->severity }}</td>
                                <td class="px-4 py-2">{{ $incident->kind }}</td>
                                <td class="px-4 py-2">
                                    <a class="text-gray-900 hover:underline" href="{{ route('incidents.show', $incident) }}">
                                        {{ $incident->summary }}
                                    </a>
                                </td>
                                <td class="px-4 py-2">{{ $incident->status }}</td>
                                <td class="px-4 py-2">
                                    <div class="text-xs text-gray-600 dark:text-gray-400">
                                        {{ $incident->occurrences ?? 1 }}x
                                        @if ($incident->last_seen_at)
                                            &middot; {{ $incident->last_seen_at->toDateTimeString() }}
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-2">
                                    @if ($incident->assignedToUser)
                                        <div class="text-sm text-gray-800 dark:text-gray-200">{{ $incident->assignedToUser->name }}</div>
                                        <div class="text-xs text-gray-600 dark:text-gray-400">{{ $incident->assignedToUser->email }}</div>
                                    @else
                                        <span class="text-xs text-gray-500">{{ __('messages.incidents.unassigned') }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2">
                                    @if ($incident->snoozed_until && $incident->snoozed_until->isFuture())
                                        <span class="text-xs text-amber-700 dark:text-amber-300">{{ $incident->snoozed_until->toDateTimeString() }}</span>
                                    @else
                                        <span class="text-xs text-gray-500">{{ __('messages.incidents.snoozed_not_active') }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2">
                                    @if ($isOwner)
                                        <form method="POST" action="{{ route('incidents.update', $incident) }}" class="flex items-center gap-2">
                                            @csrf
                                            @method('PATCH')
                                            <select name="status" class="app-input">
                                                <option value="open" {{ $incident->status === 'open' ? 'selected' : '' }}>{{ __('messages.incidents.open') }}</option>
                                                <option value="ack" {{ $incident->status === 'ack' ? 'selected' : '' }}>{{ __('messages.incidents.ack') }}</option>
                                                <option value="resolved" {{ $incident->status === 'resolved' ? 'selected' : '' }}>{{ __('messages.incidents.resolved') }}</option>
                                            </select>
                                            <button class="app-link" type="submit">{{ __('messages.actions.update') }}</button>
                                        </form>
                                    @else
                                        <span class="text-gray-500 text-xs">{{ __('messages.actions.read_only') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr class="border-t">
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-400" colspan="10">{{ __('messages.incidents.no_incidents_yet') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div>
                {{ $incidents->links() }}
            </div>
        </div>
    </div>
</x-app-layout>

