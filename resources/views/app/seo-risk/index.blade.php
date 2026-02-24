<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight dark:text-gray-100">
                {{ __('messages.seo_risk.title') }}
            </h2>
            <div class="flex items-center gap-3">
                @if ($canManage)
                    <form method="POST" action="{{ route('seo-risk.sync') }}">
                        @csrf
                        <button type="submit" class="app-button-primary">
                            {{ __('messages.seo_risk.run_sync_now') }}
                        </button>
                    </form>
                @else
                    <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-1 text-xs font-medium text-gray-700 dark:bg-gray-700 dark:text-gray-200">
                        {{ __('messages.actions.read_only') }}
                    </span>
                @endif
                <a href="{{ route('dashboard') }}" class="app-link">
                    {{ __('messages.nav.dashboard') }}
                </a>
            </div>
        </div>
    </x-slot>

    <div class="app-page">
        <div class="app-container space-y-4">
            <section class="app-card">
                @if ($canManage)
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                        {{ app()->getLocale() === 'pl' ? 'Szybki start modułu SEO (3 kroki)' : 'SEO Risk quick start (3 steps)' }}
                    </h3>
                    <ol class="mt-2 list-inside list-decimal space-y-1 text-sm text-gray-700 dark:text-gray-300">
                        <li>{{ app()->getLocale() === 'pl' ? 'Podłącz Google Search Console, Google Analytics 4 lub oba źródła.' : 'Connect Google Search Console, Google Analytics 4, or both.' }}</li>
                        <li>{{ app()->getLocale() === 'pl' ? 'Dodaj właściwość SEO i wybierz powiązany monitor URL.' : 'Add an SEO property and map it to a monitor URL.' }}</li>
                        <li>{{ app()->getLocale() === 'pl' ? 'Uruchom synchronizację, a alerty pojawią się jako incydenty SEO z listą działań.' : 'Run sync and receive SEO incidents with a recovery checklist.' }}</li>
                    </ol>
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                        {{ app()->getLocale() === 'pl' ? 'Wskazówka: po wyborze monitora formularz automatycznie podpowie domenę i format GSC (sc-domain:twojadomena).' : 'Tip: after selecting a monitor, the form auto-suggests domain and GSC format (sc-domain:yourdomain).' }}
                    </p>
                @else
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                        {{ app()->getLocale() === 'pl' ? 'Moduł SEO (tylko odczyt)' : 'SEO Risk (read-only)' }}
                    </h3>
                    <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">
                        {{ app()->getLocale() === 'pl' ? 'Widzisz alerty i sygnały SEO dla swojej przestrzeni roboczej. Konfigurację integracji i właściwości SEO wykonuje właściciel przestrzeni roboczej.' : 'You can view SEO alerts and signals for your workspace. Integration and SEO property setup is managed by workspace owner.' }}
                    </p>
                @endif
            </section>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <section class="app-card">
                    <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('messages.seo_risk.connected_integrations') }}</div>
                    <div class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">
                        {{ collect($integrations)->filter(fn ($integration) => (bool) $integration->is_enabled)->count() }}/2
                    </div>
                </section>
                <section class="app-card">
                    <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('messages.seo_risk.properties_total') }}</div>
                    <div class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $properties->count() }}</div>
                </section>
                <section class="app-card">
                    <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('messages.seo_risk.monitors_available') }}</div>
                    <div class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $monitoredUrls->count() }}</div>
                </section>
            </div>

            <section class="app-card">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                    {{ app()->getLocale() === 'pl' ? 'Limity i funkcje modułu SEO dla Twojego planu' : 'SEO Risk plan limits and features' }}
                </h3>
                <div class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-4 text-sm">
                    <div class="rounded-lg border border-gray-200 px-3 py-2 dark:border-gray-700">
                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ app()->getLocale() === 'pl' ? 'Plan' : 'Plan' }}</div>
                        <div class="font-semibold text-gray-900 dark:text-gray-100 uppercase">{{ (string) ($seoRiskPlan['tier'] ?? '-') }}</div>
                    </div>
                    <div class="rounded-lg border border-gray-200 px-3 py-2 dark:border-gray-700">
                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ app()->getLocale() === 'pl' ? 'Maks. właściwości SEO' : 'Max SEO properties' }}</div>
                        <div class="font-semibold text-gray-900 dark:text-gray-100">{{ (int) ($seoRiskPlan['max_properties'] ?? 0) }}</div>
                    </div>
                    <div class="rounded-lg border border-gray-200 px-3 py-2 dark:border-gray-700">
                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ app()->getLocale() === 'pl' ? 'Automatyczna synchronizacja (codziennie)' : 'Auto sync (daily)' }}</div>
                        <div class="font-semibold text-gray-900 dark:text-gray-100">{{ (bool) ($seoRiskPlan['auto_sync'] ?? false) ? __('messages.actions.yes') : __('messages.actions.no') }}</div>
                    </div>
                    <div class="rounded-lg border border-gray-200 px-3 py-2 dark:border-gray-700">
                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ app()->getLocale() === 'pl' ? 'Ryzyko utraty linków' : 'Backlink risk' }}</div>
                        <div class="font-semibold text-gray-900 dark:text-gray-100">{{ (bool) ($seoRiskPlan['backlinks_enabled'] ?? false) ? __('messages.actions.yes') : __('messages.actions.no') }}</div>
                    </div>
                </div>
            </section>

            <section class="app-card">
                <div class="flex items-center justify-between gap-3">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('messages.seo_risk.action_cards_title') }}</h3>
                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ __('messages.seo_risk.action_cards_hint') }}</span>
                </div>
                <div class="mt-3 grid grid-cols-1 gap-3 lg:grid-cols-2">
                    @forelse ($actionCards as $card)
                        <article class="rounded-xl border px-4 py-3 {{ $card['priority'] === 'critical' ? 'border-red-300 bg-red-50 dark:border-red-500/40 dark:bg-red-500/10' : 'border-amber-300 bg-amber-50 dark:border-amber-500/40 dark:bg-amber-500/10' }}">
                            <div class="flex items-center justify-between gap-2">
                                <span class="inline-flex items-center rounded-full px-2 py-1 text-[11px] font-semibold uppercase tracking-wide {{ $card['priority'] === 'critical' ? 'bg-red-100 text-red-700 dark:bg-red-500/20 dark:text-red-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300' }}">
                                    {{ $card['priority'] === 'critical' ? __('messages.seo_risk.priority_critical') : __('messages.seo_risk.priority_warning') }}
                                </span>
                                <span class="text-xs font-medium text-gray-600 dark:text-gray-300">
                                    {{ __('messages.seo_risk.severity_short') }} {{ $card['severity'] }}
                                </span>
                            </div>
                            <h4 class="mt-2 text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $card['summary'] }}</h4>
                            <p class="mt-1 text-xs text-gray-600 dark:text-gray-300">
                                {{ $card['kind_label'] }} | {{ optional($card['detected_at'])->diffForHumans() }}
                            </p>
                            <p class="mt-2 text-sm text-gray-700 dark:text-gray-200">
                                {{ $card['next_step'] ?: __('messages.seo_risk.action_cards_default_step') }}
                            </p>
                            <div class="mt-3">
                                <a href="{{ $card['cta_url'] }}" class="{{ $card['priority'] === 'critical' ? 'app-button-primary' : 'app-button-secondary' }}">
                                    {{ __('messages.seo_risk.action_cards_open_incident') }}
                                </a>
                            </div>
                        </article>
                    @empty
                        <div class="rounded-xl border border-gray-200 px-4 py-3 text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400 lg:col-span-2">
                            {{ __('messages.seo_risk.action_cards_empty') }}
                        </div>
                    @endforelse
                </div>
            </section>

            <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
                @foreach (['gsc' => 'Google Search Console', 'ga4' => 'Google Analytics 4'] as $provider => $label)
                    @php
                        $integration = $integrations[$provider] ?? null;
                    @endphp
                    <section class="app-card">
                        <div class="flex items-center justify-between gap-3">
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $label }}</h3>
                            <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium {{ ($integration?->is_enabled ?? false) ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200' }}">
                                {{ ($integration?->is_enabled ?? false) ? __('messages.seo_risk.connected') : __('messages.seo_risk.not_connected') }}
                            </span>
                        </div>

                        <div class="mt-3 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-800/40 dark:text-gray-200">
                            <p>{{ __('messages.seo_risk.platform_oauth_managed') }}</p>
                            @if (is_string($integration?->external_account_email) && trim((string) $integration?->external_account_email) !== '')
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    {{ __('messages.seo_risk.connected_account') }}: {{ $integration?->external_account_email }}
                                </p>
                            @endif
                        </div>

                        @if ($canManage)
                            <div class="mt-3 flex flex-wrap gap-2">
                                @if ((bool) ($integration?->is_enabled ?? false))
                                    <form method="POST" action="{{ route('seo-risk.integrations.disconnect', ['provider' => $provider]) }}">
                                        @csrf
                                        <button type="submit" class="app-button-danger">
                                            {{ __('messages.seo_risk.disconnect_google') }}
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('seo-risk.integrations.connect', ['provider' => $provider]) }}">
                                        @csrf
                                        <button type="submit" class="app-button-secondary">
                                            {{ __('messages.seo_risk.reconnect_google_oauth') }}
                                        </button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('seo-risk.integrations.connect', ['provider' => $provider]) }}">
                                        @csrf
                                        <button type="submit" class="app-button-primary">
                                            {{ __('messages.seo_risk.connect_google_oauth') }}
                                        </button>
                                    </form>
                                @endif
                            </div>
                        @else
                            <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                                {{ app()->getLocale() === 'pl' ? 'Tryb tylko do odczytu. Połączenia OAuth zmienia właściciel przestrzeni roboczej.' : 'Read-only mode. OAuth connections can be managed by workspace owner.' }}
                            </p>
                        @endif

                        @if (is_string($integration?->last_error) && trim((string) $integration?->last_error) !== '')
                            <div class="mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 dark:border-red-500/40 dark:bg-red-500/10 dark:text-red-300">
                                {{ $integration?->last_error }}
                            </div>
                        @endif
                    </section>
                @endforeach
            </div>

            <section class="app-card">
                <div class="flex items-center justify-between gap-3">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('messages.seo_risk.core_updates_title') }}</h3>
                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ __('messages.seo_risk.core_updates_hint') }}</span>
                </div>
                <div class="mt-3 space-y-2">
                    @forelse ($recentCoreUpdates as $event)
                        <div class="rounded-lg border border-gray-200 px-3 py-2 dark:border-gray-700">
                            <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $event->title }}</div>
                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                {{ optional($event->published_at)->toDateString() ?: '-' }}
                                @if ($event->effective_from || $event->effective_to)
                                    | {{ optional($event->effective_from)->toDateString() ?: '?' }} -> {{ optional($event->effective_to)->toDateString() ?: '?' }}
                                @endif
                            </div>
                            @if (is_string($event->source_url) && trim($event->source_url) !== '')
                                <a href="{{ $event->source_url }}" target="_blank" rel="noopener noreferrer" class="mt-1 inline-flex text-xs text-blue-600 hover:text-blue-500 dark:text-blue-400">
                                    {{ __('messages.seo_risk.source_link') }}
                                </a>
                            @endif
                        </div>
                    @empty
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('messages.seo_risk.no_core_updates') }}</p>
                    @endforelse
                </div>
            </section>

            @if ($canManage)
            <section class="app-card">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('messages.seo_risk.add_property') }}</h3>
                @if ($errors->any())
                    <div class="mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 dark:border-red-500/40 dark:bg-red-500/10 dark:text-red-300">
                        <ul class="list-disc list-inside space-y-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                @if ($isPropertyLimitReached)
                    <div class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-200">
                        {{ app()->getLocale() === 'pl' ? 'Osiągnięto limit właściwości SEO dla aktualnego planu. Zmień plan, aby dodać kolejne.' : 'SEO property limit reached for current plan. Upgrade plan to add more properties.' }}
                    </div>
                @endif
                @php
                    $extractDomain = static function (string $url): string {
                        $host = (string) parse_url($url, PHP_URL_HOST);
                        $host = strtolower(trim($host));
                        if ($host === '') {
                            return '';
                        }

                        return str_starts_with($host, 'www.') ? substr($host, 4) : $host;
                    };
                @endphp
                <form method="POST" action="{{ route('seo-risk.properties.store') }}" class="mt-4 grid grid-cols-1 gap-3 lg:grid-cols-3" data-seo-risk-autofill="true" data-seo-risk-options-url="{{ route('seo-risk.google.options') }}">
                    @csrf
                    <div>
                        <label class="app-label">{{ __('messages.seo_risk.property_name') }}</label>
                        <input type="text" name="name" class="app-input" @disabled($isPropertyLimitReached) required />
                    </div>
                    <div>
                        <label class="app-label">{{ __('messages.seo_risk.domain') }}</label>
                        <input type="text" name="domain" class="app-input" placeholder="example.com" data-seo-risk-domain @disabled($isPropertyLimitReached) required />
                    </div>
                    <div>
                        <label class="app-label">{{ __('messages.seo_risk.monitor_mapping') }}</label>
                        <select name="monitored_url_id" class="app-input" data-seo-risk-monitor @disabled($isPropertyLimitReached) required>
                            <option value="">{{ __('messages.seo_risk.choose_monitor') }}</option>
                            @foreach ($monitoredUrls as $monitor)
                                <option value="{{ $monitor->id }}" data-monitor-domain="{{ $extractDomain((string) $monitor->url) }}">{{ $monitor->display_name ?: $monitor->url }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="lg:col-span-3 rounded-lg border border-gray-200 px-3 py-2 dark:border-gray-700">
                        <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            {{ app()->getLocale() === 'pl' ? 'Źródła danych' : 'Data sources' }}
                        </div>
                        <div class="mt-2 flex flex-wrap items-center gap-4">
                            <input type="hidden" name="use_gsc" value="0">
                            <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                                <input type="checkbox" name="use_gsc" value="1" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800" @disabled($isPropertyLimitReached) @checked((bool) old('use_gsc', true))>
                                <span>Google Search Console</span>
                            </label>
                            <input type="hidden" name="use_ga4" value="0">
                            <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                                <input type="checkbox" name="use_ga4" value="1" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800" @disabled($isPropertyLimitReached) @checked((bool) old('use_ga4', true))>
                                <span>Google Analytics 4</span>
                            </label>
                        </div>
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                            {{ app()->getLocale() === 'pl' ? 'Wybierz co najmniej jedno źródło. Pola GSC/GA4 poniżej są wymagane tylko dla zaznaczonych opcji.' : 'Select at least one source. GSC/GA4 fields below are required only for selected options.' }}
                        </p>
                    </div>
                    <div>
                        <label class="app-label">{{ __('messages.seo_risk.gsc_site_url') }}</label>
                        <input type="text" name="gsc_site_url" class="app-input" placeholder="sc-domain:example.com" value="{{ old('gsc_site_url') }}" data-seo-risk-gsc list="seo-risk-gsc-sites" @disabled($isPropertyLimitReached) />
                    </div>
                    <div>
                        <label class="app-label">{{ __('messages.seo_risk.ga4_property_id') }}</label>
                        <input type="text" name="ga4_property_id" class="app-input" placeholder="123456789" value="{{ old('ga4_property_id') }}" list="seo-risk-ga4-properties" @disabled($isPropertyLimitReached) />
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            {{ app()->getLocale() === 'pl' ? 'Identyfikator właściwości GA4 znajdziesz w Google Analytics (Administracja -> Ustawienia usługi).' : 'Find GA4 Property ID in Google Analytics (Admin -> Property settings).' }}
                        </p>
                    </div>
                    <div>
                        <label class="app-label">{{ __('messages.seo_risk.baseline_days') }}</label>
                        <input type="number" min="3" max="30" name="baseline_days" class="app-input" value="{{ (int) config('wids.seo_risk.default_baseline_days', 7) }}" @disabled($isPropertyLimitReached) required />
                    </div>
                    <div>
                        <label class="app-label">{{ __('messages.seo_risk.alert_clicks_drop') }}</label>
                        <input type="number" min="5" max="90" name="alert_clicks_drop_percent" class="app-input" value="{{ (int) config('wids.seo_risk.default_thresholds.clicks_drop_percent', 30) }}" @disabled($isPropertyLimitReached) required />
                    </div>
                    <div>
                        <label class="app-label">{{ __('messages.seo_risk.alert_impressions_drop') }}</label>
                        <input type="number" min="5" max="90" name="alert_impressions_drop_percent" class="app-input" value="{{ (int) config('wids.seo_risk.default_thresholds.impressions_drop_percent', 30) }}" @disabled($isPropertyLimitReached) required />
                    </div>
                    <div>
                        <label class="app-label">{{ __('messages.seo_risk.alert_sessions_drop') }}</label>
                        <input type="number" min="5" max="90" name="alert_sessions_drop_percent" class="app-input" value="{{ (int) config('wids.seo_risk.default_thresholds.sessions_drop_percent', 25) }}" @disabled($isPropertyLimitReached) required />
                    </div>
                    <div class="lg:col-span-3 flex items-center gap-2">
                        <input type="hidden" name="is_enabled" value="0">
                        <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                            <input type="checkbox" name="is_enabled" value="1" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800" @disabled($isPropertyLimitReached) checked>
                            <span>{{ __('messages.seo_risk.property_enabled') }}</span>
                        </label>
                        <span class="text-xs text-gray-500 dark:text-gray-400">
                            {{ app()->getLocale() === 'pl' ? 'Ruch organiczny i sygnały SEO będą analizowane codziennie po synchronizacji.' : 'Organic traffic and SEO signals are analyzed daily after sync.' }}
                        </span>
                        <button type="submit" class="app-button-primary" @disabled($isPropertyLimitReached)>{{ __('messages.actions.create') }}</button>
                    </div>
                </form>
            </section>
            @endif

            <section class="app-card">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('messages.seo_risk.properties_title') }}</h3>
                @unless ($canManage)
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                        {{ app()->getLocale() === 'pl' ? 'Tryb tylko do odczytu. Edycję właściwości SEO wykonuje właściciel przestrzeni roboczej.' : 'Read-only mode. SEO property updates can be managed by workspace owner.' }}
                    </p>
                @endunless
                <div class="mt-4 space-y-4">
                    @forelse ($properties as $property)
                        <div class="rounded-xl border border-gray-200 p-3 dark:border-gray-700">
                            @php
                                $dataSources = $property->seoDataSources();
                            @endphp
                            @if ($canManage)
                                <form method="POST" action="{{ route('seo-risk.properties.update', ['seo_property' => $property->id]) }}">
                                    @csrf
                                    @method('PATCH')
                                    <div class="grid grid-cols-1 gap-3 lg:grid-cols-4">
                                    <div>
                                        <label class="app-label">{{ __('messages.seo_risk.property_name') }}</label>
                                        <input type="text" name="name" class="app-input" value="{{ $property->name }}" required />
                                    </div>
                                    <div>
                                        <label class="app-label">{{ __('messages.seo_risk.domain') }}</label>
                                        <input type="text" name="domain" class="app-input" value="{{ $property->domain }}" required />
                                    </div>
                                    <div>
                                        <label class="app-label">{{ __('messages.seo_risk.monitor_mapping') }}</label>
                                        <select name="monitored_url_id" class="app-input" required>
                                            @foreach ($monitoredUrls as $monitor)
                                                <option value="{{ $monitor->id }}" @selected((int) $property->monitored_url_id === (int) $monitor->id)>{{ $monitor->display_name ?: $monitor->url }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="app-label">{{ __('messages.seo_risk.baseline_days') }}</label>
                                        <input type="number" min="3" max="30" name="baseline_days" class="app-input" value="{{ (int) $property->baseline_days }}" required />
                                    </div>
                                    <div class="lg:col-span-4 rounded-lg border border-gray-200 px-3 py-2 dark:border-gray-700">
                                        <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                            {{ app()->getLocale() === 'pl' ? 'Źródła danych' : 'Data sources' }}
                                        </div>
                                        <div class="mt-2 flex flex-wrap items-center gap-4">
                                            <input type="hidden" name="use_gsc" value="0">
                                            <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                                                <input type="checkbox" name="use_gsc" value="1" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800" @checked((bool) $dataSources['gsc'])>
                                                <span>Google Search Console</span>
                                            </label>
                                            <input type="hidden" name="use_ga4" value="0">
                                            <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                                                <input type="checkbox" name="use_ga4" value="1" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800" @checked((bool) $dataSources['ga4'])>
                                                <span>Google Analytics 4</span>
                                            </label>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="app-label">{{ __('messages.seo_risk.gsc_site_url') }}</label>
                                        <input type="text" name="gsc_site_url" class="app-input" value="{{ $property->gsc_site_url }}" list="seo-risk-gsc-sites" />
                                    </div>
                                    <div>
                                        <label class="app-label">{{ __('messages.seo_risk.ga4_property_id') }}</label>
                                        <input type="text" name="ga4_property_id" class="app-input" value="{{ $property->ga4_property_id }}" list="seo-risk-ga4-properties" />
                                    </div>
                                    <div>
                                        <label class="app-label">{{ __('messages.seo_risk.alert_clicks_drop') }}</label>
                                        <input type="number" min="5" max="90" name="alert_clicks_drop_percent" class="app-input" value="{{ (int) $property->alert_clicks_drop_percent }}" required />
                                    </div>
                                    <div>
                                        <label class="app-label">{{ __('messages.seo_risk.alert_impressions_drop') }}</label>
                                        <input type="number" min="5" max="90" name="alert_impressions_drop_percent" class="app-input" value="{{ (int) $property->alert_impressions_drop_percent }}" required />
                                    </div>
                                    <div>
                                        <label class="app-label">{{ __('messages.seo_risk.alert_sessions_drop') }}</label>
                                        <input type="number" min="5" max="90" name="alert_sessions_drop_percent" class="app-input" value="{{ (int) $property->alert_sessions_drop_percent }}" required />
                                    </div>
                                    </div>
                                    <div class="mt-3 flex flex-wrap items-center gap-2">
                                        <input type="hidden" name="is_enabled" value="0">
                                        <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                                            <input type="checkbox" name="is_enabled" value="1" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800" @checked((bool) $property->is_enabled)>
                                            <span>{{ __('messages.seo_risk.property_enabled') }}</span>
                                        </label>
                                        <button type="submit" class="app-button-secondary">{{ __('messages.actions.save') }}</button>
                                    </div>
                                </form>
                                <div class="mt-2">
                                    <form method="POST" action="{{ route('seo-risk.properties.destroy', ['seo_property' => $property->id]) }}" data-confirm-submit="{{ __('messages.seo_risk.delete_property_confirm') }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="app-button-danger">{{ __('messages.actions.delete') }}</button>
                                    </form>
                                </div>
                            @else
                                <div class="grid grid-cols-1 gap-3 lg:grid-cols-4 text-sm">
                                    <div>
                                        <div class="app-label">{{ __('messages.seo_risk.property_name') }}</div>
                                        <div class="text-gray-900 dark:text-gray-100">{{ $property->name }}</div>
                                    </div>
                                    <div>
                                        <div class="app-label">{{ __('messages.seo_risk.domain') }}</div>
                                        <div class="text-gray-900 dark:text-gray-100">{{ $property->domain }}</div>
                                    </div>
                                    <div>
                                        <div class="app-label">{{ __('messages.seo_risk.monitor_mapping') }}</div>
                                        <div class="text-gray-900 dark:text-gray-100">{{ optional($property->monitoredUrl)->display_name ?: optional($property->monitoredUrl)->url ?: '-' }}</div>
                                    </div>
                                    <div>
                                        <div class="app-label">{{ __('messages.seo_risk.baseline_days') }}</div>
                                        <div class="text-gray-900 dark:text-gray-100">{{ (int) $property->baseline_days }}</div>
                                    </div>
                                    <div>
                                        <div class="app-label">{{ app()->getLocale() === 'pl' ? 'Źródła danych' : 'Data sources' }}</div>
                                        <div class="text-gray-900 dark:text-gray-100">
                                            @if ((bool) $dataSources['gsc'] && (bool) $dataSources['ga4'])
                                                GSC + GA4
                                            @elseif ((bool) $dataSources['gsc'])
                                                GSC
                                            @elseif ((bool) $dataSources['ga4'])
                                                GA4
                                            @else
                                                -
                                            @endif
                                        </div>
                                    </div>
                                    <div>
                                        <div class="app-label">{{ __('messages.seo_risk.gsc_site_url') }}</div>
                                        <div class="text-gray-900 dark:text-gray-100">{{ (bool) $dataSources['gsc'] ? $property->gsc_site_url : '-' }}</div>
                                    </div>
                                    <div>
                                        <div class="app-label">{{ __('messages.seo_risk.ga4_property_id') }}</div>
                                        <div class="text-gray-900 dark:text-gray-100">{{ (bool) $dataSources['ga4'] ? $property->ga4_property_id : '-' }}</div>
                                    </div>
                                    <div>
                                        <div class="app-label">{{ __('messages.seo_risk.alert_clicks_drop') }}</div>
                                        <div class="text-gray-900 dark:text-gray-100">{{ (int) $property->alert_clicks_drop_percent }}%</div>
                                    </div>
                                    <div>
                                        <div class="app-label">{{ __('messages.seo_risk.alert_impressions_drop') }}</div>
                                        <div class="text-gray-900 dark:text-gray-100">{{ (int) $property->alert_impressions_drop_percent }}%</div>
                                    </div>
                                    <div>
                                        <div class="app-label">{{ __('messages.seo_risk.alert_sessions_drop') }}</div>
                                        <div class="text-gray-900 dark:text-gray-100">{{ (int) $property->alert_sessions_drop_percent }}%</div>
                                    </div>
                                    <div>
                                        <div class="app-label">{{ __('messages.seo_risk.property_enabled') }}</div>
                                        <div class="text-gray-900 dark:text-gray-100">{{ (bool) $property->is_enabled ? __('messages.actions.yes') : __('messages.actions.no') }}</div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @empty
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('messages.seo_risk.no_properties') }}</p>
                    @endforelse
                </div>
            </section>
            <datalist id="seo-risk-gsc-sites"></datalist>
            <datalist id="seo-risk-ga4-properties"></datalist>
        </div>
    </div>
</x-app-layout>

