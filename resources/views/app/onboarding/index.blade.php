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

                <form method="POST" action="{{ route('monitored-urls.store') }}" class="space-y-6" data-monitor-template-hints-root data-locale="{{ app()->getLocale() }}">
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
                            $selectedTemplate = is_string($defaultTemplate) ? ($templateByKey->get($defaultTemplate) ?: null) : null;
                            $initialDescription = is_array($selectedTemplate) ? trim((string) ($selectedTemplate['description'] ?? '')) : '';

                            $initialGuideFor = $initialDescription !== ''
                                ? $initialDescription
                                : (app()->getLocale() === 'pl'
                                    ? 'Szablon ustawia domyslne parametry pod konkretny przypadek uzycia.'
                                    : 'Template applies defaults for a specific use case.');

                            $initialScanMode = is_array($selectedTemplate) && is_array($selectedTemplate['defaults'] ?? null)
                                ? strtolower(trim((string) (($selectedTemplate['defaults']['scan_mode'] ?? '') ?: '')))
                                : '';
                            if ($initialScanMode === 'journey') {
                                $initialGuideAlert = app()->getLocale() === 'pl'
                                    ? 'Alarmuje, gdy kroki scenariusza nie przejda lub zabraknie oczekiwanych elementow UI.'
                                    : 'Alerts when journey steps fail or expected UI elements are missing.';
                            } elseif ($initialScanMode === 'port') {
                                $initialGuideAlert = app()->getLocale() === 'pl'
                                    ? 'Alarmuje, gdy wskazany port TCP nie odpowiada lub polaczenie jest odrzucane.'
                                    : 'Alerts when TCP port does not respond or connection is refused.';
                            } elseif ($initialScanMode === 'heartbeat') {
                                $initialGuideAlert = app()->getLocale() === 'pl'
                                    ? 'Alarmuje, gdy brak sygnalu heartbeat w oczekiwanym oknie czasowym.'
                                    : 'Alerts when heartbeat signal is missing in expected time window.';
                            } else {
                                $initialGuideAlert = app()->getLocale() === 'pl'
                                    ? 'Alarmuje przy niedostepnosci URL, timeoutach i nieoczekiwanym kodzie HTTP.'
                                    : 'Alerts on URL downtime, timeouts, and unexpected HTTP status.';
                            }

                            $initialPurposeText = $initialDescription !== ''
                                ? ((app()->getLocale() === 'pl' ? 'Do czego sluzy: ' : 'Use case: ') . $initialDescription)
                                : '';
                            $featuredTemplates = collect($featuredTemplateKeys)
                                ->map(fn (string $key) => $templateByKey->get($key))
                                ->filter()
                                ->values()
                                ->all();
                        @endphp

                        @if ($featuredTemplates !== [])
                            <div>
                                <div class="mb-1 text-sm font-medium text-gray-900 dark:text-gray-100">
                                    {{ app()->getLocale() === 'pl' ? 'Wybierz cel monitoringu (polecane)' : 'Pick monitoring goal (recommended)' }}
                                </div>
                                <div id="template-cards" class="grid grid-cols-1 gap-3 sm:grid-cols-2">
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
                                <div class="mt-2 text-xs text-gray-600 dark:text-gray-400">
                                    {{ app()->getLocale() === 'pl' ? 'Karty to szybki wybor. Jesli potrzebujesz innego typu, uzyj pelnej listy ponizej.' : 'Cards are a quick pick. If you need another type, use the full list below.' }}
                                </div>
                            </div>
                        @endif

                        <div class="mt-4">
                            <label class="mb-1 block text-sm font-medium text-gray-900 dark:text-gray-100" for="template">
                                {{ app()->getLocale() === 'pl' ? 'Pelna lista typow monitora' : 'Full monitor type list' }}
                            </label>
                            <select id="template" name="template" class="app-input" required>
                                @foreach ($monitorTemplates as $template)
                                    @php($templateScanMode = is_array($template['defaults'] ?? null) ? strtolower(trim((string) ($template['defaults']['scan_mode'] ?? ''))) : '')
                                    <option
                                        value="{{ $template['key'] }}"
                                        data-template-description="{{ $template['description'] }}"
                                        data-template-scan-mode="{{ $templateScanMode }}"
                                        {{ $defaultTemplate === $template['key'] ? 'selected' : '' }}
                                    >
                                        {{ $template['name'] }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="mt-2 text-xs text-gray-600 dark:text-gray-400">
                                {{ __('messages.onboarding.template_pick_help') }}
                            </div>
                        </div>

                        <div id="template-suggestion" class="mt-3 hidden text-xs text-gray-600 dark:text-gray-400"></div>
                        <div id="template-selected-state" class="mt-2 text-xs font-medium text-indigo-700 dark:text-indigo-300"></div>
                        <div id="template-purpose" class="mt-1 rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-xs text-gray-700 dark:border-gray-700 dark:bg-gray-800/40 dark:text-gray-300 {{ $initialPurposeText === '' ? 'hidden' : '' }}">{{ $initialPurposeText }}</div>
                        <div id="template-guide-box" class="mt-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-xs text-gray-700 dark:border-gray-700 dark:bg-gray-800/40 dark:text-gray-200">
                            <div id="template-guide-for">{{ app()->getLocale() === 'pl' ? 'Co monitor robi: ' : 'What it does: ' }}{{ $initialGuideFor }}</div>
                            <div id="template-guide-alert" class="mt-1">{{ app()->getLocale() === 'pl' ? 'Kiedy alarmuje: ' : 'When it alerts: ' }}{{ $initialGuideAlert }}</div>
                        </div>

                        <script type="application/json" data-monitor-template-hints-templates>@json($monitorTemplates ?? [])</script>
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

</x-app-layout>
