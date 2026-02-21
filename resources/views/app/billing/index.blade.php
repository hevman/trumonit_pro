<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Billing / Usage') }}
            </h2>
            <a href="{{ route('dashboard') }}" class="app-link">
                {{ __('messages.nav.dashboard') }}
            </a>
        </div>
    </x-slot>

    <div class="app-page">
        <div class="app-container">
            @if (session('checkout_url'))
                <div class="app-card app-card-info text-sm">
                    {{ __('Checkout URL') }}:
                    <a href="{{ session('checkout_url') }}" target="_blank" rel="noopener" class="underline break-all">
                        {{ session('checkout_url') }}
                    </a>
                </div>
            @endif

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <section class="app-card">
                    <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Workspace') }}</div>
                    <div class="mt-2 text-xl font-semibold text-gray-900 dark:text-gray-100">{{ $tenant->name }}</div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Period start') }}: {{ $periodStart->toDateString() }}</div>
                </section>
                <section class="app-card">
                    <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Plan') }}</div>
                    <div class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $planTier }}</div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Current tier') }}: {{ strtoupper($currentPlanTier) }}</div>
                </section>
                <section class="app-card">
                    <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Enforcement') }}</div>
                    <div class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $enforcementEnabled ? __('ON') : __('OFF') }}</div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        {{ __('Metric') }}: <span class="font-mono">{{ $enforcedMetricKey }}</span>
                    </div>
                </section>
                <section class="app-card">
                    <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Projected cap risk') }}</div>
                    <div class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ (int) $highRiskMetrics }}</div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Metrics projected above cap this month') }}</div>
                </section>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <section class="app-card">
                    <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Forecast breaches') }}</div>
                    <div class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ (int) $forecastBreachCount }}</div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Over cap now or projected to exceed before month end') }}</div>
                </section>
                <section class="app-card">
                    <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Near cap metrics') }}</div>
                    <div class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ (int) $nearCapMetrics }}</div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Current utilization at 80% or higher') }}</div>
                </section>
                <section class="app-card">
                    <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Uncapped metrics') }}</div>
                    <div class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ (int) $uncappedMetrics }}</div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Useful for trend watching, not hard enforcement') }}</div>
                </section>
            </div>

            <section class="app-card app-card-lg">
                @php
                    $executiveRisk = is_array($executiveForecast['risk_breakdown'] ?? null)
                        ? $executiveForecast['risk_breakdown']
                        : ['healthy' => 0, 'near_cap' => 0, 'forecast_breach' => 0, 'over_cap' => 0];
                    $executiveHasData = (bool) ($executiveForecast['has_data'] ?? false);
                    $executiveSoonestDate = is_string($executiveForecast['soonest_breach_date'] ?? null)
                        ? (string) $executiveForecast['soonest_breach_date']
                        : null;
                    $executiveSoonestMetric = is_string($executiveForecast['soonest_breach_metric'] ?? null)
                        ? (string) $executiveForecast['soonest_breach_metric']
                        : null;
                    $executiveMaxUtilizationPct = max(100.0, (float) ($executiveForecast['max_utilization_pct'] ?? 100.0));
                    $executiveMaxProjectedPct = max(100.0, (float) ($executiveForecast['max_projected_cap_pct'] ?? 100.0));
                @endphp
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('Executive cap forecast (month)') }}</h3>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            {{ __('Current and projected utilization for capped metrics. Click bars for monitor/incident drilldown.') }}
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 text-xs">
                        <span class="app-chip-success">{{ __('Healthy') }}: {{ (int) ($executiveRisk['healthy'] ?? 0) }}</span>
                        <span class="app-chip-warning">{{ __('Near cap') }}: {{ (int) ($executiveRisk['near_cap'] ?? 0) }}</span>
                        <span class="app-chip-warning">{{ __('Forecast breach') }}: {{ (int) ($executiveRisk['forecast_breach'] ?? 0) }}</span>
                        <span class="app-chip-danger">{{ __('Over cap') }}: {{ (int) ($executiveRisk['over_cap'] ?? 0) }}</span>
                    </div>
                </div>

                <div class="mt-2 text-xs text-gray-600 dark:text-gray-300">
                    @if ($executiveSoonestDate !== null && $executiveSoonestDate !== '')
                        {{ __('Soonest forecast breach') }}: <span class="font-medium">{{ $executiveSoonestDate }}</span>
                        @if ($executiveSoonestMetric !== null && $executiveSoonestMetric !== '')
                            ({{ $executiveSoonestMetric }})
                        @endif
                    @else
                        {{ __('No forecast breach date in current month profile.') }}
                    @endif
                </div>

                <div class="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-2">
                    <section>
                        <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Current utilization') }}</h4>
                        <x-analytics.bar-chart
                            :values="$executiveForecast['utilization_pct'] ?? []"
                            :labels="$executiveForecast['labels'] ?? []"
                            :drilldown-urls="$executiveForecast['drilldown_urls'] ?? []"
                            :has-data="$executiveHasData"
                            :max-value="$executiveMaxUtilizationPct"
                            :series-name="__('Current cap utilization')"
                            :legend="__('Current utilization by metric')"
                            unit="%"
                            tone="teal"
                            height-class="h-40"
                            :label-step="2"
                            :empty-text="__('No capped metrics for current plan.')"
                        />
                    </section>

                    <section>
                        <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Projected end of month') }}</h4>
                        <x-analytics.bar-chart
                            :values="$executiveForecast['projected_cap_pct'] ?? []"
                            :labels="$executiveForecast['labels'] ?? []"
                            :drilldown-urls="$executiveForecast['drilldown_urls'] ?? []"
                            :has-data="$executiveHasData"
                            :max-value="$executiveMaxProjectedPct"
                            :series-name="__('Projected cap utilization')"
                            :legend="__('Projected EOM utilization by metric')"
                            unit="%"
                            tone="amber"
                            height-class="h-40"
                            :label-step="2"
                            :empty-text="__('No capped metrics for current plan.')"
                        />
                    </section>
                </div>
            </section>

            <section class="app-card app-card-lg">
                @php
                    $selectedMetricKey = (string) ($selectedMetric['key'] ?? '');
                    $selectedMetricLabel = (string) ($selectedMetric['label'] ?? $selectedMetricKey);
                    $selectedMetricUnit = (string) ($selectedMetric['unit'] ?? 'events');
                    $selectedMetricLatestValue = $selectedMetric['latest_value'] ?? null;
                    $selectedMetricDay = is_string($selectedMetric['selected_day'] ?? null) ? (string) $selectedMetric['selected_day'] : null;
                    $selectedMetricDayValue = $selectedMetric['selected_day_value'] ?? null;
                    $selectedMetricDayCapPct = $selectedMetric['selected_day_cap_pct'] ?? null;
                    $selectedMetricDayDrilldownUrl = is_string($selectedMetric['selected_day_drilldown_url'] ?? null) ? (string) $selectedMetric['selected_day_drilldown_url'] : null;
                @endphp
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('Metric trend explorer (daily)') }}</h3>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Per-metric daily usage trend with drilldown links to related monitors/incidents views.
                        </p>
                    </div>
                    <form method="GET" action="{{ route('billing.index') }}" class="flex flex-wrap gap-2 items-end">
                        <div class="min-w-72">
                            <label class="app-label">{{ __('Metric') }}</label>
                            <select name="metric_key" class="app-input">
                                @foreach ($metricDefinitions as $metricKey => $metricLabel)
                                    <option value="{{ $metricKey }}" @selected($selectedMetricKey === $metricKey)>{{ $metricLabel }}</option>
                                @endforeach
                            </select>
                        </div>
                        @if ($selectedMetricDay)
                            <input type="hidden" name="metric_day" value="{{ $selectedMetricDay }}" />
                        @endif
                        <button type="submit" class="app-button-primary">{{ __('Show trend') }}</button>
                        <a href="{{ route('billing.index', ['metric_key' => $selectedMetricKey]) }}" class="app-link px-3 py-2">{{ __('Reset day') }}</a>
                    </form>
                </div>

                <div class="mt-3 flex flex-wrap items-center gap-3 text-xs text-gray-500 dark:text-gray-400">
                    <span class="app-chip">{{ $selectedMetricLabel }}</span>
                    <span>{{ __('Latest non-zero') }}: {{ $selectedMetricLatestValue !== null ? number_format((float) $selectedMetricLatestValue, $selectedMetricUnit === 'MB' ? 2 : 0) : '-' }} {{ $selectedMetricUnit }}</span>
                    @if ($selectedMetricDay)
                        <span>{{ __('Selected day') }}: {{ $selectedMetricDay }}</span>
                        <span>{{ __('Value') }}: {{ $selectedMetricDayValue !== null ? number_format((float) $selectedMetricDayValue, $selectedMetricUnit === 'MB' ? 2 : 0) : '-' }} {{ $selectedMetricUnit }}</span>
                        @if ($selectedMetricDayCapPct !== null)
                            <span>{{ __('Daily cap share') }}: {{ number_format((float) $selectedMetricDayCapPct, 1) }}%</span>
                        @endif
                        @if ($selectedMetricDayDrilldownUrl)
                            <a href="{{ $selectedMetricDayDrilldownUrl }}" class="app-link underline">{{ __('Open selected day drilldown') }}</a>
                        @endif
                    @endif
                </div>

                <x-analytics.bar-chart
                    :values="$selectedMetric['values'] ?? []"
                    :labels="$selectedMetric['labels'] ?? []"
                    :drilldown-urls="$selectedMetric['drilldown_urls'] ?? []"
                    :has-data="(bool) ($selectedMetric['has_data'] ?? false)"
                    :max-value="$selectedMetric['max_value'] ?? null"
                    :series-name="$selectedMetricLabel"
                    :legend="$selectedMetricLabel.' per day'"
                    :unit="$selectedMetricUnit"
                    tone="indigo"
                    height-class="h-40"
                    :label-step="3"
                    :empty-text="__('No usage data for this metric in current month.')"
                />
            </section>

            <div class="app-card space-y-4">
                <div class="flex items-center justify-between gap-2">
                    <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ __('Plan management') }}</div>
                    <div class="text-xs text-gray-600 dark:text-gray-400">{{ __('Payment provider') }}: {{ $paymentProvider }}</div>
                </div>

                <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
                    @foreach ($availablePlanTiers as $tier)
                        @php
                            $tierNormalized = strtolower((string) $tier);
                            $isCurrentTier = $tierNormalized === strtolower((string) $currentPlanTier);
                            $tierNameKey = match ($tierNormalized) {
                                'free' => 'messages.welcome.price_free_name',
                                'lite' => 'messages.welcome.price_lite_name',
                                'pro' => 'messages.welcome.price_pro_name',
                                'enterprise' => 'messages.welcome.price_enterprise_name',
                                default => null,
                            };
                            $tierValueKey = match ($tierNormalized) {
                                'free' => 'messages.welcome.price_free_value',
                                'lite' => 'messages.welcome.price_lite_value',
                                'pro' => 'messages.welcome.price_pro_value',
                                'enterprise' => 'messages.welcome.price_enterprise_value',
                                default => null,
                            };
                            $tierTextKey = match ($tierNormalized) {
                                'free' => 'messages.welcome.price_free_text',
                                'lite' => 'messages.welcome.price_lite_text',
                                'pro' => 'messages.welcome.price_pro_text',
                                'enterprise' => 'messages.welcome.price_enterprise_text',
                                default => null,
                            };
                            $tierName = $tierNameKey ? __($tierNameKey) : strtoupper($tierNormalized);
                            $tierValue = $tierValueKey ? __($tierValueKey) : '-';
                            $tierText = $tierTextKey ? __($tierTextKey) : '';
                        @endphp
                        <article class="rounded-xl border p-4 {{ $isCurrentTier ? 'border-indigo-500 bg-indigo-50/40 dark:border-indigo-400 dark:bg-indigo-500/10' : 'border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900' }}">
                            <div class="flex items-center justify-between gap-2">
                                <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $tierName }}</h4>
                                @if ($isCurrentTier)
                                    <span class="app-chip-success">{{ __('Current tier') }}</span>
                                @elseif ($tierNormalized === 'pro')
                                    <span class="app-chip">{{ __('messages.welcome.price_recommended') }}</span>
                                @endif
                            </div>
                            <p class="mt-1 text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $tierValue }}</p>
                            @if ($tierText !== '')
                                <p class="mt-2 text-xs text-gray-600 dark:text-gray-300">{{ $tierText }}</p>
                            @endif
                        </article>
                    @endforeach
                </div>

                <form method="POST" action="{{ route('billing.plan.change') }}" class="grid gap-3 md:grid-cols-3 md:items-end">
                    @csrf
                    <div>
                        <label class="app-label">{{ __('Target plan tier') }}</label>
                        <select name="target_plan_tier" class="app-input">
                            @foreach ($availablePlanTiers as $tier)
                                <option value="{{ $tier }}" @selected(old('target_plan_tier') === $tier)>
                                    {{ strtoupper($tier) }}{{ $tier === $currentPlanTier ? ' (current)' : '' }}
                                </option>
                            @endforeach
                        </select>
                        @error('target_plan_tier')
                            <div class="text-xs text-red-600 mt-1">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="md:col-span-2">
                        <button class="app-button-primary" type="submit">
                            {{ __('Start plan change') }}
                        </button>
                    </div>
                </form>

                <div class="app-table overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-gray-600 dark:bg-gray-900/70 dark:text-gray-300">
                            <tr>
                                <th class="text-left px-3 py-2 font-medium">{{ __('Session') }}</th>
                                <th class="text-left px-3 py-2 font-medium">{{ __('Target plan') }}</th>
                                <th class="text-left px-3 py-2 font-medium">{{ __('Provider') }}</th>
                                <th class="text-left px-3 py-2 font-medium">{{ __('Status') }}</th>
                                <th class="text-left px-3 py-2 font-medium">{{ __('Created') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($checkoutSessions as $checkoutSession)
                                <tr>
                                    <td class="px-3 py-2 font-mono text-xs">#{{ $checkoutSession->id }}</td>
                                    <td class="px-3 py-2 uppercase">{{ $checkoutSession->target_plan_tier }}</td>
                                    <td class="px-3 py-2">{{ $checkoutSession->provider }}</td>
                                    <td class="px-3 py-2">{{ $checkoutSession->status }}</td>
                                    <td class="px-3 py-2">{{ optional($checkoutSession->created_at)->toDateTimeString() }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-3 py-4 text-center text-gray-500 dark:text-gray-400">{{ __('No checkout sessions yet.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="app-card space-y-4">
                <div class="flex items-center justify-between gap-2">
                    <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ __('Weekly report packs') }}</div>
                    <div class="text-xs text-gray-600 dark:text-gray-400">{{ __('JSON + CSV artifacts from scheduled digest runs') }}</div>
                </div>

                <div class="app-table overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-gray-600 dark:bg-gray-900/70 dark:text-gray-300">
                            <tr>
                                <th class="text-left px-3 py-2 font-medium">{{ __('Window') }}</th>
                                <th class="text-left px-3 py-2 font-medium">{{ __('Generated') }}</th>
                                <th class="text-left px-3 py-2 font-medium">{{ __('Dispatch mode') }}</th>
                                <th class="text-right px-3 py-2 font-medium">{{ __('Scans') }}</th>
                                <th class="text-right px-3 py-2 font-medium">{{ __('Opened') }}</th>
                                <th class="text-right px-3 py-2 font-medium">{{ __('Resolved') }}</th>
                                <th class="text-right px-3 py-2 font-medium">{{ __('Uptime') }}</th>
                                <th class="text-right px-3 py-2 font-medium">{{ __('Downloads') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($weeklyDigestReports as $weeklyDigestReport)
                                @php
                                    $summary = is_array($weeklyDigestReport->summary ?? null) ? $weeklyDigestReport->summary : [];
                                    $windowStart = $weeklyDigestReport->week_start_date?->toDateString() ?? '-';
                                    $windowEnd = $weeklyDigestReport->week_end_date?->toDateString() ?? '-';
                                    $generatedAt = $weeklyDigestReport->generated_at?->toDateTimeString() ?? $weeklyDigestReport->created_at?->toDateTimeString();
                                    $uptimePercent = is_numeric($summary['uptime_percent'] ?? null)
                                        ? number_format((float) $summary['uptime_percent'], 2).'%'
                                        : '-';
                                @endphp
                                <tr>
                                    <td class="px-3 py-2 font-mono text-xs">{{ $windowStart }} .. {{ $windowEnd }}</td>
                                    <td class="px-3 py-2">{{ $generatedAt ?? '-' }}</td>
                                    <td class="px-3 py-2 uppercase">{{ (string) $weeklyDigestReport->dispatch_mode }}</td>
                                    <td class="px-3 py-2 text-right">{{ number_format((int) ($summary['scans_total'] ?? 0)) }}</td>
                                    <td class="px-3 py-2 text-right">{{ number_format((int) ($summary['incidents_opened_total'] ?? 0)) }}</td>
                                    <td class="px-3 py-2 text-right">{{ number_format((int) ($summary['incidents_resolved_total'] ?? 0)) }}</td>
                                    <td class="px-3 py-2 text-right">{{ $uptimePercent }}</td>
                                    <td class="px-3 py-2 text-right">
                                        <div class="inline-flex items-center gap-2">
                                            <a href="{{ route('billing.weekly-digest-reports.download', ['weeklyDigestReport' => $weeklyDigestReport->id, 'format' => 'json']) }}" class="app-link underline text-xs">{{ __('JSON') }}</a>
                                            <span class="text-gray-300">|</span>
                                            <a href="{{ route('billing.weekly-digest-reports.download', ['weeklyDigestReport' => $weeklyDigestReport->id, 'format' => 'csv']) }}" class="app-link underline text-xs">{{ __('CSV') }}</a>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-3 py-4 text-center text-gray-500 dark:text-gray-400">{{ __('No weekly report packs yet.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
                <section class="app-card app-card-lg">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('Daily scans (current month)') }}</h3>
                    @php
                        $scanTrendDates = is_array($metricsTrend['dates'] ?? null) ? $metricsTrend['dates'] : [];
                        $scanTrendDrilldownUrls = collect($scanTrendDates)
                            ->map(fn ($date) => route('monitored-urls.index', [
                                'last_scanned_from' => $date,
                                'last_scanned_to' => $date,
                            ]))
                            ->values()
                            ->all();
                    @endphp
                    <x-analytics.bar-chart
                        :values="$metricsTrend['scans'] ?? []"
                        :labels="$metricsTrend['labels'] ?? []"
                        :drilldown-urls="$scanTrendDrilldownUrls"
                        :max-value="$metricsTrend['max_scans'] ?? null"
                        :series-name="__('Scans')"
                        :legend="__('Scan volume per day')"
                        :unit="__('events')"
                        tone="indigo"
                        height-class="h-40"
                        :label-step="3"
                        :empty-text="__('No scan usage data yet for current month.')"
                    />
                </section>

                <section class="app-card app-card-lg">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('Daily incidents (current month)') }}</h3>
                    @php
                        $incidentTrendDates = is_array($metricsTrend['dates'] ?? null) ? $metricsTrend['dates'] : [];
                        $incidentTrendDrilldownUrls = collect($incidentTrendDates)
                            ->map(fn ($date) => route('incidents.index', [
                                'detected_from' => $date,
                                'detected_to' => $date,
                            ]))
                            ->values()
                            ->all();
                    @endphp
                    <x-analytics.bar-chart
                        :values="$metricsTrend['incidents'] ?? []"
                        :labels="$metricsTrend['labels'] ?? []"
                        :drilldown-urls="$incidentTrendDrilldownUrls"
                        :max-value="$metricsTrend['max_incidents'] ?? null"
                        :series-name="__('Incidents')"
                        :legend="__('Incident volume per day')"
                        :unit="__('incidents')"
                        tone="amber"
                        height-class="h-40"
                        :label-step="3"
                        :empty-text="__('No incident usage data yet for current month.')"
                    />
                </section>
            </div>

            <div class="app-table overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-gray-600 dark:bg-gray-900/70 dark:text-gray-300">
                        <tr>
                            <th class="text-left px-4 py-2 font-medium">{{ __('Metric') }}</th>
                            <th class="text-right px-4 py-2 font-medium">{{ __('Used') }}</th>
                            <th class="text-right px-4 py-2 font-medium">{{ __('Included') }}</th>
                            <th class="text-right px-4 py-2 font-medium">{{ __('Cap') }}</th>
                            <th class="text-right px-4 py-2 font-medium">{{ __('Run-rate/day') }}</th>
                            <th class="text-left px-4 py-2 font-medium">{{ __('Utilization') }}</th>
                            <th class="text-right px-4 py-2 font-medium">{{ __('Projected EOM') }}</th>
                            <th class="text-right px-4 py-2 font-medium">{{ __('Days to cap') }}</th>
                            <th class="text-right px-4 py-2 font-medium">{{ __('Forecast breach') }}</th>
                            <th class="text-left px-4 py-2 font-medium">{{ __('Risk') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($rows as $row)
                            @php
                                $isStorageMetric = (string) ($row['key'] ?? '') === \App\Services\BillingUsageService::METRIC_STORAGE_BYTES_ADDED;
                                $used = (int) ($row['used'] ?? 0);
                                $cap = (int) ($row['cap'] ?? 0);
                                $included = (int) ($row['included'] ?? 0);
                                $utilization = $row['utilization_pct'] ?? null;
                                $projectedEndOfMonth = (int) ($row['projected_end_of_month'] ?? 0);
                                $projectedCapPct = $row['projected_cap_pct'] ?? null;
                                $dailyRunRate = (float) ($row['daily_run_rate'] ?? 0);
                                $daysToCap = $row['days_to_cap'] ?? null;
                                $forecastBreachDate = $row['forecast_breach_date'] ?? null;
                                $riskLevel = (string) ($row['risk_level'] ?? 'healthy');
                                $isNearCap = $cap > 0 && $used >= (int) floor($cap * 0.8);
                                $isOverCap = $cap > 0 && $used > $cap;
                                $isProjectedOver = $cap > 0 && is_numeric($projectedCapPct) && (float) $projectedCapPct >= 100;
                                $barWidth = $utilization !== null ? min(100, max(0, (int) round((float) $utilization))) : 0;
                                $metricQuery = array_merge(request()->except(['page']), ['metric_key' => $row['key']]);
                                $metricLink = route('billing.index', $metricQuery);
                                $usedDisplay = $isStorageMetric ? number_format($used / 1_048_576, 2).' MB' : number_format($used);
                                $includedDisplay = $included > 0 ? ($isStorageMetric ? number_format($included / 1_048_576, 2).' MB' : number_format($included)) : '-';
                                $capDisplay = $cap > 0 ? ($isStorageMetric ? number_format($cap / 1_048_576, 2).' MB' : number_format($cap)) : __('unlimited');
                                $projectedDisplay = $isStorageMetric ? number_format($projectedEndOfMonth / 1_048_576, 2).' MB' : number_format($projectedEndOfMonth);
                                $dailyRunRateDisplay = $isStorageMetric ? number_format($dailyRunRate / 1_048_576, 2).' MB' : number_format($dailyRunRate, 2);
                                $riskChipClass = match ($riskLevel) {
                                    'over_cap' => 'app-chip-danger',
                                    'forecast_breach' => 'app-chip-warning',
                                    'near_cap' => 'app-chip-warning',
                                    'uncapped' => 'app-chip',
                                    default => 'app-chip-success',
                                };
                                $riskLabel = match ($riskLevel) {
                                    'over_cap' => __('Over cap'),
                                    'forecast_breach' => __('Forecast breach'),
                                    'near_cap' => __('Near cap'),
                                    'uncapped' => __('Uncapped'),
                                    default => __('Healthy'),
                                };
                            @endphp
                            <tr class="{{ $isOverCap ? 'bg-red-50 dark:bg-red-950/20' : ($isProjectedOver ? 'bg-amber-50 dark:bg-amber-950/20' : ($isNearCap ? 'bg-amber-50/60 dark:bg-amber-950/10' : '')) }}">
                                <td class="px-4 py-2">
                                    <a href="{{ $metricLink }}" class="font-medium text-gray-900 underline decoration-dotted underline-offset-2 hover:text-gray-700">{{ $row['label'] }}</a>
                                    <div class="text-xs text-gray-500 font-mono">{{ $row['key'] }}</div>
                                </td>
                                <td class="px-4 py-2 text-right">{{ $usedDisplay }}</td>
                                <td class="px-4 py-2 text-right">{{ $includedDisplay }}</td>
                                <td class="px-4 py-2 text-right">{{ $capDisplay }}</td>
                                <td class="px-4 py-2 text-right">{{ $dailyRunRateDisplay }}</td>
                                <td class="px-4 py-2">
                                    @if ($utilization !== null)
                                        <div class="min-w-[190px]">
                                            <div class="mb-1 flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                                                <span>{{ number_format((float) $utilization, 1) }}%</span>
                                                @if ($projectedCapPct !== null)
                                                    <span>proj {{ number_format((float) $projectedCapPct, 1) }}%</span>
                                                @endif
                                            </div>
                                            <div class="h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                                                <div class="h-full rounded-full {{ $isOverCap ? 'bg-red-500' : ($isProjectedOver ? 'bg-amber-500' : 'bg-indigo-500') }}" style="width: {{ $barWidth }}%"></div>
                                            </div>
                                        </div>
                                    @else
                                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ __('No cap') }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-right">
                                    {{ $projectedDisplay }}
                                </td>
                                <td class="px-4 py-2 text-right">
                                    {{ is_numeric($daysToCap) ? (int) $daysToCap : '-' }}
                                </td>
                                <td class="px-4 py-2 text-right">
                                    {{ is_string($forecastBreachDate) ? $forecastBreachDate : '-' }}
                                </td>
                                <td class="px-4 py-2">
                                    <span class="{{ $riskChipClass }}">{{ $riskLabel }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="px-4 py-6 text-center text-gray-500">
                                    {{ __('No metrics yet for current period.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
