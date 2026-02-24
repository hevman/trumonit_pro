@php
    $user = auth()->user();
    $isOwner = ($user?->role ?? null) !== 'member';
@endphp

@if ($isOwner)
    @php
        $templates = app(\App\Services\MonitorTemplateService::class)->frontendTemplates();
        $defaultTemplate = 'website_uptime';
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

                if (!url || !template) return;

                var userPickedTemplate = false;

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
                    setSuggestionText('');
                });

                // Ensure defaults.
                if (!template.value) template.value = {{ json_encode($defaultTemplate) }};
            });
        </script>
    </x-modal>
@endif
