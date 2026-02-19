<x-app-layout>
    <x-slot name="header">
        @php($isOwner = auth()->user()?->role !== 'member')
        <div class="flex items-start justify-between gap-4">
            <div class="min-w-0">
                <div class="text-sm text-gray-600 dark:text-gray-400">
                    <a href="{{ route('incidents.index') }}" class="hover:underline">{{ __('messages.incidents.title') }}</a>
                    <span class="mx-1">/</span>
                    <span class="font-mono">#{{ $incident->id }}</span>
                </div>
                <h2 class="font-semibold text-xl leading-tight text-gray-900 dark:text-gray-100 break-words">
                    {{ $incident->summary }}
                </h2>
                <div class="text-sm text-gray-600 dark:text-gray-400">
                    <span class="font-mono">{{ $incident->kind }}</span>
                    <span class="mx-1">&middot;</span>
                    <span>{{ __('messages.incidents.severity') }}: {{ $incident->severity }}</span>
                    <span class="mx-1">&middot;</span>
                    <span>{{ $incident->detected_at?->toDateTimeString() }}</span>
                </div>
            </div>

            @if ($isOwner)
                <form method="POST" action="{{ route('incidents.update', $incident) }}" class="flex items-center gap-2">
                    @csrf
                    @method('PATCH')
                    <select name="status" class="app-input">
                        <option value="open" {{ $incident->status === 'open' ? 'selected' : '' }}>{{ __('messages.incidents.open') }}</option>
                        <option value="ack" {{ $incident->status === 'ack' ? 'selected' : '' }}>{{ __('messages.incidents.ack') }}</option>
                        <option value="resolved" {{ $incident->status === 'resolved' ? 'selected' : '' }}>{{ __('messages.incidents.resolved') }}</option>
                    </select>
                    <button class="app-link" type="submit">{{ __('messages.actions.update') }}</button>
                </form>
            @else
                <div class="text-sm text-gray-600 dark:text-gray-400">{{ __('Status') }}: {{ $incident->status }}</div>
            @endif
        </div>
    </x-slot>

    <div class="app-page">
        <div class="app-container">
            @if ($maintenanceActive)
                <div class="app-card app-card-warning">
                    <div class="text-sm font-medium">{{ __('Maintenance window active') }}</div>
                    <div class="text-xs text-yellow-800 mt-1 dark:text-yellow-200">{{ __('Notifications for this monitor may be suppressed.') }}</div>
                </div>
            @endif

            <div class="app-card space-y-3">
                <div class="font-medium">{{ __('Collaboration') }}</div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                    <div>
                        <div class="text-xs text-gray-600 dark:text-gray-400">{{ __('Assigned') }}</div>
                        @if ($incident->assignedToUser)
                            <div>{{ $incident->assignedToUser->name }} ({{ $incident->assignedToUser->email }})</div>
                        @else
                            <div class="text-gray-500 dark:text-gray-400">{{ __('Unassigned') }}</div>
                        @endif
                    </div>
                    <div>
                        <div class="text-xs text-gray-600 dark:text-gray-400">{{ __('Snoozed until') }}</div>
                        @if ($incident->snoozed_until && $incident->snoozed_until->isFuture())
                            <div class="text-amber-700 dark:text-amber-300">{{ $incident->snoozed_until->toDateTimeString() }}</div>
                        @else
                            <div class="text-gray-500 dark:text-gray-400">{{ __('Not active') }}</div>
                        @endif
                    </div>
                </div>

                @if ($isOwner)
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-3">
                        <form method="POST" action="{{ route('incidents.update', $incident) }}" class="flex items-end gap-2">
                            @csrf
                            @method('PATCH')
                            <div class="flex-1">
                                <label class="app-label">{{ __('Assignee') }}</label>
                                <select name="assigned_user_id" class="app-input">
                                    <option value="">{{ __('Unassigned') }}</option>
                                    @foreach ($tenantUsers as $tenantUser)
                                        <option value="{{ $tenantUser->id }}" {{ (string) ($incident->assigned_user_id ?? '') === (string) $tenantUser->id ? 'selected' : '' }}>
                                            {{ $tenantUser->name }} ({{ $tenantUser->email }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <button class="app-link" type="submit">{{ __('Set') }}</button>
                        </form>

                        <form method="POST" action="{{ route('incidents.update', $incident) }}" class="flex items-end gap-2">
                            @csrf
                            @method('PATCH')
                            <div class="flex-1">
                                <label class="app-label">{{ __('Snooze minutes') }}</label>
                                <input name="snooze_minutes" type="number" min="0" max="10080" value="60" class="app-input" />
                            </div>
                            <button class="app-link" type="submit">{{ __('Apply') }}</button>
                        </form>

                        <form method="POST" action="{{ route('incidents.update', $incident) }}" class="flex items-end gap-2">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="clear_snooze" value="1" />
                            <button class="app-link" type="submit">{{ __('Clear snooze') }}</button>
                        </form>
                    </div>
                @endif
            </div>

            <div class="app-card">
                <div class="text-sm text-gray-600 dark:text-gray-400">{{ __('messages.monitored_urls.url') }}</div>
                <div class="font-medium break-all">
                    @if ($incident->monitoredUrl)
                        <a class="hover:underline" href="{{ route('monitored-urls.show', $incident->monitoredUrl) }}">
                            {{ $incident->monitoredUrl->display_name ?? $incident->monitoredUrl->url }}
                        </a>
                    @else
                        -
                    @endif
                </div>
                @if ($incident->monitoredUrl?->tags?->count())
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach ($incident->monitoredUrl->tags->sortBy('name') as $tag)
                            <span class="app-chip">{{ $tag->name }}</span>
                        @endforeach
                    </div>
                @endif
                @if ($incident->monitoredUrl?->url)
                    <div class="text-sm text-gray-600 dark:text-gray-400 break-all">{{ $incident->monitoredUrl->url }}</div>
                @endif
            </div>

            @if (! empty($changeSummary))
                <div class="app-card">
                    <div class="font-medium">{{ __('What changed') }}</div>
                    <div class="mt-3 overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="text-gray-600 bg-gray-50 dark:text-gray-300 dark:bg-gray-900/70">
                                <tr>
                                    <th class="text-left px-3 py-2">{{ __('Field') }}</th>
                                    <th class="text-left px-3 py-2">{{ __('Before') }}</th>
                                    <th class="text-left px-3 py-2">{{ __('After') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($changeSummary as $row)
                                    <tr class="border-t">
                                    <td class="px-3 py-2 font-medium text-gray-900 dark:text-gray-100">{{ $row['label'] }}</td>
                                    <td class="px-3 py-2 text-gray-700 dark:text-gray-300 font-mono whitespace-pre-wrap break-all">{{ $row['before'] }}</td>
                                    <td class="px-3 py-2 text-gray-700 dark:text-gray-300 font-mono whitespace-pre-wrap break-all">{{ $row['after'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="app-card">
                    <div class="font-medium">{{ __('messages.incidents.previous_snapshot') }}</div>
                    @if ($incident->previousSnapshot)
                        <div class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            <a class="hover:underline" href="{{ route('snapshots.show', $incident->previousSnapshot) }}">
                                {{ $incident->previousSnapshot->fetched_at?->toDateTimeString() }}
                            </a>
                        </div>

                        @if ($previousBodyPreview)
                            <div class="mt-3">
                                <div class="text-xs text-gray-600 dark:text-gray-400 flex items-center justify-between">
                                    <span>{{ __('messages.incidents.body_preview') }}</span>
                                    @if ($previousBodyTruncated)
                                        <span>{{ __('messages.incidents.truncated') }}</span>
                                    @endif
                                </div>
                                <pre class="mt-2 text-xs font-mono whitespace-pre-wrap break-words bg-gray-50 border border-gray-200 rounded p-3 max-h-64 overflow-auto dark:bg-gray-950/60 dark:border-gray-700 dark:text-gray-200">{{ $previousBodyPreview }}</pre>
                            </div>
                        @endif

                        @if ($incident->previousSnapshot->screenshot_storage_path)
                            <div class="mt-3">
                                <div class="text-xs text-gray-600 dark:text-gray-400 flex items-center justify-between">
                                    <span>{{ __('Screenshot') }}</span>
                                    @if ($incident->previousSnapshot->screenshot_phash)
                                        <span class="font-mono">{{ $incident->previousSnapshot->screenshot_phash }}</span>
                                    @endif
                                </div>
                                <a href="{{ route('snapshots.screenshot', $incident->previousSnapshot) }}" target="_blank" rel="noopener">
                                    <img src="{{ route('snapshots.screenshot', $incident->previousSnapshot) }}" alt="{{ __('Previous snapshot screenshot') }}" class="mt-2 max-w-full border border-gray-200 rounded dark:border-gray-700" />
                                </a>
                            </div>
                        @endif

                        @if ($incident->previousSnapshot->body_storage_path)
                            <div class="mt-2">
                                <a class="app-link hover:underline" href="{{ route('snapshots.body', $incident->previousSnapshot) }}">
                                    {{ __('messages.incidents.view_body') }}
                                </a>
                            </div>
                        @endif
                    @else
                        <div class="mt-1 text-sm text-gray-600 dark:text-gray-400">-</div>
                    @endif
                </div>

                <div class="app-card">
                    <div class="font-medium">{{ __('messages.incidents.current_snapshot') }}</div>
                    @if ($incident->currentSnapshot)
                        <div class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            <a class="hover:underline" href="{{ route('snapshots.show', $incident->currentSnapshot) }}">
                                {{ $incident->currentSnapshot->fetched_at?->toDateTimeString() }}
                            </a>
                        </div>

                        @if ($currentBodyPreview)
                            <div class="mt-3">
                                <div class="text-xs text-gray-600 dark:text-gray-400 flex items-center justify-between">
                                    <span>{{ __('messages.incidents.body_preview') }}</span>
                                    @if ($currentBodyTruncated)
                                        <span>{{ __('messages.incidents.truncated') }}</span>
                                    @endif
                                </div>
                                <pre class="mt-2 text-xs font-mono whitespace-pre-wrap break-words bg-gray-50 border border-gray-200 rounded p-3 max-h-64 overflow-auto dark:bg-gray-950/60 dark:border-gray-700 dark:text-gray-200">{{ $currentBodyPreview }}</pre>
                            </div>
                        @endif

                        @if ($incident->currentSnapshot->screenshot_storage_path)
                            <div class="mt-3">
                                <div class="text-xs text-gray-600 dark:text-gray-400 flex items-center justify-between">
                                    <span>{{ __('Screenshot') }}</span>
                                    @if ($incident->currentSnapshot->screenshot_phash)
                                        <span class="font-mono">{{ $incident->currentSnapshot->screenshot_phash }}</span>
                                    @endif
                                </div>
                                <a href="{{ route('snapshots.screenshot', $incident->currentSnapshot) }}" target="_blank" rel="noopener">
                                    <img src="{{ route('snapshots.screenshot', $incident->currentSnapshot) }}" alt="{{ __('Current snapshot screenshot') }}" class="mt-2 max-w-full border border-gray-200 rounded dark:border-gray-700" />
                                </a>
                            </div>
                        @endif

                        @if ($incident->currentSnapshot->body_storage_path)
                            <div class="mt-2">
                                <a class="app-link hover:underline" href="{{ route('snapshots.body', $incident->currentSnapshot) }}">
                                    {{ __('messages.incidents.view_body') }}
                                </a>
                            </div>
                        @endif
                    @else
                        <div class="mt-1 text-sm text-gray-600 dark:text-gray-400">-</div>
                    @endif
                </div>
            </div>

            @if (is_array($diff) && ! empty($diff['lines']))
                <div class="app-card">
                    <div class="flex items-center justify-between">
                        <div class="font-medium">{{ __('Text diff (preview)') }}</div>
                        @if (! empty($diff['truncated']))
                            <div class="text-xs text-gray-600 dark:text-gray-400">{{ __('Truncated') }}</div>
                        @endif
                    </div>

                    <pre class="mt-3 text-xs font-mono whitespace-pre-wrap break-words bg-gray-50 border border-gray-200 rounded p-3 overflow-auto dark:bg-gray-950/60 dark:border-gray-700 dark:text-gray-200">
@foreach ($diff['lines'] as $line)
@php($prefix = $line['type'] === 'insert' ? '+' : ($line['type'] === 'delete' ? '-' : ' '))
@php($cls = $line['type'] === 'insert' ? 'text-emerald-700 dark:text-emerald-300' : ($line['type'] === 'delete' ? 'text-red-700 dark:text-red-300' : 'text-gray-800 dark:text-gray-200'))
<span class="{{ $cls }}">{{ $prefix }} {{ $line['text'] }}</span>
@endforeach
</pre>
                </div>
            @endif

            <div class="app-card">
                <div class="font-medium">{{ __('Comments') }}</div>

                <div class="mt-3 space-y-3">
                    @forelse ($incidentComments as $comment)
                        <div class="border rounded p-3">
                            <div class="text-xs text-gray-600 dark:text-gray-400">
                                {{ $comment->author?->name ?? __('Unknown user') }}
                                <span class="mx-1">&middot;</span>
                                {{ $comment->created_at?->toDateTimeString() }}
                            </div>
                            <div class="mt-2 text-sm whitespace-pre-wrap break-words">{{ $comment->body }}</div>
                        </div>
                    @empty
                        <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('No comments yet.') }}</div>
                    @endforelse
                </div>

                <form method="POST" action="{{ route('incidents.comments.store', $incident) }}" class="mt-4">
                    @csrf
                    <label class="app-label">{{ __('Add comment') }}</label>
                    <textarea name="body" rows="3" class="app-input">{{ old('body') }}</textarea>
                    @error('body')
                        <div class="text-xs text-red-700 mt-1">{{ $message }}</div>
                    @enderror
                    <div class="mt-2">
                        <button class="app-link" type="submit">{{ __('Post comment') }}</button>
                    </div>
                </form>
            </div>

            <div class="app-card">
                <div class="font-medium">{{ __('History') }}</div>
                <div class="mt-3 space-y-3">
                    @forelse ($historyEntries as $entry)
                        <div class="border rounded p-3">
                            <div class="text-xs text-gray-600 dark:text-gray-400">
                                {{ $entry->created_at?->toDateTimeString() }}
                                <span class="mx-1">&middot;</span>
                                {{ $entry->actor?->name ?? __('System') }}
                                <span class="mx-1">&middot;</span>
                                <span class="font-mono">{{ $entry->action }}</span>
                            </div>
                            @if (is_array($entry->data) && $entry->data !== [])
                                <pre class="mt-2 text-xs font-mono whitespace-pre-wrap break-words bg-gray-50 border rounded p-2 overflow-auto">{{ json_encode($entry->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                            @endif
                        </div>
                    @empty
                        <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('No history yet.') }}</div>
                    @endforelse
                </div>
            </div>

            <div class="app-card">
                <div class="font-medium">{{ __('messages.incidents.details') }}</div>
                <pre class="mt-3 text-xs font-mono whitespace-pre-wrap break-words bg-gray-50 border rounded p-3 overflow-auto">{{ json_encode($incident->details, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
            </div>
        </div>
    </div>
</x-app-layout>

