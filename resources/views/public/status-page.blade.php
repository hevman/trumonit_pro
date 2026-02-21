@php
    $statusTitle = $statusPage['title'] ?? __('Status');
    $statusDescription = is_string($statusPage['description'] ?? null) && $statusPage['description'] !== ''
        ? $statusPage['description']
        : __('Public status page with current component health and incident history.');
    $canonicalUrl = url()->current();
    $ogImage = asset('android-chrome-512x512.png');
    $statusSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'WebPage',
        'name' => $statusTitle,
        'description' => $statusDescription,
        'url' => $canonicalUrl,
        'inLanguage' => app()->getLocale() === 'pl' ? 'pl-PL' : 'en-US',
        'publisher' => [
            '@type' => 'Organization',
            'name' => config('app.name', 'TruMonit'),
            'url' => url('/'),
        ],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="{{ $statusDescription }}">
        <meta name="robots" content="index,follow,max-image-preview:large,max-snippet:-1,max-video-preview:-1">
        <link rel="canonical" href="{{ $canonicalUrl }}">

        <meta property="og:type" content="website">
        <meta property="og:title" content="{{ $statusTitle }}">
        <meta property="og:description" content="{{ $statusDescription }}">
        <meta property="og:url" content="{{ $canonicalUrl }}">
        <meta property="og:image" content="{{ $ogImage }}">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="{{ $statusTitle }}">
        <meta name="twitter:description" content="{{ $statusDescription }}">
        <meta name="twitter:image" content="{{ $ogImage }}">

        <title>{{ $statusTitle }}</title>
        <script type="application/ld+json">{!! json_encode($statusSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
        @include('partials.theme-head')
        @include('partials.favicons')

        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @else
            <script src="https://cdn.tailwindcss.com"></script>
            <script>
                tailwind.config = Object.assign({}, tailwind.config || {}, { darkMode: 'class' });
            </script>
            @include('partials.tailwind-fallback-components')
        @endif
    </head>
    <body class="bg-gray-50 text-gray-900 dark:bg-gray-950 dark:text-gray-100">
        <div class="mx-auto max-w-5xl space-y-6 px-4 py-10 sm:px-6 lg:px-8">
            <header class="app-card">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div class="space-y-2">
                        <h1 class="text-2xl font-semibold">{{ $statusPage['title'] ?? __('Status') }}</h1>
                        @if (! empty($statusPage['description']))
                            <p class="text-gray-700 dark:text-gray-300">{{ $statusPage['description'] }}</p>
                        @endif
                        <div class="text-xs text-gray-500 font-mono dark:text-gray-400">{{ __('messages.status_pages.public_updated') }}: {{ $statusPage['generated_at'] ?? '' }}</div>
                    </div>

                    <button
                        type="button"
                        class="app-button-secondary w-fit"
                        data-theme-toggle
                        aria-label="{{ __('Toggle theme') }}"
                    >
                        <span data-theme-icon aria-hidden="true">&#x263D;</span>
                        <span data-theme-label class="ml-2"></span>
                    </button>
                </div>
            </header>

            @if (($statusPage['has_issues'] ?? false) === true)
                <div class="app-card app-card-danger">
                    <div class="font-medium">{{ __('messages.status_pages.public_some_systems_issues') }}</div>
                </div>
            @else
                <div class="app-card app-card-success">
                    <div class="font-medium">{{ __('messages.status_pages.public_all_systems_operational') }}</div>
                </div>
            @endif

            @php
                $trendValues = is_array($incidentTrend['values'] ?? null) ? $incidentTrend['values'] : [];
                $trendLabels = is_array($incidentTrend['labels'] ?? null) ? $incidentTrend['labels'] : [];
                $trendDates = is_array($incidentTrend['dates'] ?? null) ? $incidentTrend['dates'] : [];
                $selectedIncidentDayValue = is_string($selectedIncidentDay ?? null) ? $selectedIncidentDay : null;
                $selectedComponentStateValue = is_string($selectedComponentState ?? null) ? $selectedComponentState : null;
                $selectedIncidentDayLabel = $selectedIncidentDayValue !== null
                    ? \Illuminate\Support\Carbon::parse($selectedIncidentDayValue)->format('d M Y')
                    : null;
                $selectedComponentStateLabel = match ($selectedComponentStateValue) {
                    'up' => __('messages.monitored_urls.state_up'),
                    'down' => __('messages.monitored_urls.state_down'),
                    'paused' => __('messages.monitored_urls.state_paused'),
                    'unknown' => __('messages.monitored_urls.state_unknown'),
                    default => null,
                };
                $componentStateCountsSafe = is_array($componentStateCounts ?? null) ? $componentStateCounts : [];
                $componentStateTotal = (int) array_sum($componentStateCountsSafe);

                $buildPublicUrl = function (array $params = [], ?string $anchor = null): string {
                    $query = array_merge(request()->query(), $params);

                    foreach ($query as $key => $value) {
                        if ($value === null || $value === '') {
                            unset($query[$key]);
                        }
                    }

                    $queryString = http_build_query($query);

                    return request()->url()
                        .($queryString !== '' ? '?'.$queryString : '')
                        .(is_string($anchor) && $anchor !== '' ? '#'.$anchor : '');
                };

                $trendDrilldownUrls = collect($trendDates)
                    ->map(fn ($date) => $buildPublicUrl(['incident_day' => $date], 'recent-incidents'))
                    ->values()
                    ->all();
            @endphp

            <section class="app-card app-card-lg">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('messages.status_pages.public_incident_trend_7d') }}</h2>
                    <div class="flex items-center gap-2">
                        <span class="app-chip">{{ $incidentTrend['total'] ?? 0 }}</span>
                        @if ($selectedIncidentDayValue !== null)
                            <span class="app-chip-warning">{{ __('messages.status_pages.public_filtered_day') }}: {{ $selectedIncidentDayLabel }}</span>
                            <a href="{{ $buildPublicUrl(['incident_day' => null], 'recent-incidents') }}" class="app-link text-xs underline">{{ __('messages.actions.clear') }}</a>
                        @endif
                    </div>
                </div>

                <x-analytics.bar-chart
                    :values="$trendValues"
                    :labels="$trendLabels"
                    :drilldown-urls="$trendDrilldownUrls"
                    :has-data="($incidentTrend['total'] ?? 0) > 0"
                    :max-value="max($trendValues ?: [0])"
                    :series-name="__('messages.incidents.title')"
                    :legend="__('messages.status_pages.public_incidents_per_day')"
                    :unit="__('incidents')"
                    tone="indigo"
                    height-class="h-32"
                    :label-step="1"
                    :empty-text="__('messages.status_pages.public_no_incidents_7d')"
                />

                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                    {{ __('messages.status_pages.public_click_bar_hint') }}
                </p>
            </section>

            <section id="components" class="app-card">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('messages.status_pages.public_components_filter') }}</span>
                    <a href="{{ $buildPublicUrl(['component_state' => null], 'components') }}" class="{{ $selectedComponentStateValue === null ? 'app-chip bg-gray-900 text-white dark:bg-gray-100 dark:text-gray-900' : 'app-chip hover:bg-gray-200 dark:hover:bg-gray-700' }}">
                        {{ __('messages.actions.all') }} {{ $componentStateTotal }}
                    </a>
                    <a href="{{ $buildPublicUrl(['component_state' => 'up'], 'components') }}" class="{{ $selectedComponentStateValue === 'up' ? 'app-chip bg-emerald-600 text-white dark:bg-emerald-500 dark:text-gray-900' : 'app-chip hover:bg-gray-200 dark:hover:bg-gray-700' }}">
                        {{ __('messages.monitored_urls.state_up') }} {{ (int) ($componentStateCountsSafe['up'] ?? 0) }}
                    </a>
                    <a href="{{ $buildPublicUrl(['component_state' => 'down'], 'components') }}" class="{{ $selectedComponentStateValue === 'down' ? 'app-chip bg-red-600 text-white dark:bg-red-500 dark:text-gray-900' : 'app-chip hover:bg-gray-200 dark:hover:bg-gray-700' }}">
                        {{ __('messages.monitored_urls.state_down') }} {{ (int) ($componentStateCountsSafe['down'] ?? 0) }}
                    </a>
                    <a href="{{ $buildPublicUrl(['component_state' => 'paused'], 'components') }}" class="{{ $selectedComponentStateValue === 'paused' ? 'app-chip bg-gray-700 text-white dark:bg-gray-200 dark:text-gray-900' : 'app-chip hover:bg-gray-200 dark:hover:bg-gray-700' }}">
                        {{ __('messages.monitored_urls.state_paused') }} {{ (int) ($componentStateCountsSafe['paused'] ?? 0) }}
                    </a>
                    <a href="{{ $buildPublicUrl(['component_state' => 'unknown'], 'components') }}" class="{{ $selectedComponentStateValue === 'unknown' ? 'app-chip bg-amber-600 text-white dark:bg-amber-500 dark:text-gray-900' : 'app-chip hover:bg-gray-200 dark:hover:bg-gray-700' }}">
                        {{ __('messages.monitored_urls.state_unknown') }} {{ (int) ($componentStateCountsSafe['unknown'] ?? 0) }}
                    </a>

                    @if ($selectedComponentStateLabel !== null)
                        <span class="ml-auto app-chip-warning">{{ __('messages.status_pages.public_filtered_components') }}: {{ $selectedComponentStateLabel }}</span>
                    @endif
                </div>
            </section>

            <section class="app-table">
                <div class="px-4 py-3 border-b font-medium">{{ __('messages.status_pages.public_components') }}</div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-gray-600 dark:bg-gray-900/70 dark:text-gray-300">
                            <tr>
                                <th class="text-left px-4 py-2">{{ __('messages.status_pages.public_name') }}</th>
                                <th class="text-left px-4 py-2">{{ __('messages.incidents.status') }}</th>
                                <th class="text-left px-4 py-2">{{ __('messages.status_pages.public_last_check') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($monitors as $monitor)
                                <tr class="border-t">
                                    <td class="px-4 py-2">
                                        <div class="font-medium">{{ $monitor['label'] ?? '-' }}</div>
                                    </td>
                                    <td class="px-4 py-2">
                                        @php
                                            $st = $monitor['status'] ?? 'unknown';
                                        @endphp
                                        @if ($st === 'up')
                                            <span class="app-chip-success">{{ __('messages.monitored_urls.state_up') }}</span>
                                        @elseif ($st === 'down')
                                            <span class="app-chip-danger">{{ __('messages.monitored_urls.state_down') }}</span>
                                        @elseif ($st === 'paused')
                                            <span class="app-chip">{{ __('messages.monitored_urls.state_paused') }}</span>
                                        @else
                                            <span class="app-chip">{{ __('messages.monitored_urls.state_unknown') }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2 text-gray-700 dark:text-gray-300">
                                        <div class="font-mono text-xs">{{ $monitor['last_checked_at'] ?? '-' }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ __('http') }}={{ $monitor['http_status'] ?? '-' }}, {{ __('time') }}={{ $monitor['response_time_ms'] ?? '-' }} {{ __('ms') }}
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr class="border-t">
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-400" colspan="3">{{ __('messages.status_pages.public_no_components') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            @if ($openIncidents !== [])
                <section class="app-table">
                    <div class="px-4 py-3 border-b font-medium">{{ __('messages.status_pages.public_active_incidents') }}</div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 text-gray-600 dark:bg-gray-900/70 dark:text-gray-300">
                                <tr>
                                    <th class="text-left px-4 py-2">{{ __('messages.incidents.detected_at') }}</th>
                                    <th class="text-left px-4 py-2">{{ __('messages.incidents.summary') }}</th>
                                    <th class="text-left px-4 py-2">{{ __('messages.incidents.status') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($openIncidents as $incident)
                                    <tr class="border-t">
                                        <td class="px-4 py-2 font-mono text-xs">{{ $incident['detected_at'] ?? '-' }}</td>
                                        <td class="px-4 py-2">{{ $incident['summary'] ?? '-' }}</td>
                                        <td class="px-4 py-2">{{ $incident['status'] ?? '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif

            <section id="recent-incidents" class="app-table">
                <div class="flex items-center justify-between gap-3 px-4 py-3 border-b font-medium">
                    <span>{{ __('messages.status_pages.public_recent_incidents_7d') }}</span>
                    @if ($selectedIncidentDayValue !== null)
                        <span class="app-chip-warning">{{ __('messages.status_pages.public_filtered') }}: {{ $selectedIncidentDayLabel }}</span>
                    @endif
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-gray-600 dark:bg-gray-900/70 dark:text-gray-300">
                            <tr>
                                <th class="text-left px-4 py-2">{{ __('messages.incidents.detected_at') }}</th>
                                <th class="text-left px-4 py-2">{{ __('messages.incidents.summary') }}</th>
                                <th class="text-left px-4 py-2">{{ __('messages.incidents.status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($recentIncidents as $incident)
                                <tr class="border-t">
                                    <td class="px-4 py-2 font-mono text-xs">{{ $incident['detected_at'] ?? '-' }}</td>
                                    <td class="px-4 py-2">{{ $incident['summary'] ?? '-' }}</td>
                                    <td class="px-4 py-2">{{ $incident['status'] ?? '-' }}</td>
                                </tr>
                            @empty
                                <tr class="border-t">
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-400" colspan="3">{{ __('messages.status_pages.public_no_incidents_7d') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <footer class="text-xs text-gray-500 dark:text-gray-400">
                {{ __('messages.status_pages.public_powered_by') }} {{ config('app.name', 'TruMonit') }}
            </footer>
        </div>
    </body>
</html>
