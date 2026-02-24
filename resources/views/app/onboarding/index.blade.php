<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl leading-tight text-gray-900 dark:text-gray-100">
                {{ __('messages.onboarding.title') }}
            </h2>
            <a href="{{ route('dashboard') }}" class="app-link">
                {{ __('messages.onboarding.back_to_dashboard') }}
            </a>
        </div>
    </x-slot>

    <div class="app-page">
        <div class="app-container-3xl">
            @if ($hasMonitors)
                <div class="app-card app-card-warning text-sm">
                    {{ __('messages.onboarding.has_monitors_notice') }}
                </div>
            @endif

            <div class="app-card app-card-lg">
                <div class="mb-4">
                    <div class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('messages.onboarding.steps_title') }}</div>
                    <ol class="mt-2 list-inside list-decimal space-y-1 text-sm text-gray-700 dark:text-gray-300">
                        <li>{{ __('messages.onboarding.step_1') }}</li>
                        <li>{{ __('messages.onboarding.step_2') }}</li>
                        <li>{{ __('messages.onboarding.step_3') }}</li>
                    </ol>
                </div>

                <form method="POST" action="{{ route('monitored-urls.store') }}" class="space-y-6">
                    @csrf
                    <input type="hidden" name="from_onboarding" value="1" />
                    <input type="hidden" name="is_active" value="1" />

                    <div>
                        @php
                            $defaultTemplate = old('template') ?: 'website_uptime';
                            $limits = app(\App\Services\TenantQuotaService::class)->limitsForTenant(auth()->user()?->tenant);
                            $minFrequencyMinutes = max(1, (int) ($limits['min_frequency_minutes'] ?? 1));
                            $frequencyOptions = array_values(array_filter([1, 2, 5, 10, 15, 30, 60, 1440], fn (int $v) => $v >= $minFrequencyMinutes));
                            $defaultFrequency = in_array(5, $frequencyOptions, true) ? 5 : ($frequencyOptions[0] ?? 5);
                            $featuredTemplateKeys = ['website_uptime', 'keyword_presence', 'api_health', 'cron_heartbeat'];
                            $templateByKey = collect($monitorTemplates)->keyBy('key');
                            $featuredTemplates = collect($featuredTemplateKeys)
                                ->map(fn (string $key) => $templateByKey->get($key))
                                ->filter()
                                ->values()
                                ->all();
                        @endphp

                        <label class="mb-1 block text-sm font-medium text-gray-900 dark:text-gray-100" for="template">
                            {{ __('messages.onboarding.template_label') }}
                        </label>
                        <select id="template" name="template" class="app-input" required>
                            @foreach ($monitorTemplates as $template)
                                <option value="{{ $template['key'] }}" {{ $defaultTemplate === $template['key'] ? 'selected' : '' }}>
                                    {{ $template['name'] }}
                                </option>
                            @endforeach
                        </select>
                        <div class="mt-2 text-xs text-gray-600 dark:text-gray-400">
                            {{ __('messages.onboarding.template_pick_help') }}
                        </div>

                        <div id="template-suggestion" class="mt-3 hidden text-xs text-gray-600 dark:text-gray-400"></div>
                        <div id="template-selected-state" class="mt-2 text-xs font-medium text-indigo-700 dark:text-indigo-300"></div>
                        <div id="template-purpose" class="mt-1 hidden rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-xs text-gray-700 dark:border-gray-700 dark:bg-gray-800/40 dark:text-gray-300"></div>
                        <div id="template-guide-box" class="mt-2 hidden rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-xs text-gray-700 dark:border-gray-700 dark:bg-gray-800/40 dark:text-gray-200">
                            <div id="template-guide-for"></div>
                            <div id="template-guide-alert" class="mt-1"></div>
                        </div>

                        @if ($featuredTemplates !== [])
                            <div class="mt-4">
                                <div class="mb-1 text-sm font-medium text-gray-900 dark:text-gray-100">
                                    {{ app()->getLocale() === 'pl' ? 'Szybkie skróty typu monitora (kliknij kartę)' : 'Quick monitor type shortcuts (click card)' }}
                                </div>
                                <div id="template-cards" class="hidden grid grid-cols-1 gap-3 sm:grid-cols-2">
                                    @foreach ($featuredTemplates as $template)
                                        <button
                                            type="button"
                                            data-template-key="{{ $template['key'] }}"
                                            class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-left transition hover:border-gray-300 hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:border-gray-800 dark:bg-gray-900 dark:hover:border-gray-700 dark:hover:bg-gray-900/60"
                                        >
                                            <div class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $template['name'] }}</div>
                                            <div class="mt-1 text-xs leading-relaxed text-gray-600 dark:text-gray-400">{{ $template['description'] }}</div>
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-900 dark:text-gray-100" for="url">{{ __('messages.monitored_urls.url') }}</label>
                        <input id="url" name="url" type="url" list="onboarding-url-suggestions" value="{{ old('url') }}" required class="app-input" placeholder="{{ __('messages.onboarding.url_placeholder') }}" />
                        <datalist id="onboarding-url-suggestions">
                            <option value="https://example.com"></option>
                            <option value="https://example.com/login"></option>
                            <option value="https://example.com/health"></option>
                        </datalist>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-900 dark:text-gray-100" for="display_name">{{ __('messages.monitored_urls.display_name') }}</label>
                        <input id="display_name" name="display_name" type="text" value="{{ old('display_name') }}" class="app-input" placeholder="{{ __('messages.onboarding.display_name_placeholder') }}" />
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-900 dark:text-gray-100" for="tags">{{ __('messages.monitored_urls.tags') }}</label>
                        <input id="tags" name="tags" type="text" value="{{ old('tags') }}" class="app-input" placeholder="{{ __('messages.onboarding.tags_placeholder') }}" />
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-900 dark:text-gray-100" for="frequency_minutes">
                            {{ __('messages.quick_add.interval_label') }}
                        </label>
                        <select id="frequency_minutes" name="frequency_minutes" class="app-input">
                            @php($picked = (int) old('frequency_minutes', $defaultFrequency))
                            @foreach ($frequencyOptions as $value)
                                <option value="{{ $value }}" {{ $picked === $value ? 'selected' : '' }}>
                                    @if ($value === 1440)
                                        {{ app()->getLocale() === 'pl' ? 'Dziennie' : 'Daily' }}
                                    @else
                                        {{ $value }} min
                                    @endif
                                </option>
                            @endforeach
                        </select>
                        <div class="mt-1 text-xs text-gray-600 dark:text-gray-400">{{ __('messages.quick_add.interval_help') }}</div>
                        @if ($minFrequencyMinutes > 1)
                            <div class="mt-1 text-xs text-gray-600 dark:text-gray-400">
                                {{ app()->getLocale() === 'pl' ? 'Minimum w Twoim planie: ' : 'Plan minimum: ' }}{{ $minFrequencyMinutes }} min
                            </div>
                        @endif
                    </div>

                    <div class="app-card app-card-success text-sm">
                        {{ __('messages.onboarding.baseline_guidance') }}
                    </div>

                    <div class="pt-2">
                        <button type="submit" class="app-button-primary">
                            {{ __('messages.onboarding.create_monitor') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var url = document.getElementById('url');
            var template = document.getElementById('template');
            var suggestion = document.getElementById('template-suggestion');
            var selectedState = document.getElementById('template-selected-state');
            var purpose = document.getElementById('template-purpose');
            var cardsWrap = document.getElementById('template-cards');
            var templates = @json($monitorTemplates ?? []);
            var guideBox = document.getElementById('template-guide-box');
            var guideFor = document.getElementById('template-guide-for');
            var guideAlert = document.getElementById('template-guide-alert');
            var frequency = document.getElementById('frequency_minutes');
            var userPicked = false;
            var byKey = {};
            var userPickedFrequency = false;

            if (!url || !template) {
                return;
            }

            for (var i = 0; i < templates.length; i++) {
                var item = templates[i];
                if (item && typeof item.key === 'string') {
                    byKey[item.key] = item;
                }
            }

            if (cardsWrap) {
                cardsWrap.classList.remove('hidden');
            }

            if (frequency) {
                frequency.addEventListener('change', function () {
                    userPickedFrequency = true;
                });
                frequency.addEventListener('input', function () {
                    userPickedFrequency = true;
                });
            }

            function setActiveCard(key) {
                if (!cardsWrap) return;
                var cards = cardsWrap.querySelectorAll('[data-template-key]');
                for (var i = 0; i < cards.length; i++) {
                    var card = cards[i];
                    var isActive = card.getAttribute('data-template-key') === key;
                    if (isActive) {
                        card.classList.add('ring-2', 'ring-indigo-500', 'border-indigo-300', 'bg-indigo-50', 'dark:bg-indigo-500/10');
                    } else {
                        card.classList.remove('ring-2', 'ring-indigo-500', 'border-indigo-300', 'bg-indigo-50', 'dark:bg-indigo-500/10');
                    }
                }
            }

            function updateSelectedState() {
                if (!selectedState) {
                    return;
                }

                var selectedOption = template.options[template.selectedIndex];
                if (!selectedOption) {
                    selectedState.textContent = '';
                    return;
                }

                var prefix = {{ json_encode(app()->getLocale() === 'pl' ? 'Wybrany typ: ' : 'Selected type: ') }};
                selectedState.textContent = prefix + selectedOption.text;
            }

            function updateGuide() {
                if (!guideFor || !guideAlert || !guideBox) {
                    return;
                }

                var selected = byKey[template.value] || null;
                var forText = selected && selected.description ? String(selected.description).trim() : '';
                if (forText === '') {
                    forText = {{ json_encode(app()->getLocale() === 'pl' ? 'Szablon dostosowuje domyślne ustawienia do konkretnego przypadku użycia.' : 'Template applies defaults for a specific use case.') }};
                }

                var alertText = '';
                if (selected && selected.defaults && typeof selected.defaults === 'object') {
                    var scanMode = String(selected.defaults.scan_mode || '').toLowerCase();
                    if (scanMode === 'journey') {
                        alertText = {{ json_encode(app()->getLocale() === 'pl'
                            ? 'Alarmuje, gdy kroki scenariusza nie przejdą lub zabraknie oczekiwanych elementów UI.'
                            : 'Alerts when journey steps fail or expected UI elements are missing.') }};
                    } else if (scanMode === 'port') {
                        alertText = {{ json_encode(app()->getLocale() === 'pl'
                            ? 'Alarmuje, gdy wskazany port TCP nie odpowiada lub połączenie jest odrzucane.'
                            : 'Alerts when TCP port does not respond or connection is refused.') }};
                    } else if (scanMode === 'heartbeat') {
                        alertText = {{ json_encode(app()->getLocale() === 'pl'
                            ? 'Alarmuje, gdy brak sygnału heartbeat w oczekiwanym oknie czasowym.'
                            : 'Alerts when heartbeat signal is missing in expected time window.') }};
                    } else {
                        alertText = {{ json_encode(app()->getLocale() === 'pl'
                            ? 'Alarmuje przy niedostępności URL, timeoutach i nieoczekiwanym kodzie HTTP.'
                            : 'Alerts on URL downtime, timeouts, and unexpected HTTP status.') }};
                    }
                }
                if (alertText === '') {
                    alertText = {{ json_encode(app()->getLocale() === 'pl'
                        ? 'Alarmuje zgodnie z regułami wybranego typu monitora.'
                        : 'Alerts according to selected monitor type rules.') }};
                }

                guideFor.textContent = {{ json_encode(app()->getLocale() === 'pl' ? 'Co monitor robi: ' : 'What it does: ') }} + forText;
                guideAlert.textContent = {{ json_encode(app()->getLocale() === 'pl' ? 'Kiedy alarmuje: ' : 'When it alerts: ') }} + alertText;
                guideBox.classList.remove('hidden');
            }

            function maybeApplyTemplateDefaultsToFrequency() {
                if (!frequency || userPickedFrequency) {
                    return;
                }

                var selected = byKey[template.value] || null;
                if (!selected || !selected.defaults || typeof selected.defaults !== 'object') {
                    return;
                }

                var value = selected.defaults.frequency_minutes;
                if (value === null || typeof value === 'undefined') {
                    return;
                }

                frequency.value = String(value);
            }

            function updatePurpose() {
                if (!purpose) {
                    return;
                }

                var selected = byKey[template.value];
                if (!selected) {
                    purpose.textContent = '';
                    purpose.classList.add('hidden');
                    return;
                }

                var description = selected.description || '';
                var usageText = description.trim();
                if (usageText === '') {
                    purpose.textContent = '';
                    purpose.classList.add('hidden');
                    return;
                }

                purpose.textContent = {{ json_encode(app()->getLocale() === 'pl' ? 'Do czego służy: ' : 'Use case: ') }} + usageText;
                purpose.classList.remove('hidden');
            }

            function guessTemplateKey(raw) {
                if (typeof raw !== 'string') {
                    return null;
                }

                var value = raw.trim().toLowerCase();
                var hostname = '';
                if (!value) {
                    return null;
                }
                try {
                    hostname = new URL(value).hostname.toLowerCase();
                } catch (e) {
                    hostname = '';
                }

                if (value.indexOf('/cron') !== -1 || value.indexOf('/heartbeat') !== -1) {
                    return 'cron_heartbeat';
                }

                if (value.indexOf('/checkout') !== -1 || value.indexOf('/cart') !== -1) {
                    return 'ecommerce_checkout';
                }

                if (value.indexOf('/tracking') !== -1 || value.indexOf('/track') !== -1) {
                    return 'logistics_tracking';
                }

                if (value.indexOf('/login') !== -1 || value.indexOf('/signin') !== -1) {
                    return 'login_journey';
                }

                if (value.indexOf('/health') !== -1 || value.indexOf('/api/') !== -1) {
                    return 'api_health';
                }
                
                if (value.indexOf('/cdn-cgi/trace') !== -1) {
                    return 'cloudflare_edge';
                }

                if (hostname.indexOf('origin.') === 0 || hostname.indexOf('.origin.') !== -1) {
                    return 'origin_bypass';
                }

                return 'website_uptime';
            }

            function maybeApplySuggestion() {
                var key = guessTemplateKey(url.value);
                if (!key) {
                    if (suggestion) {
                        suggestion.classList.add('hidden');
                        suggestion.textContent = '';
                    }
                    return;
                }

                var matchedLabel = null;
                for (var j = 0; j < template.options.length; j++) {
                    if (template.options[j].value === key) {
                        matchedLabel = template.options[j].text;
                        break;
                    }
                }

                if (!userPicked) {
                    for (var i = 0; i < template.options.length; i++) {
                        if (template.options[i].value === key) {
                            template.value = key;
                            break;
                        }
                    }
                }

                setActiveCard(template.value);
                updateSelectedState();
                updatePurpose();
                updateGuide();
                maybeApplyTemplateDefaultsToFrequency();

                if (suggestion && matchedLabel && !userPicked) {
                    suggestion.textContent = ({{ json_encode(app()->getLocale() === 'pl' ? 'Sugerowany typ: ' : 'Suggested: ') }} + matchedLabel + '.');
                    suggestion.classList.remove('hidden');
                } else if (suggestion) {
                    suggestion.classList.add('hidden');
                    suggestion.textContent = '';
                }
            }

            url.addEventListener('change', maybeApplySuggestion);
            url.addEventListener('blur', maybeApplySuggestion);
            url.addEventListener('input', function () {
                if (url.value.length >= 8) {
                    maybeApplySuggestion();
                }
            });

            if (cardsWrap) {
                cardsWrap.addEventListener('click', function (event) {
                    var target = event.target.closest('[data-template-key]');
                    if (!target) return;
                    var key = target.getAttribute('data-template-key');
                    if (!key) return;
                    userPicked = true;
                    template.value = key;
                    setActiveCard(key);
                    updateSelectedState();
                    updatePurpose();
                    updateGuide();
                    maybeApplyTemplateDefaultsToFrequency();
                    if (suggestion) {
                        suggestion.classList.add('hidden');
                        suggestion.textContent = '';
                    }
                });
            }

            template.addEventListener('change', function () {
                userPicked = true;
                setActiveCard(template.value);
                updateSelectedState();
                updatePurpose();
                updateGuide();
                maybeApplyTemplateDefaultsToFrequency();
                if (!suggestion) {
                    return;
                }
                suggestion.classList.add('hidden');
                suggestion.textContent = '';
            });

            // Initial sync.
            setActiveCard(template.value || {{ json_encode($defaultTemplate) }});
            updateSelectedState();
            updatePurpose();
            updateGuide();
            maybeApplyTemplateDefaultsToFrequency();
        });
    </script>
</x-app-layout>
