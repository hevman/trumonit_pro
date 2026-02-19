<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('messages.actions.edit') }}: {{ $statusPage->title }}
            </h2>
            <a href="{{ route('status-pages.show', $statusPage) }}" class="app-link">
                {{ __('messages.actions.cancel') }}
            </a>
        </div>
    </x-slot>

    <div class="app-page">
        <div class="app-container-4xl">
            @if ($statusPage->is_public)
                <div class="app-card">
                    <div class="text-sm text-gray-600 dark:text-gray-400 mb-1">{{ __('messages.status_pages.public_url') }}</div>
                    <div class="font-mono text-sm break-all">
                        <a class="text-gray-900 dark:text-gray-100 hover:underline" href="{{ route('status-pages.public.show', $statusPage->slug) }}" target="_blank" rel="noreferrer">
                            {{ route('status-pages.public.show', $statusPage->slug) }}
                        </a>
                    </div>
                    @if ($statusPage->custom_domain)
                        <div class="font-mono text-sm break-all mt-2">
                            <a class="text-gray-900 dark:text-gray-100 hover:underline" href="{{ 'https://'.$statusPage->custom_domain }}" target="_blank" rel="noreferrer">
                                {{ 'https://'.$statusPage->custom_domain }}
                            </a>
                        </div>
                    @endif
                </div>
            @endif

            <div class="app-card app-card-lg">
                <form method="POST" action="{{ route('status-pages.update', $statusPage) }}" class="space-y-4">
                    @csrf
                    @method('PUT')

                    <div>
                        <label class="block text-sm font-medium mb-1" for="title">{{ __('messages.status_pages.name') }}</label>
                        <input id="title" name="title" type="text" value="{{ old('title', $statusPage->title) }}" required class="app-input" />
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-1" for="slug">{{ __('messages.status_pages.slug') }}</label>
                        <input id="slug" name="slug" type="text" value="{{ old('slug', $statusPage->slug) }}" required class="app-input font-mono" />
                        <div class="app-help">{{ __('Allowed') }}: <span class="font-mono">a-z0-9-</span>.</div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-1" for="custom_domain">{{ __('messages.status_pages.custom_domain') }}</label>
                        <input id="custom_domain" name="custom_domain" type="text" value="{{ old('custom_domain', $statusPage->custom_domain) }}" class="app-input font-mono" placeholder="status.example.com" />
                        <div class="app-help">{{ __('Hostname only (no scheme/path). Root path on this domain will serve the status page.') }}</div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-1" for="cache_ttl_seconds">{{ __('messages.status_pages.cache_ttl_seconds') }}</label>
                        <input id="cache_ttl_seconds" name="cache_ttl_seconds" type="number" min="5" max="600" value="{{ old('cache_ttl_seconds', $statusPage->cache_ttl_seconds ?? 30) }}" class="app-input" />
                        <div class="app-help">{{ __('Controls `Cache-Control` headers for public status responses.') }}</div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-1" for="description">{{ __('messages.status_pages.description') }}</label>
                        <textarea id="description" name="description" rows="3" class="app-input">{{ old('description', $statusPage->description) }}</textarea>
                    </div>

                    <div class="flex items-center gap-2">
                        <input id="is_public" type="checkbox" name="is_public" value="1" class="app-checkbox" {{ old('is_public', $statusPage->is_public) ? 'checked' : '' }} />
                        <label class="text-sm text-gray-700 dark:text-gray-300" for="is_public">{{ __('messages.status_pages.is_public') }}</label>
                    </div>

                    <div>
                        <div class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-2">{{ __('messages.status_pages.monitors') }}</div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                            @foreach ($monitoredUrls as $monitoredUrl)
                                <label class="flex items-start gap-2 rounded border px-3 py-2 hover:bg-gray-50 dark:hover:bg-gray-800/60">
                                    <input type="checkbox" name="monitored_url_ids[]" value="{{ $monitoredUrl->id }}" class="mt-1 app-checkbox"
                                        {{ in_array($monitoredUrl->id, old('monitored_url_ids', $selectedIds)) ? 'checked' : '' }} />
                                    <span class="text-sm">
                                        <div class="font-medium text-gray-900 dark:text-gray-100">{{ $monitoredUrl->display_name ?? $monitoredUrl->url }}</div>
                                        <div class="text-xs text-gray-600 dark:text-gray-400 break-all">{{ $monitoredUrl->url }}</div>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="pt-2">
                        <button type="submit" class="app-button-primary">
                            {{ __('messages.actions.update') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>

