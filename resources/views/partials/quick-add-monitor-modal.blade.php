@php
    $user = auth()->user();
    $isOwner = ($user?->role ?? null) !== 'member';
@endphp

@if ($isOwner)
    @php
        $templates = app(\App\Services\MonitorTemplateService::class)->frontendTemplates();
        $defaultTemplate = 'website_uptime';
        $templateByKey = collect($templates)->keyBy('key');
        $featuredQuickTemplateKeys = ['website_uptime', 'api_health', 'login_journey', 'cron_heartbeat'];
        $featuredQuickTemplates = collect($featuredQuickTemplateKeys)
            ->map(fn (string $key) => $templateByKey->get($key))
            ->filter()
            ->values()
            ->all();
        $isPolishLocale = app()->getLocale() === 'pl';
        $quickTemplateGuide = [
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
        $quickTemplateGuideFallback = [
            'for' => $isPolishLocale ? 'Szablon dostosowuje domyślne ustawienia do konkretnego przypadku użycia.' : 'Template applies defaults for a specific use case.',
            'alert' => $isPolishLocale ? 'Alarmuje zgodnie z regułami wybranego typu monitora.' : 'Alerts according to selected monitor type rules.',
        ];
    @endphp

    <x-modal name="quick-add-monitor" :show="false" maxWidth="2xl">
        <div class="px-6 py-5 border-b border-gray-200 dark:border-gray-800 flex items-start justify-between gap-4">
            <div>
                <div class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('messages.quick_add.title') }}</div>
                <div class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ __('messages.quick_add.subtitle') }}</div>
            </div>
            <button type="button" class="app-link" x-on:click="$dispatch('close-modal', 'quick-add-monitor')">
                {{ __('messages.actions.cancel') }}
            </button>
        </div>

        <form method="POST" action="{{ route('monitored-urls.store') }}" class="p-6 space-y-5" id="quick-add-form">
            @csrf
            <input type="hidden" name="from_onboarding" value="1" />
            <input type="hidden" name="is_active" value="1" />

            <div>
                <label class="mb-1 block text-sm font-medium text-gray-900 dark:text-gray-100" for="quick-add-url">
                    {{ __('messages.quick_add.url_label') }}
                </label>
                <input
                    id="quick-add-url"
                    name="url"
                    type="url"
                    required
                    class="app-input"
                    placeholder="{{ __('messages.onboarding.url_placeholder') }}"
                    autocomplete="off"
                />
                <div id="quick-add-suggestion" class="mt-2 hidden text-xs text-gray-600 dark:text-gray-400"></div>
            </div>

            @if ($featuredQuickTemplates !== [])
                <div>
                    <div class="mb-1 block text-sm font-medium text-gray-900 dark:text-gray-100">
                        {{ app()->getLocale() === 'pl' ? 'Wybierz cel monitoringu (polecane)' : 'Choose monitoring goal (recommended)' }}
                    </div>
                    <div id="quick-add-template-cards" class="hidden grid grid-cols-1 gap-2 sm:grid-cols-2">
                        @foreach ($featuredQuickTemplates as $templateItem)
                            <button
                                type="button"
                                data-template-key="{{ $templateItem['key'] }}"
                                class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-left transition hover:border-gray-300 hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:hover:border-gray-600"
                            >
                                <div class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $templateItem['name'] }}</div>
                                <div class="mt-1 text-xs text-gray-600 dark:text-gray-400">{{ $templateItem['description'] }}</div>
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-900 dark:text-gray-100" for="quick-add-template">
                        {{ __('messages.quick_add.type_label') }}
                    </label>
                    <select id="quick-add-template" name="template" class="app-input" required>
                        @foreach ($templates as $template)
                            <option value="{{ $template['key'] }}" {{ $template['key'] === $defaultTemplate ? 'selected' : '' }}>
                                {{ $template['name'] }}
                            </option>
                        @endforeach
                    </select>
                    <div id="quick-add-template-selected-state" class="mt-2 text-xs font-medium text-indigo-700 dark:text-indigo-300"></div>
                    <div id="quick-add-template-purpose" class="mt-1 rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-xs text-gray-700 dark:border-gray-700 dark:bg-gray-800/40 dark:text-gray-300"></div>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-900 dark:text-gray-100" for="quick-add-frequency">
                        {{ __('messages.quick_add.interval_label') }}
                    </label>
                    <select id="quick-add-frequency" name="frequency_minutes" class="app-input">
                        <option value="1">1 min</option>
                        <option value="2">2 min</option>
                        <option value="5" selected>5 min</option>
                        <option value="10">10 min</option>
                        <option value="15">15 min</option>
                        <option value="30">30 min</option>
                        <option value="60">60 min</option>
                        <option value="1440">Daily</option>
                    </select>
                    <div class="mt-1 text-xs text-gray-600 dark:text-gray-400">{{ __('messages.quick_add.interval_help') }}</div>
                </div>
            </div>
            <div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-xs text-gray-700 dark:border-gray-700 dark:bg-gray-800/40 dark:text-gray-200">
                <div id="quick-add-template-guide-for"></div>
                <div id="quick-add-template-guide-alert" class="mt-1"></div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-900 dark:text-gray-100" for="quick-add-name">
                        {{ __('messages.quick_add.name_label') }}
                    </label>
                    <input id="quick-add-name" name="display_name" type="text" class="app-input" placeholder="{{ __('messages.onboarding.display_name_placeholder') }}" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-900 dark:text-gray-100" for="quick-add-tags">
                        {{ __('messages.quick_add.tags_label') }}
                    </label>
                    <input id="quick-add-tags" name="tags" type="text" class="app-input" placeholder="{{ __('messages.onboarding.tags_placeholder') }}" />
                </div>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between pt-2 border-t border-gray-200 dark:border-gray-800">
                <div class="flex flex-wrap gap-3 text-sm">
                    <a class="app-link" href="{{ route('onboarding.show') }}">
                        {{ __('messages.quick_add.open_wizard') }}
                    </a>
                    <a class="app-link" href="{{ route('monitored-urls.create') }}">
                        {{ __('messages.quick_add.open_advanced') }}
                    </a>
                    <a class="app-link" href="{{ route('seo-risk.index') }}">
                        {{ __('messages.nav.seo_risk') }}
                    </a>
                    <a class="app-link" href="{{ route('notification-channels.edit') }}">
                        {{ __('messages.quick_add.open_notifications') }}
                    </a>
                </div>
                <div class="flex gap-2">
                    <button type="button" class="app-button-secondary" x-on:click="$dispatch('close-modal', 'quick-add-monitor')">
                        {{ __('messages.actions.cancel') }}
                    </button>
                    <button type="submit" class="app-button-primary">
                        {{ __('messages.quick_add.submit') }}
                    </button>
                </div>
            </div>
        </form>

        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var url = document.getElementById('quick-add-url');
                var template = document.getElementById('quick-add-template');
                var suggestion = document.getElementById('quick-add-suggestion');
                var selectedState = document.getElementById('quick-add-template-selected-state');
                var purpose = document.getElementById('quick-add-template-purpose');
                var cardsWrap = document.getElementById('quick-add-template-cards');
                var guideFor = document.getElementById('quick-add-template-guide-for');
                var guideAlert = document.getElementById('quick-add-template-guide-alert');
                var templates = {!! json_encode($templates, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!};
                var templateGuides = {!! json_encode($quickTemplateGuide, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!};
                var templateGuideFallback = {!! json_encode($quickTemplateGuideFallback, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!};

                if (!url || !template) return;

                var userPickedTemplate = false;
                var byKey = {};

                if (cardsWrap) {
                    cardsWrap.classList.remove('hidden');
                }

                for (var i = 0; i < templates.length; i++) {
                    var item = templates[i];
                    if (item && typeof item.key === 'string') {
                        byKey[item.key] = item;
                    }
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
                    if (!selectedState) return;
                    var selectedOption = template.options[template.selectedIndex];
                    if (!selectedOption) {
                        selectedState.textContent = '';
                        return;
                    }

                    var prefix = {{ json_encode(app()->getLocale() === 'pl' ? 'Wybrany typ: ' : 'Selected type: ') }};
                    selectedState.textContent = prefix + selectedOption.text;
                }

                function updatePurpose() {
                    if (!purpose) return;
                    var selected = byKey[template.value];
                    if (!selected) {
                        purpose.textContent = '';
                        return;
                    }

                    var guide = templateGuides[template.value] || templateGuideFallback;
                    var usageText = selected.description || guide.for || templateGuideFallback.for || '';
                    purpose.textContent = {{ json_encode(app()->getLocale() === 'pl' ? 'Do czego służy: ' : 'Use case: ') }} + usageText;
                }

                function updateTemplateGuide() {
                    if (!guideFor || !guideAlert) return;
                    var key = template.value || '';
                    var selected = byKey[key] || null;
                    var guide = templateGuides[key] || {};
                    var forText = guide.for || (selected && selected.description ? selected.description : '') || templateGuideFallback.for || '';
                    var alertText = guide.alert || '';

                    if (!alertText && selected && selected.defaults && typeof selected.defaults === 'object') {
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

                    if (!alertText) {
                        alertText = templateGuideFallback.alert || '';
                    }

                    guideFor.textContent = {{ json_encode(app()->getLocale() === 'pl' ? 'Co monitor robi: ' : 'What it does: ') }} + forText;
                    guideAlert.textContent = {{ json_encode(app()->getLocale() === 'pl' ? 'Kiedy alarmuje: ' : 'When it alerts: ') }} + alertText;
                }

                function guessTemplateKey(raw) {
                    if (typeof raw !== 'string') return null;
                    var value = raw.trim().toLowerCase();
                    if (!value) return null;
                    var hostname = '';
                    try {
                        hostname = new URL(value).hostname.toLowerCase();
                    } catch (e) {
                        hostname = '';
                    }

                    if (value.indexOf('/cron') !== -1 || value.indexOf('/heartbeat') !== -1) return 'cron_heartbeat';
                    if (value.indexOf('/checkout') !== -1 || value.indexOf('/cart') !== -1) return 'ecommerce_checkout';
                    if (value.indexOf('/tracking') !== -1 || value.indexOf('/track') !== -1) return 'logistics_tracking';
                    if (value.indexOf('/login') !== -1 || value.indexOf('/signin') !== -1) return 'login_journey';
                    if (value.indexOf('/health') !== -1 || value.indexOf('/api/') !== -1) return 'api_health';
                    if (value.indexOf('/cdn-cgi/trace') !== -1) return 'cloudflare_edge';
                    if (hostname.indexOf('origin.') === 0 || hostname.indexOf('.origin.') !== -1) return 'origin_bypass';

                    return 'website_uptime';
                }

                function setSuggestionText(text) {
                    if (!suggestion) return;
                    if (!text) {
                        suggestion.classList.add('hidden');
                        suggestion.textContent = '';
                        return;
                    }
                    suggestion.textContent = text;
                    suggestion.classList.remove('hidden');
                }

                function maybeSuggestTemplate() {
                    var key = guessTemplateKey(url.value);
                    if (!key) {
                        setSuggestionText('');
                        return;
                    }

                    var matchedLabel = null;
                    for (var j = 0; j < template.options.length; j++) {
                        if (template.options[j].value === key) {
                            matchedLabel = template.options[j].text;
                            break;
                        }
                    }

                    if (!userPickedTemplate) {
                        template.value = key;
                    }

                    setActiveCard(template.value);
                    updateTemplateGuide();
                    updateSelectedState();
                    updatePurpose();

                    if (matchedLabel && !userPickedTemplate) {
                        setSuggestionText({!! json_encode(__('messages.quick_add.suggested_prefix')) !!} + matchedLabel + '.');
                    } else {
                        setSuggestionText('');
                    }
                }

                url.addEventListener('change', maybeSuggestTemplate);
                url.addEventListener('blur', maybeSuggestTemplate);
                url.addEventListener('input', function () {
                    if (url.value.length >= 8) maybeSuggestTemplate();
                });

                template.addEventListener('change', function () {
                    userPickedTemplate = true;
                    setActiveCard(template.value);
                    updateTemplateGuide();
                    updateSelectedState();
                    updatePurpose();
                    setSuggestionText('');
                });
                template.addEventListener('input', function () {
                    userPickedTemplate = true;
                    setActiveCard(template.value);
                    updateTemplateGuide();
                    updateSelectedState();
                    updatePurpose();
                    setSuggestionText('');
                });

                if (cardsWrap) {
                    cardsWrap.addEventListener('click', function (event) {
                        var target = event.target.closest('[data-template-key]');
                        if (!target) return;
                        var key = target.getAttribute('data-template-key');
                        if (!key) return;
                        userPickedTemplate = true;
                        template.value = key;
                        setActiveCard(key);
                        updateTemplateGuide();
                        updateSelectedState();
                        updatePurpose();
                        setSuggestionText('');
                    });
                }

                // Ensure defaults.
                if (!template.value) template.value = {{ json_encode($defaultTemplate) }};
                setActiveCard(template.value);
                updateTemplateGuide();
                updateSelectedState();
                updatePurpose();
            });
        </script>
    </x-modal>
@endif
