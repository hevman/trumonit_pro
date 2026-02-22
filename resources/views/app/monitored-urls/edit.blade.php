<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl leading-tight text-gray-900 dark:text-gray-100">
                {{ __('messages.actions.edit') }}: {{ $monitoredUrl->display_name ?? $monitoredUrl->url }}
            </h2>
            <a href="{{ route('monitored-urls.show', $monitoredUrl) }}" class="app-link">
                {{ __('messages.actions.cancel') }}
            </a>
        </div>
    </x-slot>

    <div class="app-page">
        <div class="app-container-3xl">
            <div class="app-card app-card-lg">
                @php($contentRulesJson = $monitoredUrl->content_rules ? json_encode($monitoredUrl->content_rules, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '')
                @php($journeyStepsJson = $monitoredUrl->journey_steps ? json_encode($monitoredUrl->journey_steps, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '')
                @php($tagsString = $monitoredUrl->tags?->pluck('name')->sort()->implode(', ') ?? '')

                <form method="POST" action="{{ route('monitored-urls.update', $monitoredUrl) }}" class="space-y-4" x-data="{ advancedOpen: false }">
                    @csrf
                    @method('PUT')

                    <div>
                        <div class="text-sm text-gray-600 dark:text-gray-400 mb-1">{{ __('messages.monitored_urls.url') }}</div>
                        <div class="font-medium break-all">{{ $monitoredUrl->url }}</div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-1" for="display_name">{{ __('messages.monitored_urls.display_name') }}</label>
                        <input id="display_name" name="display_name" type="text" value="{{ old('display_name', $monitoredUrl->display_name) }}" class="app-input" />
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-1" for="tags">{{ __('messages.monitored_urls.tags') }}</label>
                        <input id="tags" name="tags" type="text" value="{{ old('tags', $tagsString) }}" class="app-input" placeholder="prod, team:payments" />
                        <div class="app-help">{{ __('Format') }}: <span class="font-mono">a-z0-9:_-</span>, {{ __('max 20') }}.</div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-1" for="scan_mode">{{ __('messages.monitored_urls.scan_mode_label') }}</label>
                            <select id="scan_mode" name="scan_mode" class="app-input">
                                <option value="http" {{ old('scan_mode', $monitoredUrl->scan_mode) === 'http' ? 'selected' : '' }}>{{ __('messages.monitored_urls.scan_mode_http') }}</option>
                                <option value="journey" {{ old('scan_mode', $monitoredUrl->scan_mode) === 'journey' ? 'selected' : '' }}>{{ __('messages.monitored_urls.scan_mode_journey') }}</option>
                            </select>
                            <div class="app-help">{{ __('messages.monitored_urls.scan_mode_switch_warning') }}</div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1" for="frequency_minutes">{{ __('messages.monitored_urls.frequency_minutes') }}</label>
                            <input id="frequency_minutes" name="frequency_minutes" type="number" value="{{ old('frequency_minutes', $monitoredUrl->frequency_minutes) }}" class="app-input" />
                        </div>
                    </div>

                    <div class="flex items-center gap-4">
                        <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                            <input type="checkbox" name="is_active" value="1" class="app-checkbox" {{ old('is_active', $monitoredUrl->is_active) ? 'checked' : '' }} />
                            {{ __('messages.monitored_urls.is_active') }}
                        </label>
                    </div>

                    <div class="pt-1">
                        <button type="button" class="app-button-secondary" @click="advancedOpen = !advancedOpen">
                            <span x-show="!advancedOpen">{{ __('messages.actions.show_advanced') }}</span>
                            <span x-show="advancedOpen" x-cloak>{{ __('messages.actions.hide_advanced') }}</span>
                        </button>
                    </div>

                    <div x-show="advancedOpen" x-cloak class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium mb-1" for="timeout_seconds">{{ __('messages.monitored_urls.timeout_seconds') }}</label>
                                <input id="timeout_seconds" name="timeout_seconds" type="number" value="{{ old('timeout_seconds', $monitoredUrl->timeout_seconds) }}" class="app-input" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1" for="appearance_min_distance">{{ __('Appearance threshold (pHash distance)') }}</label>
                                <input id="appearance_min_distance" name="appearance_min_distance" type="number" min="0" max="64" value="{{ old('appearance_min_distance', $monitoredUrl->appearance_min_distance) }}" class="app-input" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium mb-1" for="expected_status_min">{{ __('Expected status min') }}</label>
                                <input id="expected_status_min" name="expected_status_min" type="number" value="{{ old('expected_status_min', $monitoredUrl->expected_status_min) }}" class="app-input" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1" for="expected_status_max">{{ __('Expected status max') }}</label>
                                <input id="expected_status_max" name="expected_status_max" type="number" value="{{ old('expected_status_max', $monitoredUrl->expected_status_max) }}" class="app-input" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium mb-1" for="alert_after_failures">{{ __('messages.monitored_urls.alert_after_failures') }}</label>
                                <input id="alert_after_failures" name="alert_after_failures" type="number" min="1" max="10" value="{{ old('alert_after_failures', $monitoredUrl->alert_after_failures ?? 1) }}" class="app-input" />
                                <div class="app-help">{{ __('Create DOWN incidents only after N consecutive failures.') }}</div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1" for="alert_cooldown_minutes">{{ __('messages.monitored_urls.alert_cooldown_minutes') }}</label>
                                <input id="alert_cooldown_minutes" name="alert_cooldown_minutes" type="number" min="0" max="10080" value="{{ old('alert_cooldown_minutes', $monitoredUrl->alert_cooldown_minutes ?? 0) }}" class="app-input" />
                                <div class="app-help">{{ __('0 = no cooldown.') }}</div>
                            </div>
                        </div>

                        <div class="flex items-center gap-4">
                            <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                <input type="checkbox" name="follow_redirects" value="1" class="app-checkbox" {{ old('follow_redirects', $monitoredUrl->follow_redirects) ? 'checked' : '' }} />
                                {{ __('Follow redirects') }}
                            </label>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-1" for="content_rules">{{ __('Content rules (JSON)') }}</label>
                            <textarea id="content_rules" name="content_rules" rows="6" class="app-input font-mono text-xs">{{ old('content_rules', $contentRulesJson) }}</textarea>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-1" for="journey_steps">{{ __('messages.monitored_urls.journey_steps_label') }}</label>
                            <textarea id="journey_steps" name="journey_steps" rows="8" class="app-input font-mono text-xs">{{ old('journey_steps', $journeyStepsJson) }}</textarea>
                            <div class="app-help">{{ __('messages.monitored_urls.journey_steps_help') }}</div>
                        </div>
                    </div>

                    <div class="pt-2">
                        <button type="submit" class="app-button-primary">
                            {{ __('messages.actions.update') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>

