<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Saved searches') }}
            </h2>
            <a href="{{ route('dashboard') }}" class="app-link">
                {{ __('messages.nav.dashboard') }}
            </a>
        </div>
    </x-slot>

    <div class="app-page">
        <div class="app-container">
            <div class="app-card">
                <form method="GET" action="{{ route('saved-views.index') }}" class="flex flex-wrap gap-3 items-end">
                    <div class="w-full sm:w-64">
                        <label class="app-label">{{ __('Scope') }}</label>
                        <select name="scope" class="app-input">
                            <option value="">{{ __('messages.actions.all') }}</option>
                            <option value="incidents" {{ $scope === 'incidents' ? 'selected' : '' }}>{{ __('Incidents') }}</option>
                            <option value="monitored_urls" {{ $scope === 'monitored_urls' ? 'selected' : '' }}>{{ __('Monitored URLs') }}</option>
                        </select>
                    </div>

                    <div class="flex gap-2">
                        <button class="app-button-primary" type="submit">
                            {{ __('messages.actions.filter') }}
                        </button>
                        <a class="app-link px-3 py-2" href="{{ route('saved-views.index') }}">
                            {{ __('messages.actions.reset') }}
                        </a>
                    </div>
                </form>
            </div>

            <div class="app-table overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-gray-600 dark:bg-gray-900/70 dark:text-gray-300">
                        <tr>
                            <th class="text-left px-4 py-2">{{ __('Scope') }}</th>
                            <th class="text-left px-4 py-2">{{ __('Name') }}</th>
                            <th class="text-left px-4 py-2">{{ __('Filters') }}</th>
                            <th class="text-left px-4 py-2">{{ __('owner') }}</th>
                            <th class="text-left px-4 py-2">{{ __('Updated') }}</th>
                            <th class="text-left px-4 py-2">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($savedViews as $savedView)
                            @php($filters = is_array($savedView->filters) ? $savedView->filters : [])
                            @php($targetRoute = $savedView->scope === 'incidents' ? route('incidents.index', $filters) : route('monitored-urls.index', $filters))
                            <tr class="border-t">
                                <td class="px-4 py-2">
                                    <span class="app-chip">
                                        {{ $savedView->scope === 'incidents' ? __('Incidents') : __('Monitored URLs') }}
                                    </span>
                                </td>
                                <td class="px-4 py-2">
                                    <form method="POST" action="{{ route('saved-views.update', $savedView) }}" class="flex items-center gap-2">
                                        @csrf
                                        @method('PATCH')
                                        <input type="text" name="name" value="{{ $savedView->name }}" class="app-input w-64" />
                                        <button class="app-link" type="submit">{{ __('messages.actions.save') }}</button>
                                    </form>
                                </td>
                                <td class="px-4 py-2">
                                    @if ($filters === [])
                                        <span class="text-xs text-gray-500 dark:text-gray-400">-</span>
                                    @else
                                        <div class="text-xs text-gray-700 dark:text-gray-300 font-mono break-all">{{ json_encode($filters, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-2">
                                    <div class="text-sm text-gray-800 dark:text-gray-200">{{ $savedView->createdByUser?->name ?? __('Unknown') }}</div>
                                    <div class="text-xs text-gray-600 dark:text-gray-400">{{ $savedView->createdByUser?->email ?? '-' }}</div>
                                </td>
                                <td class="px-4 py-2 text-xs text-gray-600 dark:text-gray-400">{{ $savedView->updated_at?->toDateTimeString() }}</td>
                                <td class="px-4 py-2">
                                    <div class="flex items-center gap-3">
                                        <a href="{{ $targetRoute }}" class="text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-gray-100">{{ __('Open') }}</a>
                                        <form method="POST" action="{{ route('saved-views.destroy', $savedView) }}" data-confirm-submit="{{ __('messages.saved_views.delete_confirm') }}">
                                            @csrf
                                            @method('DELETE')
                                            <button class="text-red-700 hover:text-red-900" type="submit">{{ __('messages.actions.delete') }}</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr class="border-t">
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-400" colspan="6">{{ __('No saved searches.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div>
                {{ $savedViews->links() }}
            </div>
        </div>
    </div>
</x-app-layout>

