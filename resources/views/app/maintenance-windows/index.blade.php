<x-app-layout>
    <x-slot name="header">
        @php($isOwner = auth()->user()?->role !== 'member')
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl leading-tight text-gray-900 dark:text-gray-100">
                {{ __('Maintenance windows') }}
            </h2>
            <a href="{{ route('monitored-urls.index') }}" class="app-link">
                &larr; {{ __('messages.monitored_urls.title') }}
            </a>
        </div>
    </x-slot>

    <div class="app-page">
        <div class="app-container">
            @if ($isOwner)
                <div class="app-card">
                    <div class="font-medium text-gray-900 dark:text-gray-100">{{ __('Create') }}</div>
                    <form method="POST" action="{{ route('maintenance-windows.store') }}" class="mt-3 flex flex-wrap gap-3 items-end">
                        @csrf

                        <div class="w-full sm:w-52">
                            <label class="app-label">{{ __('Scope') }}</label>
                            <select name="scope" class="app-input">
                                @php($scope = old('scope', 'all'))
                                <option value="all" {{ $scope === 'all' ? 'selected' : '' }}>{{ __('All monitors') }}</option>
                                <option value="monitor" {{ $scope === 'monitor' ? 'selected' : '' }}>{{ __('Single monitor') }}</option>
                                <option value="tag" {{ $scope === 'tag' ? 'selected' : '' }}>{{ __('Tag') }}</option>
                            </select>
                        </div>

                        <div class="w-full sm:w-72">
                            <label class="app-label">{{ __('Monitor (when scope=monitor)') }}</label>
                            <select name="monitored_url_id" class="app-input">
                                <option value="">-</option>
                                @foreach ($monitoredUrls as $mu)
                                    <option value="{{ $mu->id }}" {{ (string) old('monitored_url_id') === (string) $mu->id ? 'selected' : '' }}>
                                        {{ $mu->display_name ?? $mu->url }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="w-full sm:w-56">
                            <label class="app-label">{{ __('Tag (when scope=tag)') }}</label>
                            <input name="tag" value="{{ old('tag') }}" class="app-input" placeholder="{{ __('e.g. deploy') }}" list="tag-list" />
                            <datalist id="tag-list">
                                @foreach ($tags as $tag)
                                    <option value="{{ $tag }}"></option>
                                @endforeach
                            </datalist>
                        </div>

                        <div class="w-full sm:w-56">
                            <label class="app-label">{{ __('Start') }}</label>
                            <input type="datetime-local" name="starts_at" value="{{ old('starts_at', now()->format('Y-m-d\\TH:i')) }}" class="app-input" />
                        </div>

                        <div class="w-full sm:w-56">
                            <label class="app-label">{{ __('End') }}</label>
                            <input type="datetime-local" name="ends_at" value="{{ old('ends_at', now()->addHour()->format('Y-m-d\\TH:i')) }}" class="app-input" />
                        </div>

                        <div class="w-full sm:w-72">
                            <label class="app-label">{{ __('Note') }}</label>
                            <input name="note" value="{{ old('note') }}" class="app-input" placeholder="{{ __('optional') }}" />
                        </div>

                        <div>
                            <button class="app-button-primary" type="submit">
                                Create
                            </button>
                        </div>
                    </form>
                </div>
            @endif

            <div class="app-table overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-gray-600 dark:bg-gray-900/70 dark:text-gray-300">
                        <tr>
                                <th class="text-left px-4 py-2">{{ __('Target') }}</th>
                                <th class="text-left px-4 py-2">{{ __('Start') }}</th>
                                <th class="text-left px-4 py-2">{{ __('End') }}</th>
                                <th class="text-left px-4 py-2">{{ __('Status') }}</th>
                                <th class="text-left px-4 py-2">{{ __('Note') }}</th>
                                <th class="text-left px-4 py-2">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                    <tbody>
                        @php($now = now())
                        @forelse ($windows as $w)
                            @php($isActive = $w->starts_at && $w->ends_at && $w->starts_at->lte($now) && $w->ends_at->gte($now))
                            @php($isUpcoming = $w->starts_at && $w->starts_at->gt($now))
                            <tr class="border-t">
                                <td class="px-4 py-2">
                                    @if ($w->monitored_url_id)
                                        <span class="app-chip">{{ __('monitor') }}</span>
                                        <div class="mt-1">
                                            <a class="hover:underline text-gray-900" href="{{ route('monitored-urls.show', $w->monitoredUrl) }}">
                                                {{ $w->monitoredUrl?->display_name ?? $w->monitoredUrl?->url ?? ('#'.$w->monitored_url_id) }}
                                            </a>
                                        </div>
                                    @elseif ($w->tag)
                                        <span class="app-chip">{{ __('tag') }}</span>
                                        <div class="mt-1 font-mono text-gray-900 dark:text-gray-100">{{ $w->tag }}</div>
                                    @else
                                        <span class="app-chip">{{ __('all') }}</span>
                                        <div class="mt-1 text-gray-900 dark:text-gray-100">{{ __('All monitors') }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-2">{{ $w->starts_at?->toDateTimeString() ?? '-' }}</td>
                                <td class="px-4 py-2">{{ $w->ends_at?->toDateTimeString() ?? '-' }}</td>
                                <td class="px-4 py-2">
                                    @if ($isActive)
                                        <span class="app-chip-warning">{{ __('ACTIVE') }}</span>
                                    @elseif ($isUpcoming)
                                        <span class="app-chip">{{ __('UPCOMING') }}</span>
                                    @else
                                        <span class="app-chip">{{ __('PAST') }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $w->note ?? '-' }}</td>
                                <td class="px-4 py-2">
                                    @if ($isOwner)
                                        <form method="POST" action="{{ route('maintenance-windows.destroy', $w) }}" data-confirm-submit="{{ __('Delete this maintenance window?') }}">
                                            @csrf
                                            @method('DELETE')
                                            <button class="text-red-700 hover:text-red-900" type="submit">{{ __('messages.actions.delete') }}</button>
                                        </form>
                                    @else
                                        <span class="text-gray-500 dark:text-gray-400 text-xs">{{ __('Read-only') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr class="border-t">
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-400" colspan="6">{{ __('No maintenance windows.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div>
                {{ $windows->links() }}
            </div>
        </div>
    </div>
</x-app-layout>

