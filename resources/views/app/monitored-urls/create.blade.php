<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl leading-tight text-gray-900 dark:text-gray-100">
                {{ __('messages.monitored_urls.new') }}
            </h2>
            <a href="{{ route('monitored-urls.index') }}" class="app-link">
                {{ __('messages.actions.cancel') }}
            </a>
        </div>
    </x-slot>

    <div class="app-page">
        <div class="app-container-3xl">
            <div class="app-card app-card-lg">
                @if (isset($quota) && is_array($quota))
                    <div class="mb-4 rounded border border-gray-200 bg-gray-50 p-3 text-xs text-gray-700 dark:border-gray-700 dark:bg-gray-900/70 dark:text-gray-300">
                        <span class="font-semibold">{{ __('Plan') }}:</span> {{ strtoupper((string) ($quota['tier'] ?? '')) }}
                        @if (($quota['max_monitored_urls'] ?? 0) > 0)
                            <span class="mx-2">|</span>
                            <span class="font-semibold">{{ __('Monitors') }}:</span> {{ (int) ($quota['current_monitored_urls'] ?? 0) }}/{{ (int) ($quota['max_monitored_urls'] ?? 0) }}
                        @endif
                        <div class="mt-1">{{ __('If you hit limits, upgrade plan tier to increase monitor/storage/RPM quotas.') }}</div>
                    </div>
                @endif

                <form method="POST" action="{{ route('monitored-urls.store') }}" class="space-y-4" x-data="{ advancedOpen: {{ (old('content_rules') || old('journey_steps')) ? 'true' : 'false' }} }">
                    @csrf

                    <div>
                        <label class="block text-sm font-medium mb-1" for="template">{{ __('Template') }}</label>
                        <div class="flex flex-col sm:flex-row gap-2">
                            <select id="template" name="template" class="app-input">
                                <option value="">{{ __('Custom') }}</option>
                                @foreach ($monitorTemplates as $template)
                                    <option value="{{ $template['key'] }}" {{ old('template') === $template['key'] ? 'selected' : '' }}>
                                        {{ $template['name'] }}
                                    </option>
                                @endforeach
                            </select>
                            <button id="apply-template" type="button" class="app-button-secondary">
                                {{ __('Apply defaults') }}
                            </button>
                        </div>
                        <div id="template-description" class="app-help">{{ __('Pick a template and apply defaults, then adjust fields if needed.') }}</div>
                        <div class="app-help">{{ __('messages.onboarding.template_pick_help') }}</div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-1" for="url">{{ __('messages.monitored_urls.url') }}</label>
                        <input id="url" name="url" type="url" value="{{ old('url') }}" required class="app-input" placeholder="{{ __('messages.onboarding.url_placeholder') }}" />
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-1" for="display_name">{{ __('messages.monitored_urls.display_name') }}</label>
                        <input id="display_name" name="display_name" type="text" value="{{ old('display_name') }}" class="app-input" placeholder="{{ __('messages.onboarding.display_name_placeholder') }}" />
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-1" for="tags">{{ __('messages.monitored_urls.tags') }}</label>
                        <input id="tags" name="tags" type="text" value="{{ old('tags') }}" class="app-input" placeholder="{{ __('messages.onboarding.tags_placeholder') }}" />
                        <div class="app-help">{{ __('Format') }}: <span class="font-mono">a-z0-9:_-</span>, {{ __('max 20') }}.</div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-1" for="scan_mode">{{ __('messages.monitored_urls.scan_mode_label') }}</label>
                            <select id="scan_mode" name="scan_mode" class="app-input">
                                <option value="http" {{ old('scan_mode', 'http') === 'http' ? 'selected' : '' }}>{{ __('messages.monitored_urls.scan_mode_http') }}</option>
                                <option value="journey" {{ old('scan_mode') === 'journey' ? 'selected' : '' }}>{{ __('messages.monitored_urls.scan_mode_journey') }}</option>
                            </select>
                            <div class="app-help">{{ __('messages.monitored_urls.scan_mode_help') }}</div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1" for="frequency_minutes">{{ __('messages.monitored_urls.frequency_minutes') }}</label>
                            <input id="frequency_minutes" name="frequency_minutes" type="number" value="{{ old('frequency_minutes', 1440) }}" class="app-input" />
                        </div>
                    </div>

                    <div class="flex items-center gap-4">
                        <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                            <input type="checkbox" name="is_active" value="1" class="app-checkbox" {{ old('is_active', 1) ? 'checked' : '' }} />
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
                                <input id="timeout_seconds" name="timeout_seconds" type="number" value="{{ old('timeout_seconds', 30) }}" class="app-input" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1" for="appearance_min_distance">{{ __('Appearance threshold (pHash distance)') }}</label>
                                <input id="appearance_min_distance" name="appearance_min_distance" type="number" min="0" max="64" value="{{ old('appearance_min_distance') }}" class="app-input" />
                                <div class="app-help">{{ __('Optional. Higher value means fewer alerts for small UI changes.') }}</div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium mb-1" for="expected_status_min">{{ __('Expected status min') }}</label>
                                <input id="expected_status_min" name="expected_status_min" type="number" value="{{ old('expected_status_min', 200) }}" class="app-input" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1" for="expected_status_max">{{ __('Expected status max') }}</label>
                                <input id="expected_status_max" name="expected_status_max" type="number" value="{{ old('expected_status_max', 399) }}" class="app-input" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium mb-1" for="alert_after_failures">{{ __('messages.monitored_urls.alert_after_failures') }}</label>
                                <input id="alert_after_failures" name="alert_after_failures" type="number" min="1" max="10" value="{{ old('alert_after_failures', 1) }}" class="app-input" />
                                <div class="app-help">{{ __('Create DOWN incidents only after N consecutive failures.') }}</div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1" for="alert_cooldown_minutes">{{ __('messages.monitored_urls.alert_cooldown_minutes') }}</label>
                                <input id="alert_cooldown_minutes" name="alert_cooldown_minutes" type="number" min="0" max="10080" value="{{ old('alert_cooldown_minutes', 0) }}" class="app-input" />
                                <div class="app-help">{{ __('0 = no cooldown.') }}</div>
                            </div>
                        </div>

                        <div class="flex items-center gap-4">
                            <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                <input type="checkbox" name="follow_redirects" value="1" class="app-checkbox" {{ old('follow_redirects', 1) ? 'checked' : '' }} />
                                {{ __('Follow redirects') }}
                            </label>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-1" for="content_rules">{{ __('Content rules (JSON)') }}</label>
                            <textarea id="content_rules" name="content_rules" rows="6" class="app-input font-mono text-xs">{{ old('content_rules') }}</textarea>
                            <div class="app-help">{{ __('Example') }}: {"enabled":true,"deny_keywords":["hacked by"],"deny_regexes":["/SQLSTATE\\[/i"]}</div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-1" for="journey_steps">{{ __('messages.monitored_urls.journey_steps_label') }}</label>
                            <textarea id="journey_steps" name="journey_steps" rows="8" class="app-input font-mono text-xs">{{ old('journey_steps') }}</textarea>
                            <div class="app-help">{{ __('messages.monitored_urls.journey_steps_help') }}</div>
                        </div>
                    </div>

                    <div class="pt-2">
                        <button type="submit" class="app-button-primary">
                            {{ __('messages.actions.save') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var templates = @json($monitorTemplates ?? []);
            var select = document.getElementById('template');
            var apply = document.getElementById('apply-template');
            var description = document.getElementById('template-description');

            if (!select || !apply || !description) {
                return;
            }

            var byKey = {};

            for (var i = 0; i < templates.length; i++) {
                var template = templates[i];
                if (template && typeof template.key === 'string') {
                    byKey[template.key] = template;
                }
            }

            function setInputValue(id, value) {
                var element = document.getElementById(id);
                if (!element) return;

                if (element.type === 'checkbox') {
                    element.checked = !!value;
                    return;
                }

                if (value === null || typeof value === 'undefined') {
                    element.value = '';
                    return;
                }

                element.value = String(value);
            }

            function setJsonValue(id, value) {
                var element = document.getElementById(id);
                if (!element) return;

                if (value === null || typeof value === 'undefined') {
                    element.value = '';
                    return;
                }

                if (typeof value === 'object') {
                    element.value = JSON.stringify(value, null, 2);
                    return;
                }

                element.value = String(value);
            }

            function updateDescription() {
                var selected = byKey[select.value];
                if (!selected) {
                    description.textContent = @json(__('Pick a template and apply defaults, then adjust fields if needed.'));
                    return;
                }

                description.textContent = selected.description || @json(__('Template loaded.'));
            }

            function applyTemplateDefaults() {
                var selected = byKey[select.value];
                if (!selected || typeof selected.defaults !== 'object' || selected.defaults === null) {
                    return;
                }

                var defaults = selected.defaults;

                setInputValue('scan_mode', defaults.scan_mode);
                setInputValue('frequency_minutes', defaults.frequency_minutes);
                setInputValue('timeout_seconds', defaults.timeout_seconds);
                setInputValue('follow_redirects', defaults.follow_redirects);
                setInputValue('expected_status_min', defaults.expected_status_min);
                setInputValue('expected_status_max', defaults.expected_status_max);
                setInputValue('appearance_min_distance', defaults.appearance_min_distance);
                setInputValue('alert_after_failures', defaults.alert_after_failures);
                setInputValue('alert_cooldown_minutes', defaults.alert_cooldown_minutes);
                setJsonValue('content_rules', defaults.content_rules);
                setJsonValue('journey_steps', defaults.journey_steps);
            }

            select.addEventListener('change', updateDescription);
            apply.addEventListener('click', applyTemplateDefaults);
            updateDescription();
        });
    </script>
</x-app-layout>

