<x-app-layout>
    <x-slot name="header">
        @php($isOwner = auth()->user()?->role !== 'member')
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Snapshot') }} #{{ $snapshot->id }}
            </h2>
            <div class="flex items-center gap-3">
                <a href="{{ route('monitored-urls.show', $snapshot->monitoredUrl) }}" class="app-link">
                    &lt;- {{ __('messages.monitored_urls.details') }}
                </a>

                @if ($snapshot->monitoredUrl)
                    @if ($snapshot->monitoredUrl->baseline_snapshot_id === $snapshot->id)
                        <span class="text-sm text-gray-600 dark:text-gray-400">{{ __('Baseline') }}</span>
                    @elseif ($isOwner)
                        <form method="POST" action="{{ route('monitored-urls.baseline', $snapshot->monitoredUrl) }}">
                            @csrf
                            <input type="hidden" name="snapshot_id" value="{{ $snapshot->id }}">
                            <button class="app-link" type="submit">{{ __('Set as baseline') }}</button>
                        </form>
                    @else
                        <span class="text-sm text-gray-600 dark:text-gray-400">{{ __('Read-only') }}</span>
                    @endif
                @endif
            </div>
        </div>
    </x-slot>

    <div class="app-page">
        <div class="app-container">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="app-card space-y-2 text-sm">
                    <div><span class="text-gray-600 dark:text-gray-400">{{ __('Fetched') }}:</span> <span class="font-medium">{{ $snapshot->fetched_at?->toDateTimeString() }}</span></div>
                    <div><span class="text-gray-600 dark:text-gray-400">{{ __('Request URL') }}:</span> <span class="font-medium break-all">{{ $snapshot->request_url }}</span></div>
                    <div><span class="text-gray-600 dark:text-gray-400">{{ __('Final URL') }}:</span> <span class="font-medium break-all">{{ $snapshot->final_url ?? '-' }}</span></div>
                    <div><span class="text-gray-600 dark:text-gray-400">{{ __('HTTP') }}:</span> <span class="font-medium">{{ $snapshot->http_status ?? '-' }}</span></div>
                    <div><span class="text-gray-600 dark:text-gray-400">{{ __('Time (ms)') }}:</span> <span class="font-medium">{{ $snapshot->response_time_ms ?? '-' }}</span></div>
                    <div><span class="text-gray-600 dark:text-gray-400">{{ __('Size') }}:</span> <span class="font-medium">{{ $snapshot->body_size ?? '-' }}</span></div>
                    <div><span class="text-gray-600 dark:text-gray-400">{{ __('SHA') }}:</span> <span class="font-mono text-xs">{{ $snapshot->body_sha256 ?? '-' }}</span></div>
                    <div><span class="text-gray-600 dark:text-gray-400">{{ __('Visual hash') }}:</span> <span class="font-mono text-xs">{{ $snapshot->screenshot_phash ?? '-' }}</span></div>
                    @if ($snapshot->error_message)
                        <div class="text-red-700 dark:text-red-300"><span class="text-gray-600 dark:text-gray-400">{{ __('Error') }}:</span> {{ $snapshot->error_message }}</div>
                    @endif
                </div>

                <div class="app-card text-sm">
                    <div class="font-medium mb-2">{{ __('Headers') }}</div>
                    <pre class="text-xs whitespace-pre-wrap">{{ json_encode($snapshot->headers ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                </div>
            </div>

            <div class="app-card text-sm">
                <div class="font-medium mb-2">{{ __('Meta') }}</div>
                <pre class="text-xs whitespace-pre-wrap">{{ json_encode($snapshot->meta ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
            </div>

            <div class="app-card">
                <div class="px-4 py-3 border-b flex items-center justify-between">
                    <div class="font-medium">{{ __('Screenshot') }}</div>
                    @if ($snapshot->screenshot_storage_path)
                        <a href="{{ route('snapshots.screenshot', $snapshot) }}" class="app-link" target="_blank" rel="noopener">{{ __('Open') }}</a>
                    @endif
                </div>
                <div class="p-4">
                    @if (! $snapshot->screenshot_storage_path)
                        <div class="text-sm text-gray-600 dark:text-gray-400">{{ __('No screenshot available.') }}</div>
                    @else
                        <a href="{{ route('snapshots.screenshot', $snapshot) }}" target="_blank" rel="noopener">
                            <img src="{{ route('snapshots.screenshot', $snapshot) }}" alt="{{ __('Snapshot screenshot') }}" class="max-w-full border rounded" />
                        </a>
                    @endif
                </div>
            </div>

            <div class="app-card">
                <div class="px-4 py-3 border-b flex items-center justify-between">
                    <div class="font-medium">{{ __('Body preview') }}</div>
                    @if ($snapshot->body_storage_path)
                        <a href="{{ route('snapshots.body', $snapshot) }}" class="app-link">{{ __('Download') }}</a>
                    @endif
                </div>
                <div class="p-4">
                    @if ($bodyPreview === null)
                        <div class="text-sm text-gray-600 dark:text-gray-400">{{ __('No preview available.') }}</div>
                    @else
                        <pre class="text-xs whitespace-pre-wrap">{{ $bodyPreview }}</pre>
                        @if ($bodyTruncated)
                            <div class="mt-2 text-sm text-gray-600 dark:text-gray-400">{{ __('Preview truncated.') }}</div>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

