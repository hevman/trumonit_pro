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

                    @php
                        $templateByKey = collect($monitorTemplates)->keyBy('key');
                        $featuredTemplateKeys = ['website_uptime', 'api_health', 'login_journey', 'cron_heartbeat'];
                        $featuredTemplates = collect($featuredTemplateKeys)
                            ->map(fn (string $key) => $templateByKey->get($key))
                            ->filter()
                            ->values()
                            ->all();
                        $isPolishLocale = app()->getLocale() === 'pl';
                        $templateGuide = [
                            'website_uptime' => [
                                'for' => $isPolishLocale ? 'Dla stron firmowych i landingów. Sprawdza dostępność URL i status HTTP.' : 'For websites and landing pages. Checks URL availability and expected HTTP status.',
                                'alert' => $isPolishLocale ? 'Alarmuje przy niedostępności, złym kodzie odpowiedzi lub timeout.' : 'Alerts on downtime, wrong status code, or timeout.',
                            ],
                            'api_health' => [
                                'for' => $isPolishLocale ? 'Dla endpointów API i webhooków. Najlepsze do monitoringu backendu.' : 'For API endpoints and webhooks. Best for backend monitoring.',
                                'alert' => $isPolishLocale ? 'Alarmuje przy błędach 4xx/5xx, timeoutach i utracie dostępności.' : 'Alerts on 4xx/5xx responses, timeouts, and unavailability.',
                            ],
                            'login_journey' => [
                                'for' => $isPolishLocale ? 'Dla krytycznych ścieżek użytkownika (np. logowanie).' : 'For critical user journeys (for example login flow).',
                                'alert' => $isPolishLocale ? 'Alarmuje, gdy kroki scenariusza nie przejdą lub UI nie zawiera oczekiwanych elementów.' : 'Alerts when journey steps fail or expected UI markers are missing.',
                            ],
                            'cron_heartbeat' => [
                                'for' => $isPolishLocale ? 'Dla zadań cron i workerów, które mają regularnie wysyłać sygnał.' : 'For cron jobs and workers that should check in regularly.',
                                'alert' => $isPolishLocale ? 'Alarmuje, gdy nie ma sygnału heartbeat w oczekiwanym czasie.' : 'Alerts when heartbeat check-ins are missing in expected time window.',
                            ],
                        ];
                        $templateGuideFallback = [
                            'for' => $isPolishLocale ? 'Szablon dostosowuje domyślne ustawienia do konkretnego przypadku użycia.' : 'Template applies defaults for a specific use case.',
                            'alert' => $isPolishLocale ? 'Alarmuje zgodnie z regułami wybranego typu monitora.' : 'Alerts according to selected monitor type rules.',
                        ];
                    @endphp

                    @if ($featuredTemplates !== [])
                        <div>
                            <div class="mb-1 block text-sm font-medium text-gray-900 dark:text-gray-100">
                                {{ app()->getLocale() === 'pl' ? 'Wybierz cel monitoringu (polecane)' : 'Choose monitoring goal (recommended)' }}
                            </div>
                            <div id="template-cards" class="hidden grid grid-cols-1 gap-2 sm:grid-cols-2">
                                @foreach ($featuredTemplates as $template)
                                    <button
                                        type="button"
                                        data-template-key="{{ $template['key'] }}"
                                        class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-left transition hover:border-gray-300 hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:hover:border-gray-600"
                                    >
                                        <div class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $template['name'] }}</div>
                                        <div class="mt-1 text-xs text-gray-600 dark:text-gray-400">{{ $template['description'] }}</div>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endif

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
                        <div id="template-selected-state" class="mt-1 text-xs font-medium text-indigo-700 dark:text-indigo-300"></div>
                        <div id="template-purpose" class="mt-1 text-xs text-gray-700 dark:text-gray-300"></div>
                    </div>
                    <div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-xs text-gray-700 dark:border-gray-700 dark:bg-gray-800/40 dark:text-gray-200">
                        <div id="template-guide-for"></div>
                        <div id="template-guide-alert" class="mt-1"></div>
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
                                <option value="port" {{ old('scan_mode') === 'port' ? 'selected' : '' }}>{{ __('messages.monitored_urls.scan_mode_port') }}</option>
                                <option value="heartbeat" {{ old('scan_mode') === 'heartbeat' ? 'selected' : '' }}>{{ __('messages.monitored_urls.scan_mode_heartbeat') }}</option>
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

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium mb-1" for="keyword_must_include">{{ __('messages.monitored_urls.keyword_must_include') }}</label>
                                <input id="keyword_must_include" name="keyword_must_include" type="text" value="{{ old('keyword_must_include') }}" class="app-input" placeholder="{{ __('messages.monitored_urls.keyword_placeholder') }}" />
                                <div class="app-help">{{ __('messages.monitored_urls.keyword_help') }}</div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1" for="port_number">{{ __('messages.monitored_urls.port_number') }}</label>
                                <input id="port_number" name="port_number" type="number" min="1" max="65535" value="{{ old('port_number') }}" class="app-input" placeholder="443" />
                                <div class="app-help">{{ __('messages.monitored_urls.port_help') }}</div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1" for="heartbeat_grace_minutes">{{ __('messages.monitored_urls.heartbeat_grace_minutes') }}</label>
                                <input id="heartbeat_grace_minutes" name="heartbeat_grace_minutes" type="number" min="0" max="10080" value="{{ old('heartbeat_grace_minutes') }}" class="app-input" placeholder="3" />
                                <div class="app-help">{{ __('messages.monitored_urls.heartbeat_grace_help') }}</div>
                            </div>
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
            var cardsWrap = document.getElementById('template-cards');
            var guideFor = document.getElementById('template-guide-for');
            var guideAlert = document.getElementById('template-guide-alert');
            var selectedState = document.getElementById('template-selected-state');
            var purpose = document.getElementById('template-purpose');
            var templateGuides = @json($templateGuide ?? []);
            var templateGuideFallback = @json($templateGuideFallback ?? []);

            if (!select || !description) {
                return;
            }

            var byKey = {};

            if (cardsWrap) {
                cardsWrap.classList.remove('hidden');
            }

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
                    if (purpose) {
                        purpose.textContent = '';
                    }
                    return;
                }

                description.textContent = selected.description || @json(__('Template loaded.'));
                if (!purpose) {
                    return;
                }

                var guide = templateGuides[select.value] || templateGuideFallback;
                var usageText = selected.description || guide.for || templateGuideFallback.for || '';
                purpose.textContent = @json(app()->getLocale() === 'pl' ? 'Do czego służy: ' : 'Use case: ') + usageText;
            }

            function updateSelectedState() {
                if (!selectedState) return;
                var selectedOption = select.options[select.selectedIndex];
                if (!selectedOption || !select.value) {
                    selectedState.textContent = '';
                    return;
                }
                selectedState.textContent = @json(app()->getLocale() === 'pl' ? 'Wybrany typ: ' : 'Selected type: ') + selectedOption.text;
            }

            function setActiveCard(key) {
                if (!cardsWrap) return;
                var cards = cardsWrap.querySelectorAll('[data-template-key]');
                for (var i = 0; i < cards.length; i++) {
                    var card = cards[i];
                    var isActive = card.getAttribute('data-template-key') === key;
                    if (isActive) {
                        card.classList.add('ring-2', 'ring-indigo-500', 'border-indigo-200');
                    } else {
                        card.classList.remove('ring-2', 'ring-indigo-500', 'border-indigo-200');
                    }
                }
            }

            function updateTemplateGuide() {
                if (!guideFor || !guideAlert) return;
                var key = select.value || '';
                var guide = templateGuides[key] || templateGuideFallback;
                guideFor.textContent = @json(app()->getLocale() === 'pl' ? 'Co monitor robi: ' : 'What it does: ') + (guide.for || templateGuideFallback.for || '');
                guideAlert.textContent = @json(app()->getLocale() === 'pl' ? 'Kiedy alarmuje: ' : 'When it alerts: ') + (guide.alert || templateGuideFallback.alert || '');
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
                if (defaults.content_rules && typeof defaults.content_rules === 'object') {
                    var allowKeywords = Array.isArray(defaults.content_rules.allow_keywords) ? defaults.content_rules.allow_keywords : [];
                    setInputValue('keyword_must_include', allowKeywords.length > 0 ? allowKeywords.join(', ') : '');

                    var portDefaults = defaults.content_rules.port && typeof defaults.content_rules.port === 'object'
                        ? defaults.content_rules.port
                        : null;
                    setInputValue('port_number', portDefaults && typeof portDefaults.port !== 'undefined' ? portDefaults.port : '');

                    var heartbeatDefaults = defaults.content_rules.heartbeat && typeof defaults.content_rules.heartbeat === 'object'
                        ? defaults.content_rules.heartbeat
                        : null;
                    setInputValue('heartbeat_grace_minutes', heartbeatDefaults && typeof heartbeatDefaults.grace_minutes !== 'undefined' ? heartbeatDefaults.grace_minutes : '');
                } else {
                    setInputValue('keyword_must_include', '');
                    setInputValue('port_number', '');
                    setInputValue('heartbeat_grace_minutes', '');
                }
                setJsonValue('content_rules', defaults.content_rules);
                setJsonValue('journey_steps', defaults.journey_steps);
            }

            function syncTemplateState(applyDefaults) {
                updateDescription();
                updateTemplateGuide();
                updateSelectedState();
                setActiveCard(select.value);
                if (applyDefaults === true) {
                    applyTemplateDefaults();
                }
            }

            select.addEventListener('change', function () {
                syncTemplateState(true);
            });
            if (apply) {
                apply.addEventListener('click', applyTemplateDefaults);
            }
            if (cardsWrap) {
                cardsWrap.addEventListener('click', function (event) {
                    var target = event.target.closest('[data-template-key]');
                    if (!target) return;
                    var key = target.getAttribute('data-template-key');
                    if (!key) return;
                    select.value = key;
                    syncTemplateState(true);
                });
            }
            syncTemplateState(false);
        });
    </script>
</x-app-layout>

