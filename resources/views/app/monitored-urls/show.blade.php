<x-app-layout>
    <x-slot name="header">
        @php
            $isOwner = auth()->user()?->role !== 'member';
        @endphp
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl leading-tight text-gray-900 dark:text-gray-100">
                    {{ $monitoredUrl->display_name ?? $monitoredUrl->url }}
                </h2>
                <div class="text-sm text-gray-600 dark:text-gray-400 break-all">{{ $monitoredUrl->url }}</div>
                @if ($monitoredUrl->tags?->count())
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach ($monitoredUrl->tags->sortBy('name') as $tag)
                            <span class="app-chip">{{ $tag->name }}</span>
                        @endforeach
                    </div>
                @endif
            </div>
            @if ($isOwner)
                <div class="flex items-center gap-2">
                    <form method="POST" action="{{ route('monitored-urls.scan', $monitoredUrl) }}">
                        @csrf
                        <button class="app-button-primary" type="submit">
                            {{ __('messages.actions.scan_now') }}
                        </button>
                    </form>
                    <a href="{{ route('monitored-urls.edit', $monitoredUrl) }}" class="app-link">
                        {{ __('messages.actions.edit') }}
                    </a>
                </div>
            @endif
        </div>
    </x-slot>

    <div class="app-page">
        <div class="app-container">
            @if (session('monitor_created') || session('onboarding_created'))
                @php
                    $createdDispatched = (bool) session('first_check_dispatched', false);
                @endphp
                <div class="app-card app-card-success mb-4">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <div class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('messages.monitored_urls.created_title') }}</div>
                            @if ($latestSnapshot?->fetched_at)
                                <div class="text-sm text-gray-700 dark:text-gray-300">
                                    {{ __('messages.dashboard.last_check') }}: {{ $latestSnapshot->fetched_at->toDateTimeString() }}
                                </div>
                            @else
                                <div class="text-sm text-gray-700 dark:text-gray-300">
                                    {{ $createdDispatched ? __('messages.monitored_urls.created_body_queued') : __('messages.monitored_urls.created_body_manual') }}
                                </div>
                            @endif
                        </div>
                        @if ($isOwner)
                            <div class="flex flex-wrap gap-2">
                                @if (! $hasNotificationChannels)
                                    <a href="{{ route('notification-channels.edit') }}" class="app-button-primary">
                                        {{ __('Configure notifications') }}
                                    </a>
                                @endif
                                <form method="POST" action="{{ route('monitored-urls.scan', $monitoredUrl) }}">
                                    @csrf
                                    <button class="app-button-secondary" type="submit">{{ __('messages.actions.scan_now') }}</button>
                                </form>
                                <a href="{{ route('monitored-urls.index') }}" class="app-button-secondary">
                                    {{ __('messages.nav.monitored_urls') }}
                                </a>
                            </div>
                        @endif
                    </div>
                    @if ($isOwner && ! $hasNotificationChannels)
                        <div class="mt-3 text-xs text-gray-700 dark:text-gray-300">
                            {{ __('You will not receive alerts until you configure at least one notification channel (Email/Slack/Webhook).') }}
                        </div>
                    @endif

                    @if ($isOwner && $shouldShowBaselinePanel && ! $monitoredUrl->baselineSnapshot)
                        <div class="mt-3 text-xs text-gray-700 dark:text-gray-300">
                            {{ __('messages.monitored_urls.baseline_missing_body') }}
                        </div>
                    @endif
                </div>
            @endif

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="app-card">
                    <div class="text-sm text-gray-600 dark:text-gray-400">{{ __('Status') }}</div>
                    <div class="mt-1">
                        @if (! $monitoredUrl->is_active)
                            <span class="app-chip">{{ __('PAUSED') }}</span>
                        @elseif ($latestIsUp === null)
                            <span class="app-chip">{{ __('N/A') }}</span>
                        @elseif ($latestIsUp)
                            <span class="app-chip-success">{{ __('UP') }}</span>
                        @else
                            <span class="app-chip-danger">{{ __('DOWN') }}</span>
                        @endif
                    </div>

                    <div class="mt-2 text-xs text-gray-600 dark:text-gray-400 space-y-1">
                        @if ($latestSnapshot?->fetched_at)
                            <div>{{ __('checked') }}: {{ $latestSnapshot->fetched_at->toDateTimeString() }}</div>
                        @endif
                        <div>{{ __('http') }}: {{ $latestSnapshot?->http_status ?? '-' }}</div>
                        <div>{{ __('time') }}: {{ $latestSnapshot?->response_time_ms ?? '-' }} ms</div>
                        @if ($latestSnapshot?->error_message)
                            <div class="text-red-700 dark:text-red-300">{{ \Illuminate\Support\Str::limit($latestSnapshot->error_message, 120) }}</div>
                        @endif
                    </div>

                    @if ($isOwner)
                        @php
                            $isMuted = $monitoredUrl->muted_until && $monitoredUrl->muted_until->isFuture();
                        @endphp
                        <div class="mt-3 pt-3 border-t">
                            <div class="text-xs text-gray-600 dark:text-gray-400 mb-2">{{ __('Alerts') }}</div>

                            @if ($isMuted)
                                <div class="text-xs text-gray-700 dark:text-gray-300">{{ __('muted until') }} {{ $monitoredUrl->muted_until->toDateTimeString() }}</div>
                                <form method="POST" action="{{ route('monitored-urls.unmute', $monitoredUrl) }}" class="mt-2">
                                    @csrf
                                    <button class="text-xs font-medium text-gray-900 bg-gray-100 hover:bg-gray-200 dark:text-gray-100 dark:bg-gray-800 dark:hover:bg-gray-700 px-2 py-1 rounded" type="submit">
                                        {{ __('Unmute') }}
                                    </button>
                                </form>
                            @else
                                <div class="flex flex-wrap gap-2">
                                    <form method="POST" action="{{ route('monitored-urls.mute', $monitoredUrl) }}">
                                        @csrf
                                        <input type="hidden" name="minutes" value="60" />
                                        <button class="text-xs font-medium text-gray-900 bg-gray-100 hover:bg-gray-200 dark:text-gray-100 dark:bg-gray-800 dark:hover:bg-gray-700 px-2 py-1 rounded" type="submit">{{ __('Mute 1h') }}</button>
                                    </form>
                                    <form method="POST" action="{{ route('monitored-urls.mute', $monitoredUrl) }}">
                                        @csrf
                                        <input type="hidden" name="minutes" value="240" />
                                        <button class="text-xs font-medium text-gray-900 bg-gray-100 hover:bg-gray-200 dark:text-gray-100 dark:bg-gray-800 dark:hover:bg-gray-700 px-2 py-1 rounded" type="submit">{{ __('Mute 4h') }}</button>
                                    </form>
                                    <form method="POST" action="{{ route('monitored-urls.mute', $monitoredUrl) }}">
                                        @csrf
                                        <input type="hidden" name="minutes" value="1440" />
                                        <button class="text-xs font-medium text-gray-900 bg-gray-100 hover:bg-gray-200 dark:text-gray-100 dark:bg-gray-800 dark:hover:bg-gray-700 px-2 py-1 rounded" type="submit">{{ __('Mute 24h') }}</button>
                                    </form>
                                </div>
                            @endif

                            <div class="mt-2 text-xs text-gray-600 dark:text-gray-400 space-y-1">
                                <div>{{ __('messages.monitored_urls.alert_after_failures') }}: {{ $monitoredUrl->alert_after_failures ?? 1 }}</div>
                                <div>{{ __('messages.monitored_urls.alert_cooldown_minutes') }}: {{ $monitoredUrl->alert_cooldown_minutes ?? 0 }}</div>
                                <div>{{ __('messages.monitored_urls.consecutive_failures') }}: {{ $monitoredUrl->consecutive_failures ?? 0 }}</div>
                            </div>
                        </div>
                    @endif
                </div>

                <div class="app-card">
                    <div class="text-sm text-gray-600 dark:text-gray-400">{{ __('Uptime (24h)') }}</div>
                    <div class="text-2xl font-semibold">
                        {{ $stats24h['uptime_percent'] !== null ? $stats24h['uptime_percent'].'%' : '-' }}
                    </div>
                    <div class="text-xs text-gray-600 dark:text-gray-400">{{ $stats24h['up'] }}/{{ $stats24h['total'] }} {{ __('checks') }}</div>
                </div>

                <div class="app-card">
                    <div class="text-sm text-gray-600 dark:text-gray-400">{{ __('Uptime (7d)') }}</div>
                    <div class="text-2xl font-semibold">
                        {{ $stats7d['uptime_percent'] !== null ? $stats7d['uptime_percent'].'%' : '-' }}
                    </div>
                    <div class="text-xs text-gray-600 dark:text-gray-400">{{ $stats7d['up'] }}/{{ $stats7d['total'] }} {{ __('checks') }}</div>
                </div>

                <div class="app-card">
                    <div class="text-sm text-gray-600 dark:text-gray-400">{{ __('Avg response (24h)') }}</div>
                    <div class="text-2xl font-semibold">{{ $stats24h['avg_response_time_ms'] ?? '-' }}</div>
                    <div class="text-xs text-gray-600 dark:text-gray-400">{{ __('ms (no-error snapshots)') }}</div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="app-card">
                    <div class="text-sm text-gray-600 dark:text-gray-400">{{ __('messages.monitored_urls.is_active') }}</div>
                    <div class="font-medium">{{ $monitoredUrl->is_active ? __('yes') : __('no') }}</div>
                </div>
                <div class="app-card">
                    <div class="text-sm text-gray-600 dark:text-gray-400">{{ __('messages.monitored_urls.frequency_minutes') }}</div>
                    <div class="font-medium">{{ $monitoredUrl->frequency_minutes }}</div>
                </div>
                <div class="app-card">
                    <div class="text-sm text-gray-600 dark:text-gray-400">{{ __('Scan mode') }}</div>
                    @php
                        $scanMode = (string) ($monitoredUrl->scan_mode ?? 'http');
                        $scanModeLabel = match ($scanMode) {
                            'journey' => __('messages.monitored_urls.mode_journey'),
                            'port' => __('messages.monitored_urls.mode_port'),
                            'heartbeat' => __('messages.monitored_urls.mode_heartbeat'),
                            default => __('messages.monitored_urls.mode_http'),
                        };
                    @endphp
                    <div class="font-medium font-mono">{{ $scanModeLabel }}</div>
                    @if ($monitoredUrl->appearance_min_distance !== null)
                        <div class="app-help">{{ __('appearance_min_distance') }}={{ $monitoredUrl->appearance_min_distance }}</div>
                    @endif
                </div>
                <div class="app-card">
                    <div class="text-sm text-gray-600 dark:text-gray-400">{{ __('messages.monitored_urls.last_scanned_at') }}</div>
                    <div class="font-medium">{{ $monitoredUrl->last_scanned_at?->toDateTimeString() ?? '-' }}</div>
                </div>
            </div>

            @if ($shouldShowBaselinePanel)
                <div class="app-card">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">{{ __('messages.monitored_urls.baseline_label') }}</div>
                            <div class="font-medium">
                                @if ($monitoredUrl->baselineSnapshot)
                                    <a class="hover:underline" href="{{ route('snapshots.show', $monitoredUrl->baselineSnapshot) }}">
                                        {{ __('Snapshot') }} #{{ $monitoredUrl->baselineSnapshot->id }}
                                    </a>
                                    <div class="text-xs text-gray-600 dark:text-gray-400">{{ $monitoredUrl->baseline_set_at?->toDateTimeString() ?? '' }}</div>
                                @else
                                    <div class="text-sm text-gray-700 dark:text-gray-300">{{ __('messages.monitored_urls.baseline_missing_title') }}</div>
                                    <div class="app-help">{{ __('messages.monitored_urls.baseline_missing_body') }}</div>
                                @endif
                            </div>
                        </div>

                        @if ($isOwner && $snapshots->first())
                            <form method="POST" action="{{ route('monitored-urls.baseline', $monitoredUrl) }}">
                                @csrf
                                <input type="hidden" name="snapshot_id" value="{{ $snapshots->first()->id }}">
                                <button class="app-button-primary" type="submit">
                                    {{ $monitoredUrl->baselineSnapshot ? __('messages.monitored_urls.baseline_update_button') : __('messages.monitored_urls.baseline_set_button') }}
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            @endif

            @if (($monitoredUrl->scan_mode ?? 'http') === 'heartbeat' && is_string($heartbeatCheckInUrl ?? null) && $heartbeatCheckInUrl !== '')
                <div class="app-card">
                    <div class="text-sm text-gray-600 dark:text-gray-400">{{ __('messages.monitored_urls.heartbeat_check_in_url') }}</div>
                    <div class="mt-1 text-sm break-all text-gray-900 dark:text-gray-100">{{ $heartbeatCheckInUrl }}</div>
                    <div class="mt-2 text-xs text-gray-600 dark:text-gray-400">{{ __('messages.monitored_urls.heartbeat_check_in_help') }}</div>
                    <div class="mt-2 rounded bg-gray-100 px-3 py-2 text-xs font-mono text-gray-800 dark:bg-gray-900 dark:text-gray-100 break-all">curl -fsS "{{ $heartbeatCheckInUrl }}"</div>
                </div>
            @endif

            <div class="app-card">
                <div class="px-4 py-3 border-b font-medium">{{ __('messages.monitored_urls.snapshots') }}</div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-gray-600 dark:bg-gray-900/70 dark:text-gray-300">
                            <tr>
                                <th class="text-left px-4 py-2">{{ __('Fetched') }}</th>
                                <th class="text-left px-4 py-2">{{ __('HTTP') }}</th>
                                <th class="text-left px-4 py-2">{{ __('Time (ms)') }}</th>
                                <th class="text-left px-4 py-2">{{ __('SHA') }}</th>
                                <th class="text-left px-4 py-2">{{ __('Error') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($snapshots as $snapshot)
                                <tr class="border-t">
                                    <td class="px-4 py-2">
                                        <a class="text-gray-900 hover:underline" href="{{ route('snapshots.show', $snapshot) }}">
                                            {{ $snapshot->fetched_at?->toDateTimeString() }}
                                        </a>
                                    </td>
                                    <td class="px-4 py-2">{{ $snapshot->http_status ?? '-' }}</td>
                                    <td class="px-4 py-2">{{ $snapshot->response_time_ms ?? '-' }}</td>
                                    <td class="px-4 py-2">
                                        <span class="font-mono text-xs">{{ $snapshot->body_sha256 ? substr($snapshot->body_sha256, 0, 12).'...' : '-' }}</span>
                                    </td>
                                    <td class="px-4 py-2 text-red-700 dark:text-red-300">{{ $snapshot->error_message ?? '' }}</td>
                                </tr>
                            @empty
                                <tr class="border-t">
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-400" colspan="5">{{ __('No snapshots yet.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="app-card">
                <div class="px-4 py-3 border-b font-medium">{{ __('messages.nav.incidents') }}</div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-gray-600 dark:bg-gray-900/70 dark:text-gray-300">
                            <tr>
                                <th class="text-left px-4 py-2">{{ __('messages.incidents.detected_at') }}</th>
                                <th class="text-left px-4 py-2">{{ __('messages.incidents.severity') }}</th>
                                <th class="text-left px-4 py-2">{{ __('messages.incidents.kind') }}</th>
                                <th class="text-left px-4 py-2">{{ __('messages.incidents.summary') }}</th>
                                <th class="text-left px-4 py-2">{{ __('messages.incidents.status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($incidents as $incident)
                                <tr class="border-t">
                                    <td class="px-4 py-2">{{ $incident->detected_at?->toDateTimeString() }}</td>
                                    <td class="px-4 py-2">{{ $incident->severity }}</td>
                                    <td class="px-4 py-2">{{ $incident->kind }}</td>
                                    <td class="px-4 py-2">
                                        <a class="text-gray-900 hover:underline" href="{{ route('incidents.show', $incident) }}">
                                            {{ $incident->summary }}
                                        </a>
                                    </td>
                                    <td class="px-4 py-2">{{ $incident->status }}</td>
                                </tr>
                            @empty
                                <tr class="border-t">
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-400" colspan="5">{{ __('No incidents yet.') }}</td>
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

