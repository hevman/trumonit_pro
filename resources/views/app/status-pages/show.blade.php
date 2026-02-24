<x-app-layout>
    <x-slot name="header">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl leading-tight text-gray-900 dark:text-gray-100">
                    {{ $statusPage->title }}
                </h2>
                <div class="text-sm text-gray-600 dark:text-gray-400 font-mono">{{ $statusPage->slug }}</div>
            </div>

            <div class="flex items-center gap-2">
                @if ($statusPage->is_public)
                    <a href="{{ route('status-pages.public.show', $statusPage->slug) }}" target="_blank" rel="noreferrer"
                        class="app-button-secondary">
                        {{ __('messages.status_pages.public_url') }}
                    </a>
                @endif

                @if ($isOwner)
                    <a href="{{ route('status-pages.edit', $statusPage) }}" class="app-link">
                        {{ __('messages.actions.edit') }}
                    </a>

                    <form method="POST" action="{{ route('status-pages.destroy', $statusPage) }}" data-confirm-submit="{{ __('Delete this status page?') }}">
                        @csrf
                        @method('DELETE')
                        <button class="text-sm text-red-700 hover:text-red-900" type="submit">
                            {{ __('messages.actions.delete') }}
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="app-page">
        <div class="app-container-5xl">
            <div class="app-card">
                <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                    <div>
                        <div class="text-sm text-gray-600 dark:text-gray-400">{{ __('messages.status_pages.is_public') }}</div>
                        <div class="font-medium">{{ $statusPage->is_public ? __('yes') : __('no') }}</div>
                    </div>
                    <div>
                        <div class="text-sm text-gray-600 dark:text-gray-400">{{ __('messages.status_pages.cache_ttl_seconds') }}</div>
                        <div class="font-medium">{{ $statusPage->cache_ttl_seconds ?? 30 }}</div>
                    </div>
                    <div class="md:col-span-3">
                        <div class="text-sm text-gray-600 dark:text-gray-400">{{ __('messages.status_pages.custom_domain') }}</div>
                        <div class="font-mono text-sm break-all">{{ $statusPage->custom_domain ?: '-' }}</div>
                    </div>
                    <div class="md:col-span-2">
                        <div class="text-sm text-gray-600 dark:text-gray-400">{{ __('messages.status_pages.description') }}</div>
                        <div class="text-gray-900 dark:text-gray-100">{{ $statusPage->description ?: '-' }}</div>
                    </div>
                </div>

                @if ($statusPage->is_public)
                    <div class="mt-3 pt-3 border-t space-y-1">
                        <div class="text-sm text-gray-600 dark:text-gray-400">{{ __('messages.status_pages.public_url') }}</div>
                        <div class="font-mono text-sm break-all">
                            <a class="text-gray-900 dark:text-gray-100 hover:underline" href="{{ route('status-pages.public.show', $statusPage->slug) }}" target="_blank" rel="noreferrer">
                                {{ route('status-pages.public.show', $statusPage->slug) }}
                            </a>
                        </div>
                        @if ($statusPage->custom_domain)
                            <div class="font-mono text-sm break-all">
                                <a class="text-gray-900 dark:text-gray-100 hover:underline" href="{{ 'https://'.$statusPage->custom_domain }}" target="_blank" rel="noreferrer">
                                    {{ 'https://'.$statusPage->custom_domain }}
                                </a>
                            </div>
                        @endif
                    </div>
                @endif
            </div>

            <div class="app-table">
                <div class="px-4 py-3 border-b font-medium">{{ __('messages.status_pages.monitors') }}</div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-gray-600 dark:bg-gray-900/70 dark:text-gray-300">
                            <tr>
                                <th class="text-left px-4 py-2">{{ __('messages.monitored_urls.url') }}</th>
                                <th class="text-left px-4 py-2">{{ __('messages.monitored_urls.display_name') }}</th>
                                <th class="text-left px-4 py-2">{{ __('messages.monitored_urls.is_active') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($statusPage->monitoredUrls as $monitoredUrl)
                                <tr class="border-t">
                                    <td class="px-4 py-2 break-all">
                                        <a class="text-gray-900 dark:text-gray-100 hover:underline" href="{{ route('monitored-urls.show', $monitoredUrl) }}">
                                            {{ $monitoredUrl->url }}
                                        </a>
                                    </td>
                                    <td class="px-4 py-2">{{ $monitoredUrl->display_name ?? '-' }}</td>
                                    <td class="px-4 py-2">{{ $monitoredUrl->is_active ? __('yes') : __('no') }}</td>
                                </tr>
                            @empty
                                <tr class="border-t">
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-400" colspan="3">{{ __('No monitors selected.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div>
                <a href="{{ route('status-pages.index') }}" class="app-link">
                    &larr; {{ __('messages.status_pages.title') }}
                </a>
            </div>
        </div>
    </div>
</x-app-layout>

