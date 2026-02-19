<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl leading-tight text-gray-900 dark:text-gray-100">
                {{ __('messages.monitored_urls.title') }} — {{ __('messages.actions.import_export') }}
            </h2>
            <a href="{{ route('monitored-urls.index') }}" class="app-link">
                &larr; {{ __('messages.monitored_urls.title') }}
            </a>
        </div>
    </x-slot>

    <div class="app-page">
        <div class="app-container-4xl">
            <div class="app-card">
                <div class="text-sm text-gray-700 dark:text-gray-300">
                    {{ __('Plan tier') }}: <span class="font-medium text-gray-900 dark:text-gray-100">{{ strtoupper((string) ($planTier ?? 'lite')) }}</span>
                    <span class="mx-1 text-gray-400">|</span>
                    {{ __('Current monitors') }}: <span class="font-medium text-gray-900 dark:text-gray-100">{{ $count }}</span>
                    @if ($limit > 0)
                        <span class="text-gray-500 dark:text-gray-400">({{ __('limit') }}: {{ $limit }})</span>
                    @endif
                </div>
                <div class="app-help">{{ __('Need more capacity? Upgrade plan to increase monitor, storage and retention quotas.') }}</div>
            </div>

            <div class="app-card space-y-3">
                <div>
                    <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ __('Export') }}</div>
                    <div class="app-help">{{ __('Downloads JSON with monitor configuration (including tags).') }}</div>
                </div>
                <div>
                    <a href="{{ route('monitored-urls.bulk.export') }}" class="app-button-primary">
                        {{ __('Download JSON') }}
                    </a>
                </div>
            </div>

            <div class="app-card space-y-3">
                <div>
                    <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ __('Import') }}</div>
                    <div class="app-help">{{ __('Accepts either full export payload ({ "monitors": [...] }) or a raw array of monitors.') }}</div>
                </div>

                <form method="POST" action="{{ route('monitored-urls.bulk.import') }}" enctype="multipart/form-data" class="space-y-3">
                    @csrf

                    <div>
                        <label class="app-label">{{ __('JSON file') }}</label>
                        <input type="file" name="json_file" accept=".json,application/json,text/plain" class="block w-full text-sm" />
                    </div>

                    <div>
                        <label class="app-label">{{ __('Or paste JSON') }}</label>
                        <textarea name="json" rows="10" class="app-input font-mono" placeholder='{"monitors":[{"url":"https://example.com","frequency_minutes":5,"alert_after_failures":2,"alert_cooldown_minutes":30,"tags":["prod"]}] }'>{{ old('json') }}</textarea>
                    </div>

                    <div>
                        <button type="submit" class="app-button-primary">
                            {{ __('Import') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>

