<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('messages.status_pages.new') }}
            </h2>
            <a href="{{ route('status-pages.index') }}" class="app-link">
                {{ __('messages.actions.cancel') }}
            </a>
        </div>
    </x-slot>

    <div class="app-page">
        <div class="app-container-4xl">
            <div class="app-card app-card-lg">
                <form method="POST" action="{{ route('status-pages.store') }}" class="space-y-4">
                    @csrf

                    <div>
                        <label class="block text-sm font-medium mb-1" for="title">{{ __('messages.status_pages.name') }}</label>
                        <input id="title" name="title" type="text" value="{{ old('title') }}" required class="app-input" />
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-1" for="slug">{{ __('messages.status_pages.slug') }}</label>
                        <input id="slug" name="slug" type="text" value="{{ old('slug') }}" class="app-input font-mono" placeholder="my-status" />
                        <div class="app-help">{{ __('Optional. Allowed') }}: <span class="font-mono">a-z0-9-</span>. {{ __('If empty, we generate one.') }}</div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-1" for="custom_domain">{{ __('messages.status_pages.custom_domain') }}</label>
                        <input id="custom_domain" name="custom_domain" type="text" value="{{ old('custom_domain') }}" class="app-input font-mono" placeholder="status.example.com" />
                        <div class="app-help">{{ __('Optional. Use hostname only (no scheme/path). Root path on this domain will serve the status page.') }}</div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-1" for="cache_ttl_seconds">{{ __('messages.status_pages.cache_ttl_seconds') }}</label>
                        <input id="cache_ttl_seconds" name="cache_ttl_seconds" type="number" min="5" max="600" value="{{ old('cache_ttl_seconds', 30) }}" class="app-input" />
                        <div class="app-help">{{ __('Controls `Cache-Control` headers for public status responses.') }}</div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-1" for="description">{{ __('messages.status_pages.description') }}</label>
                        <textarea id="description" name="description" rows="3" class="app-input">{{ old('description') }}</textarea>
                    </div>

                    <div class="flex items-center gap-2">
                        <input id="is_public" type="checkbox" name="is_public" value="1" class="app-checkbox" {{ old('is_public') ? 'checked' : '' }} />
                        <label class="text-sm text-gray-700 dark:text-gray-300" for="is_public">{{ __('messages.status_pages.is_public') }}</label>
                    </div>

                    <div>
                        <div class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-2">{{ __('messages.status_pages.monitors') }}</div>
                        <div class="text-xs text-gray-600 dark:text-gray-400 mb-3">{{ __('Select which monitors are visible on this page.') }}</div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                            @forelse ($monitoredUrls as $monitoredUrl)
                                <label class="flex items-start gap-2 rounded border px-3 py-2 hover:bg-gray-50 dark:hover:bg-gray-800/60">
                                    <input type="checkbox" name="monitored_url_ids[]" value="{{ $monitoredUrl->id }}" class="mt-1 app-checkbox"
                                        {{ in_array($monitoredUrl->id, old('monitored_url_ids', [])) ? 'checked' : '' }} />
                                    <span class="text-sm">
                                        <div class="font-medium text-gray-900 dark:text-gray-100">{{ $monitoredUrl->display_name ?? $monitoredUrl->url }}</div>
                                        <div class="text-xs text-gray-600 dark:text-gray-400 break-all">{{ $monitoredUrl->url }}</div>
                                    </span>
                                </label>
                            @empty
                                <div class="text-sm text-gray-600 dark:text-gray-400">{{ __('No monitors yet. Create a monitor first.') }}</div>
                            @endforelse
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
</x-app-layout>

