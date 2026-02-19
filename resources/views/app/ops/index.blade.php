<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight dark:text-gray-100">
                {{ __('Ops Center') }}
            </h2>
            <a href="{{ route('dashboard') }}" class="app-link">
                {{ __('messages.nav.dashboard') }}
            </a>
        </div>
    </x-slot>

    @php
        $checks = is_array($drillReport['checks'] ?? null) ? $drillReport['checks'] : [];
        $queueLagP95 = data_get($checks, 'queue_lag.data.scan_lag_p95_seconds');
        $queueWaitP95 = data_get($checks, 'queue_wait.data.queue_wait_p95_ms');
        $storageMaxUsage = data_get($checks, 'storage_quota.data.max_usage_percent');
        $probeGap = (int) data_get($checks, 'private_probe_coverage.data.due_without_online_probe_total', 0);
        $overallStatus = strtoupper((string) ($drillReport['overall_status'] ?? 'pass'));
        $recommendations = is_array($drillReport['recommendations'] ?? null) ? $drillReport['recommendations'] : [];
        $envOverrides = is_array($tuneReport['env_overrides'] ?? null) ? $tuneReport['env_overrides'] : [];
        $scaleAction = (string) ($tuneReport['scale_action'] ?? '');
        $scaleCommand = (string) ($tuneReport['suggested_scale_command'] ?? '');
        $selectedWindowHours = is_numeric($opsWindowHours ?? null) ? (int) $opsWindowHours : null;
        $selectedCurrentWorkers = is_numeric($opsCurrentWorkers ?? null) ? (int) $opsCurrentWorkers : null;
        $artifactTrend = is_array($artifactTrends ?? null) ? $artifactTrends : [];
        $artifactStatusCounts = is_array($artifactTrend['status_counts'] ?? null)
            ? $artifactTrend['status_counts']
            : ['pass' => 0, 'warn' => 0, 'fail' => 0, 'other' => 0];
        $artifactHasData = (bool) ($artifactTrend['has_data'] ?? false);
        $opsOwnershipResolved = is_array($opsOwnership ?? null) ? $opsOwnership : [];
        $opsEscalationChannelsResolved = is_array($opsEscalationChannels ?? null) ? $opsEscalationChannels : [];
        $opsEscalationTargetsResolved = is_array($opsEscalationTargets ?? null) ? $opsEscalationTargets : [];
        $opsReadinessState = is_array($opsReadiness ?? null) ? $opsReadiness : [];
        $opsGoNoGoReady = (bool) ($opsReadinessState['ready_for_go_no_go'] ?? false);
    @endphp

    <div class="app-page">
        <div class="app-container">
            <section class="app-card app-card-lg space-y-4">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('Ops profile') }}</h3>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Tune analysis window and worker baseline for drill + plan generation.') }}</p>
                    </div>
                    <form method="GET" action="{{ route('ops-center.index') }}" class="grid gap-2 sm:grid-cols-3 sm:items-end">
                        <div>
                            <label class="app-label">{{ __('Window hours') }}</label>
                            <input name="ops_window_hours" type="number" min="1" max="720" step="1" value="{{ $selectedWindowHours ?? old('ops_window_hours') }}" class="app-input" placeholder="24" />
                        </div>
                        <div>
                            <label class="app-label">{{ __('Current workers') }}</label>
                            <input name="ops_current_workers" type="number" min="0" max="10000" step="1" value="{{ $selectedCurrentWorkers ?? old('ops_current_workers') }}" class="app-input" placeholder="4" />
                        </div>
                        <button type="submit" class="app-button-primary">{{ __('Refresh') }}</button>
                    </form>
                </div>

                <form method="POST" action="{{ route('ops-center.drill.run') }}" class="flex flex-wrap items-center gap-2">
                    @csrf
                    @if ($selectedWindowHours !== null)
                        <input type="hidden" name="ops_window_hours" value="{{ $selectedWindowHours }}" />
                    @endif
                    @if ($selectedCurrentWorkers !== null)
                        <input type="hidden" name="ops_current_workers" value="{{ $selectedCurrentWorkers }}" />
                    @endif
                    <button type="submit" class="app-button-secondary">{{ __('Run drill now') }}</button>
                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ __('Creates JSON artifact in `storage/app/ops-drills`.') }}</span>
                </form>
            </section>

            <section class="app-card app-card-lg space-y-4">
                <div class="flex flex-col gap-2 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('Operational ownership') }}</h3>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Single source for service owner, on-call, and escalation endpoints used during incidents.') }}</p>
                    </div>
                    <span class="{{ $opsGoNoGoReady ? 'app-chip-success' : 'app-chip-warning' }}">
                        {{ $opsGoNoGoReady ? __('READY') : __('ACTION REQUIRED') }}
                    </span>
                </div>

                <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <div class="app-table overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 text-gray-600 dark:bg-gray-900/70 dark:text-gray-300">
                                <tr>
                                    <th class="text-left px-3 py-2 font-medium">{{ __('Role') }}</th>
                                    <th class="text-left px-3 py-2 font-medium">{{ __('Assigned') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr>
                                    <td class="px-3 py-2">{{ __('Service owner') }}</td>
                                    <td class="px-3 py-2 font-mono text-xs">{{ (string) ($opsOwnershipResolved['service_owner'] ?? __('missing')) }}</td>
                                </tr>
                                <tr>
                                    <td class="px-3 py-2">{{ __('Primary on-call') }}</td>
                                    <td class="px-3 py-2 font-mono text-xs">{{ (string) ($opsOwnershipResolved['primary_on_call'] ?? __('missing')) }}</td>
                                </tr>
                                <tr>
                                    <td class="px-3 py-2">{{ __('Secondary on-call') }}</td>
                                    <td class="px-3 py-2 font-mono text-xs">{{ (string) ($opsOwnershipResolved['secondary_on_call'] ?? __('missing')) }}</td>
                                </tr>
                                <tr>
                                    <td class="px-3 py-2">{{ __('Incident commander') }}</td>
                                    <td class="px-3 py-2 font-mono text-xs">{{ (string) ($opsOwnershipResolved['incident_commander'] ?? __('missing')) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="space-y-3">
                        <div class="app-table overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead class="bg-gray-50 text-gray-600 dark:bg-gray-900/70 dark:text-gray-300">
                                    <tr>
                                        <th class="text-left px-3 py-2 font-medium">{{ __('Escalation route') }}</th>
                                        <th class="text-left px-3 py-2 font-medium">{{ __('Value') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr>
                                        <td class="px-3 py-2">{{ __('Sev-1 channel') }}</td>
                                        <td class="px-3 py-2 font-mono text-xs">{{ (string) ($opsEscalationChannelsResolved['sev1'] ?? __('missing')) }}</td>
                                    </tr>
                                    <tr>
                                        <td class="px-3 py-2">{{ __('Sev-2 channel') }}</td>
                                        <td class="px-3 py-2 font-mono text-xs">{{ (string) ($opsEscalationChannelsResolved['sev2'] ?? __('missing')) }}</td>
                                    </tr>
                                    <tr>
                                        <td class="px-3 py-2">{{ __('Status updates') }}</td>
                                        <td class="px-3 py-2 font-mono text-xs">{{ (string) ($opsEscalationChannelsResolved['status_updates'] ?? __('missing')) }}</td>
                                    </tr>
                                    <tr>
                                        <td class="px-3 py-2">{{ __('Customer updates') }}</td>
                                        <td class="px-3 py-2 font-mono text-xs">{{ (string) ($opsEscalationTargetsResolved['customer_updates'] ?? __('missing')) }}</td>
                                    </tr>
                                    <tr>
                                        <td class="px-3 py-2">{{ __('Internal updates') }}</td>
                                        <td class="px-3 py-2 font-mono text-xs">{{ (string) ($opsEscalationTargetsResolved['internal_updates'] ?? __('missing')) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="space-y-2 text-xs">
                            <div class="flex items-center justify-between gap-3 rounded-lg border border-gray-200 px-3 py-2 dark:border-gray-700">
                                <span class="text-gray-700 dark:text-gray-300">{{ __('Contacts configured') }}</span>
                                <span class="{{ (bool) ($opsReadinessState['contacts_configured'] ?? false) ? 'app-chip-success' : 'app-chip-warning' }}">
                                    {{ (bool) ($opsReadinessState['contacts_configured'] ?? false) ? __('PASS') : __('MISSING') }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between gap-3 rounded-lg border border-gray-200 px-3 py-2 dark:border-gray-700">
                                <span class="text-gray-700 dark:text-gray-300">{{ __('Escalation channels configured') }}</span>
                                <span class="{{ (bool) ($opsReadinessState['channels_configured'] ?? false) ? 'app-chip-success' : 'app-chip-warning' }}">
                                    {{ (bool) ($opsReadinessState['channels_configured'] ?? false) ? __('PASS') : __('MISSING') }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between gap-3 rounded-lg border border-gray-200 px-3 py-2 dark:border-gray-700">
                                <span class="text-gray-700 dark:text-gray-300">{{ __('Escalation targets configured') }}</span>
                                <span class="{{ (bool) ($opsReadinessState['targets_configured'] ?? false) ? 'app-chip-success' : 'app-chip-warning' }}">
                                    {{ (bool) ($opsReadinessState['targets_configured'] ?? false) ? __('PASS') : __('MISSING') }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between gap-3 rounded-lg border border-gray-200 px-3 py-2 dark:border-gray-700">
                                <span class="text-gray-700 dark:text-gray-300">{{ __('Drill artifact present') }}</span>
                                <span class="{{ (bool) ($opsReadinessState['monthly_drill_ready'] ?? false) ? 'app-chip-success' : 'app-chip-warning' }}">
                                    {{ (bool) ($opsReadinessState['monthly_drill_ready'] ?? false) ? __('PASS') : __('MISSING') }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between gap-3 rounded-lg border border-gray-200 px-3 py-2 dark:border-gray-700">
                                <span class="text-gray-700 dark:text-gray-300">{{ __('Error budget policy file') }}</span>
                                <span class="{{ (bool) ($opsReadinessState['error_budget_policy_present'] ?? false) ? 'app-chip-success' : 'app-chip-warning' }}">
                                    {{ (bool) ($opsReadinessState['error_budget_policy_present'] ?? false) ? __('PASS') : __('MISSING') }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <section class="app-card">
                    <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Overall status') }}</div>
                    <div class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $overallStatus }}</div>
                </section>
                <section class="app-card">
                    <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Queue lag p95') }}</div>
                    <div class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">
                        {{ is_numeric($queueLagP95) ? number_format((float) $queueLagP95, 1).' s' : '-' }}
                    </div>
                </section>
                <section class="app-card">
                    <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Queue wait p95') }}</div>
                    <div class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">
                        {{ is_numeric($queueWaitP95) ? number_format((float) $queueWaitP95, 0).' ms' : '-' }}
                    </div>
                </section>
                <section class="app-card">
                    <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Probe gap (due no online)') }}</div>
                    <div class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $probeGap }}</div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        {{ __('Storage max usage') }}: {{ is_numeric($storageMaxUsage) ? number_format((float) $storageMaxUsage, 1).'%' : '-' }}
                    </div>
                </section>
            </div>

            <div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
                <section class="app-card app-card-lg">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('Drill outcomes (recent)') }}</h3>
                    <div class="mt-2 flex flex-wrap gap-2 text-xs">
                        <span class="app-chip-success">{{ __('PASS') }} {{ (int) ($artifactStatusCounts['pass'] ?? 0) }}</span>
                        <span class="app-chip-warning">{{ __('WARN') }} {{ (int) ($artifactStatusCounts['warn'] ?? 0) }}</span>
                        <span class="app-chip-danger">{{ __('FAIL') }} {{ (int) ($artifactStatusCounts['fail'] ?? 0) }}</span>
                        @if ((int) ($artifactStatusCounts['other'] ?? 0) > 0)
                            <span class="app-chip">{{ __('OTHER') }} {{ (int) ($artifactStatusCounts['other'] ?? 0) }}</span>
                        @endif
                    </div>
                    <x-analytics.bar-chart
                        :values="$artifactTrend['status_scores'] ?? []"
                        :labels="$artifactTrend['labels'] ?? []"
                        :drilldown-urls="$artifactTrend['drilldown_urls'] ?? []"
                        :has-data="$artifactHasData"
                        :max-value="3"
                        :series-name="__('Drill status score')"
                        :legend="__('PASS=1, WARN=2, FAIL=3')"
                        :unit="__('score')"
                        tone="amber"
                        height-class="h-36"
                        :label-step="3"
                        :empty-text="__('No drill artifacts yet.')"
                    />
                </section>

                <section class="app-card app-card-lg">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('Queue lag trend (drills)') }}</h3>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Click a bar to open the exact drill JSON artifact.') }}</p>
                    <x-analytics.bar-chart
                        :values="$artifactTrend['queue_lag_seconds'] ?? []"
                        :labels="$artifactTrend['labels'] ?? []"
                        :drilldown-urls="$artifactTrend['drilldown_urls'] ?? []"
                        :has-data="$artifactHasData"
                        :max-value="max(($artifactTrend['queue_lag_seconds'] ?? []) ?: [0])"
                        :series-name="__('Queue lag p95')"
                        :legend="__('queue_lag p95 seconds')"
                        :unit="__('s')"
                        tone="indigo"
                        height-class="h-36"
                        :label-step="3"
                        :empty-text="__('No drill artifacts yet.')"
                    />
                </section>

                <section class="app-card app-card-lg">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('Queue wait trend (drills)') }}</h3>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('From stored drill checks (`queue_wait_p95_ms`) in recent runs.') }}</p>
                    <x-analytics.bar-chart
                        :values="$artifactTrend['queue_wait_ms'] ?? []"
                        :labels="$artifactTrend['labels'] ?? []"
                        :drilldown-urls="$artifactTrend['drilldown_urls'] ?? []"
                        :has-data="$artifactHasData"
                        :max-value="max(($artifactTrend['queue_wait_ms'] ?? []) ?: [0])"
                        :series-name="__('Queue wait p95')"
                        :legend="__('queue_wait p95 ms')"
                        :unit="__('ms')"
                        tone="teal"
                        height-class="h-36"
                        :label-step="3"
                        :empty-text="__('No drill artifacts yet.')"
                    />
                </section>
            </div>

            <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
                <section class="app-card app-card-lg">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('Drill checks') }}</h3>
                    <div class="app-table mt-3 overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 text-gray-600 dark:bg-gray-900/70 dark:text-gray-300">
                                <tr>
                                    <th class="text-left px-3 py-2 font-medium">{{ __('Check') }}</th>
                                    <th class="text-left px-3 py-2 font-medium">{{ __('Status') }}</th>
                                    <th class="text-left px-3 py-2 font-medium">{{ __('Message') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($checks as $checkKey => $check)
                                    @php
                                        $status = strtolower((string) ($check['status'] ?? 'pass'));
                                        $chipClass = match ($status) {
                                            'fail' => 'app-chip-danger',
                                            'warn' => 'app-chip-warning',
                                            default => 'app-chip-success',
                                        };
                                    @endphp
                                    <tr>
                                        <td class="px-3 py-2 font-mono text-xs">{{ $checkKey }}</td>
                                        <td class="px-3 py-2"><span class="{{ $chipClass }}">{{ strtoupper($status) }}</span></td>
                                        <td class="px-3 py-2 text-xs text-gray-600 dark:text-gray-400">{{ (string) ($check['message'] ?? '') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4">
                        <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Recommendations') }}</div>
                        <ul class="mt-2 list-disc pl-5 text-sm text-gray-700 dark:text-gray-300">
                            @forelse ($recommendations as $recommendation)
                                <li>{{ $recommendation }}</li>
                            @empty
                                <li>{{ __('No recommendations for current drill state.') }}</li>
                            @endforelse
                        </ul>
                    </div>
                </section>

                <section class="app-card app-card-lg">
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('Tune plan') }}</h3>
                        <span class="app-chip">{{ __('Scale') }}: {{ $scaleAction !== '' ? strtoupper($scaleAction) : __('N/A') }}</span>
                    </div>

                    <div class="mt-2 text-xs text-gray-600 dark:text-gray-300">
                        {{ __('Suggested scale command') }}:
                        <span class="font-mono">{{ $scaleCommand !== '' ? $scaleCommand : __('n/a') }}</span>
                    </div>
                    <div class="mt-1 text-xs text-gray-600 dark:text-gray-300">
                        {{ __('Env target') }}: <span class="font-mono">{{ $opsTuneEnvFile }}</span>
                    </div>

                    <div class="app-table mt-3 overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 text-gray-600 dark:bg-gray-900/70 dark:text-gray-300">
                                <tr>
                                    <th class="text-left px-3 py-2 font-medium">{{ __('Key') }}</th>
                                    <th class="text-right px-3 py-2 font-medium">{{ __('Current') }}</th>
                                    <th class="text-right px-3 py-2 font-medium">{{ __('Suggested') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @forelse ($envOverrides as $envKey => $override)
                                    <tr>
                                        <td class="px-3 py-2 font-mono text-xs">{{ $envKey }}</td>
                                        <td class="px-3 py-2 text-right">{{ (int) ($override['current'] ?? 0) }}</td>
                                        <td class="px-3 py-2 text-right">{{ (int) ($override['suggested'] ?? 0) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="px-3 py-4 text-center text-gray-500 dark:text-gray-400">{{ __('No env overrides suggested.') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <form method="POST" action="{{ route('ops-center.tune.apply') }}" class="mt-3 flex items-center gap-2">
                        @csrf
                        @if ($selectedWindowHours !== null)
                            <input type="hidden" name="ops_window_hours" value="{{ $selectedWindowHours }}" />
                        @endif
                        @if ($selectedCurrentWorkers !== null)
                            <input type="hidden" name="ops_current_workers" value="{{ $selectedCurrentWorkers }}" />
                        @endif
                        <button type="submit" class="app-button-primary" @disabled($envOverrides === [])>{{ __('Apply overrides') }}</button>
                    </form>
                </section>
            </div>

            <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
                <section class="app-card app-card-lg">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('Drill artifacts') }}</h3>
                    <div class="app-table mt-3 overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 text-gray-600 dark:bg-gray-900/70 dark:text-gray-300">
                                <tr>
                                    <th class="text-left px-3 py-2 font-medium">{{ __('File') }}</th>
                                    <th class="text-left px-3 py-2 font-medium">{{ __('Modified') }}</th>
                                    <th class="text-right px-3 py-2 font-medium">{{ __('Size') }}</th>
                                    <th class="text-right px-3 py-2 font-medium">{{ __('View') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @forelse ($artifactRows as $artifact)
                                    @php
                                        $artifactDate = \Illuminate\Support\Carbon::parse((string) ($artifact['modified_at_iso'] ?? now()->toISOString()))->toDateTimeString();
                                    @endphp
                                    <tr>
                                        <td class="px-3 py-2 font-mono text-xs">{{ (string) ($artifact['filename'] ?? '') }}</td>
                                        <td class="px-3 py-2 text-xs text-gray-600 dark:text-gray-400">{{ $artifactDate }}</td>
                                        <td class="px-3 py-2 text-right text-xs">{{ number_format(((int) ($artifact['size_bytes'] ?? 0)) / 1024, 2) }} KB</td>
                                        <td class="px-3 py-2 text-right">
                                            <a href="{{ route('ops-center.drills.show', ['filename' => (string) ($artifact['filename'] ?? '')]) }}" target="_blank" rel="noopener" class="app-link text-xs underline">{{ __('JSON') }}</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-3 py-4 text-center text-gray-500 dark:text-gray-400">{{ __('No drill artifacts yet.') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="app-card app-card-lg">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('Env backup history') }}</h3>
                    <div class="app-table mt-3 overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 text-gray-600 dark:bg-gray-900/70 dark:text-gray-300">
                                <tr>
                                    <th class="text-left px-3 py-2 font-medium">{{ __('Backup') }}</th>
                                    <th class="text-left px-3 py-2 font-medium">{{ __('Modified') }}</th>
                                    <th class="text-right px-3 py-2 font-medium">{{ __('Size') }}</th>
                                    <th class="text-right px-3 py-2 font-medium">{{ __('Rollback') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @forelse ($backupRows as $backup)
                                    @php
                                        $backupDate = \Illuminate\Support\Carbon::parse((string) ($backup['modified_at_iso'] ?? now()->toISOString()))->toDateTimeString();
                                        $backupId = (string) ($backup['id'] ?? '');
                                    @endphp
                                    <tr>
                                        <td class="px-3 py-2 font-mono text-xs">{{ $backupId }}</td>
                                        <td class="px-3 py-2 text-xs text-gray-600 dark:text-gray-400">{{ $backupDate }}</td>
                                        <td class="px-3 py-2 text-right text-xs">{{ number_format(((int) ($backup['size_bytes'] ?? 0)) / 1024, 2) }} KB</td>
                                        <td class="px-3 py-2 text-right">
                                            <form method="POST" action="{{ route('ops-center.tune.rollback') }}">
                                                @csrf
                                                <input type="hidden" name="backup_id" value="{{ $backupId }}" />
                                                @if ($selectedWindowHours !== null)
                                                    <input type="hidden" name="ops_window_hours" value="{{ $selectedWindowHours }}" />
                                                @endif
                                                @if ($selectedCurrentWorkers !== null)
                                                    <input type="hidden" name="ops_current_workers" value="{{ $selectedCurrentWorkers }}" />
                                                @endif
                                                <button type="submit" class="app-button-secondary text-xs" onclick="return confirm('{{ __('Rollback env file from selected backup?') }}')">{{ __('Rollback') }}</button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-3 py-4 text-center text-gray-500 dark:text-gray-400">{{ __('No env backups yet.') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div>
    </div>
</x-app-layout>
