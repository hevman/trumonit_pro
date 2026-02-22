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
                        <label class="mb-1 block text-sm font-medium text-gray-900 dark:text-gray-100" for="template">{{ __('messages.onboarding.template_label') }}</label>
                        <select id="template" name="template" class="app-input" required>
                            <option value="">{{ __('messages.onboarding.template_placeholder') }}</option>
                            @foreach ($monitorTemplates as $template)
                                <option value="{{ $template['key'] }}" {{ old('template') === $template['key'] ? 'selected' : '' }}>
                                    {{ $template['name'] }}
                                </option>
                            @endforeach
                        </select>
                        <div class="mt-2 text-xs text-gray-600 dark:text-gray-400">
                            {{ __('messages.onboarding.template_pick_help') }}
                        </div>
                        <div id="template-suggestion" class="mt-2 hidden text-xs text-gray-600 dark:text-gray-400"></div>
                        <div class="mt-2 text-xs text-gray-600 dark:text-gray-400">
                            <div class="mb-1 font-medium text-gray-700 dark:text-gray-300">{{ __('messages.onboarding.template_help_title') }}</div>
                            @foreach ($monitorTemplates as $template)
                                <div><span class="font-medium text-gray-800 dark:text-gray-200">{{ $template['name'] }}</span>: {{ $template['description'] }}</div>
                            @endforeach
                        </div>
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

            if (!url || !template) {
                return;
            }

            function guessTemplateKey(raw) {
                if (typeof raw !== 'string') {
                    return null;
                }

                var value = raw.trim().toLowerCase();
                if (!value) {
                    return null;
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

                return 'status_page';
            }

            function maybeApplySuggestion() {
                // Only auto-suggest if user hasn't picked a template yet.
                if (template.value) {
                    if (suggestion) {
                        suggestion.classList.add('hidden');
                        suggestion.textContent = '';
                    }
                    return;
                }

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

                for (var i = 0; i < template.options.length; i++) {
                    if (template.options[i].value === key) {
                        template.value = key;
                        break;
                    }
                }

                if (suggestion && matchedLabel) {
                    // Plain text only (no HTML).
                    suggestion.textContent = ({{ json_encode(app()->getLocale() === 'pl' ? 'Sugerowany szablon: ' : 'Suggested template: ') }} + matchedLabel + '.');
                    suggestion.classList.remove('hidden');
                }
            }

            url.addEventListener('change', maybeApplySuggestion);
            url.addEventListener('blur', maybeApplySuggestion);

            template.addEventListener('change', function () {
                if (!suggestion) {
                    return;
                }
                suggestion.classList.add('hidden');
                suggestion.textContent = '';
            });
        });
    </script>
</x-app-layout>
