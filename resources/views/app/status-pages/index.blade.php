<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('messages.status_pages.title') }}
            </h2>
            @if ($isOwner)
                <a href="{{ route('status-pages.create') }}" class="app-button-primary">
                    {{ __('messages.actions.create') }}
                </a>
            @endif
        </div>
    </x-slot>

    <div class="app-page">
        <div class="app-container">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
                <section class="app-card">
                    <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Total pages') }}</div>
                    <div class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $summary['total'] ?? 0 }}</div>
                </section>
                <section class="app-card">
                    <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Public') }}</div>
                    <div class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $summary['public'] ?? 0 }}</div>
                </section>
                <section class="app-card">
                    <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Private') }}</div>
                    <div class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $summary['private'] ?? 0 }}</div>
                </section>
                <section class="app-card">
                    <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Custom domains') }}</div>
                    <div class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $summary['custom_domain'] ?? 0 }}</div>
                </section>
                <section class="app-card">
                    <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Monitors exposed') }}</div>
                    <div class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $summary['monitors_exposed'] ?? 0 }}</div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        {{ __('Avg / page') }}:
                        @if (($summary['avg_monitors_per_page'] ?? null) !== null)
                            {{ number_format((float) $summary['avg_monitors_per_page'], 1) }}
                        @else
                            -
                        @endif
                    </div>
                </section>
            </div>

            <div class="app-table">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-gray-600 dark:bg-gray-900/70 dark:text-gray-300">
                            <tr>
                                <th class="text-left px-4 py-2">{{ __('messages.status_pages.name') }}</th>
                                <th class="text-left px-4 py-2">{{ __('messages.status_pages.slug') }}</th>
                                <th class="text-left px-4 py-2">{{ __('messages.status_pages.custom_domain') }}</th>
                                <th class="text-left px-4 py-2">{{ __('messages.status_pages.is_public') }}</th>
                                <th class="text-left px-4 py-2">{{ __('Cache TTL') }}</th>
                                <th class="text-left px-4 py-2">{{ __('messages.status_pages.monitors') }}</th>
                                <th class="text-left px-4 py-2">{{ __('Link') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($statusPages as $statusPage)
                                <tr class="border-t">
                                    <td class="px-4 py-2">
                                        <a class="text-gray-900 dark:text-gray-100 hover:underline" href="{{ route('status-pages.show', $statusPage) }}">
                                            {{ $statusPage->title }}
                                        </a>
                                    </td>
                                    <td class="px-4 py-2 font-mono text-xs">{{ $statusPage->slug }}</td>
                                    <td class="px-4 py-2 font-mono text-xs">{{ $statusPage->custom_domain ?: '-' }}</td>
                                    <td class="px-4 py-2">
                                        @if ($statusPage->is_public)
                                            <span class="app-chip-success">{{ __('YES') }}</span>
                                        @else
                                            <span class="app-chip">{{ __('NO') }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2">{{ $statusPage->cache_ttl_seconds ?? 30 }}s</td>
                                    <td class="px-4 py-2">{{ $statusPage->monitored_urls_count ?? 0 }}</td>
                                    <td class="px-4 py-2">
                                        @if ($statusPage->is_public)
                                            <a class="text-gray-900 dark:text-gray-100 hover:underline" href="{{ $statusPage->custom_domain ? 'https://'.$statusPage->custom_domain : route('status-pages.public.show', $statusPage->slug) }}" target="_blank" rel="noreferrer">
                                                {{ __('messages.status_pages.public_url') }}
                                            </a>
                                        @else
                                            <span class="text-gray-500 dark:text-gray-400">-</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr class="border-t">
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-400" colspan="7">{{ __('No status pages yet.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

