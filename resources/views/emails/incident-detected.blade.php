@php
    /** @var \App\Models\Incident $incident */
@endphp
<p><strong>TruMonit</strong> {{ __('detected an incident.') }}</p>

<ul>
    <li><strong>{{ __('ID') }}:</strong> {{ $incident->id }}</li>
    <li><strong>{{ __('Kind') }}:</strong> {{ $incident->kind }}</li>
    <li><strong>{{ __('Severity') }}:</strong> {{ $incident->severity }}</li>
    <li><strong>{{ __('Status') }}:</strong> {{ $incident->status }}</li>
    <li><strong>{{ __('Detected at') }}:</strong> {{ $incident->detected_at?->toDateTimeString() }}</li>
    <li><strong>{{ __('URL') }}:</strong> {{ $incident->monitoredUrl?->url ?? '-' }}</li>
</ul>

<p><strong>{{ __('Summary') }}:</strong> {{ $incident->summary }}</p>

<p><a href="{{ $panelUrl }}">{{ __('Open in panel') }}</a></p>

<pre style="white-space: pre-wrap; word-break: break-word">{{ json_encode($incident->details, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
