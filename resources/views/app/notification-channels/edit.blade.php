<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Notification channels') }}
            </h2>
            <a href="{{ route('dashboard') }}" class="app-link">
                {{ __('messages.nav.dashboard') }}
            </a>
        </div>
    </x-slot>

    <div class="app-page">
        <div class="app-container">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
                <section class="app-card">
                    <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Configured channels') }}</div>
                    <div class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $configuredChannelsCount }}</div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Out of 5 available') }}</div>
                </section>

                @foreach ($channelSummary as $channelRow)
                    <section class="app-card">
                        <div class="flex items-center justify-between gap-2 text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <span>{{ $channelRow['label'] }}</span>
                            @if (($channelRow['key'] ?? '') === 'webhook')
                                @php($isHumanMode = ($channelRow['payload_mode_key'] ?? '') === 'human_machine')
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-medium {{ $isHumanMode ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-200' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200' }}">
                                    {{ $isHumanMode ? __('messages.notifications.payload_mode_human_machine') : __('messages.notifications.payload_mode_machine_only') }}
                                </span>
                            @endif
                        </div>
                        <div class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $channelRow['count'] }}</div>
                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Min severity') }}: {{ $channelRow['min_severity'] }}</div>
                        @if (($channelRow['key'] ?? '') === 'webhook')
                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                {{ __('messages.notifications.payload_mode') }}:
                                {{ $isHumanMode ? __('messages.notifications.payload_mode_human_machine') : __('messages.notifications.payload_mode_machine_only') }}
                            </div>
                        @endif
                    </section>
                @endforeach
            </div>

            <div class="app-card">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('Routing overview') }}</h3>
                <div class="mt-4 space-y-3">
                    @foreach ($channelSummary as $channelRow)
                        @php($width = (int) round(((int) $channelRow['count'] / max(1, $maxChannelEndpoints)) * 100))
                        <div class="space-y-1">
                            <div class="flex items-center justify-between gap-2 text-xs">
                                <span class="text-gray-700 dark:text-gray-200">{{ $channelRow['label'] }}</span>
                                <span class="text-gray-500 dark:text-gray-400">{{ $channelRow['count'] }} {{ __('endpoints') }}</span>
                            </div>
                            <div class="h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                                <div class="h-full rounded-full bg-indigo-500" style="width: {{ min(100, max(4, $width)) }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="app-card">
                <div class="text-sm text-gray-700 dark:text-gray-300">
                    {{ __('Configure tenant-scoped alert channels. One URL/email per line (or comma separated).') }}
                </div>
                <div class="app-help">
                    {{ __('Routing rule: send alert to channel only when incident severity is greater than or equal to channel minimum severity.') }}
                </div>

                <form method="POST" action="{{ route('notification-channels.update') }}" class="mt-4 space-y-4">
                    @csrf
                    @method('PUT')

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                        <div>
                            <label class="app-label">{{ __('Generic webhook URLs') }}</label>
                            <textarea name="webhook_urls" rows="4" class="app-input">{{ old('webhook_urls', implode(PHP_EOL, $channels['webhook_urls'] ?? [])) }}</textarea>
                            <div class="mt-3 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 dark:border-gray-700 dark:bg-gray-900/40">
                                <input type="hidden" name="webhook_human_readable_enabled" value="0" />
                                <label class="flex items-start gap-2 text-sm text-gray-700 dark:text-gray-200">
                                    <input
                                        type="checkbox"
                                        name="webhook_human_readable_enabled"
                                        value="1"
                                        class="mt-1 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800"
                                        @checked((bool) old('webhook_human_readable_enabled', $channels['webhook_human_readable_enabled'] ?? false))
                                    />
                                    <span>
                                        <span class="font-medium">{{ __('messages.notifications.webhook_human_readable_label') }}</span>
                                        <span class="block text-xs text-gray-500 dark:text-gray-400">{{ __('messages.notifications.webhook_human_readable_help') }}</span>
                                    </span>
                                </label>
                            </div>
                        </div>

                        <div>
                            <label class="app-label">{{ __('Slack webhook URLs') }}</label>
                            <textarea name="slack_webhook_urls" rows="4" class="app-input">{{ old('slack_webhook_urls', implode(PHP_EOL, $channels['slack_webhook_urls'] ?? [])) }}</textarea>
                        </div>

                        <div>
                            <label class="app-label">{{ __('Teams webhook URLs') }}</label>
                            <textarea name="teams_webhook_urls" rows="4" class="app-input">{{ old('teams_webhook_urls', implode(PHP_EOL, $channels['teams_webhook_urls'] ?? [])) }}</textarea>
                        </div>

                        <div>
                            <label class="app-label">{{ __('Discord webhook URLs') }}</label>
                            <textarea name="discord_webhook_urls" rows="4" class="app-input">{{ old('discord_webhook_urls', implode(PHP_EOL, $channels['discord_webhook_urls'] ?? [])) }}</textarea>
                        </div>

                        <div class="lg:col-span-2">
                            <label class="app-label">{{ __('Email recipients') }}</label>
                            <textarea name="email_recipients" rows="3" class="app-input">{{ old('email_recipients', implode(PHP_EOL, $channels['email_recipients'] ?? [])) }}</textarea>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
                        <div>
                            <label class="app-label">{{ __('Webhook min severity') }}</label>
                            <input type="number" min="1" max="100" name="webhook_min_severity" value="{{ old('webhook_min_severity', $channels['routing']['webhook_min_severity'] ?? 1) }}" class="app-input" />
                        </div>
                        <div>
                            <label class="app-label">{{ __('Slack min severity') }}</label>
                            <input type="number" min="1" max="100" name="slack_min_severity" value="{{ old('slack_min_severity', $channels['routing']['slack_min_severity'] ?? 1) }}" class="app-input" />
                        </div>
                        <div>
                            <label class="app-label">{{ __('Teams min severity') }}</label>
                            <input type="number" min="1" max="100" name="teams_min_severity" value="{{ old('teams_min_severity', $channels['routing']['teams_min_severity'] ?? 1) }}" class="app-input" />
                        </div>
                        <div>
                            <label class="app-label">{{ __('Discord min severity') }}</label>
                            <input type="number" min="1" max="100" name="discord_min_severity" value="{{ old('discord_min_severity', $channels['routing']['discord_min_severity'] ?? 1) }}" class="app-input" />
                        </div>
                        <div>
                            <label class="app-label">{{ __('Email min severity') }}</label>
                            <input type="number" min="1" max="100" name="email_min_severity" value="{{ old('email_min_severity', $channels['routing']['email_min_severity'] ?? 1) }}" class="app-input" />
                        </div>
                    </div>

                    <div>
                        <button class="app-button-primary" type="submit">
                            {{ __('Save') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>

