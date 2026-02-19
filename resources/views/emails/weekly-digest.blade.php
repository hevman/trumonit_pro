@php
    /** @var array<string, mixed> $digest */
    $overview = is_array($digest['overview'] ?? null) ? $digest['overview'] : [];
    $topKinds = is_array($digest['top_incident_kinds'] ?? null) ? $digest['top_incident_kinds'] : [];
    $unstableMonitors = is_array($digest['unstable_monitors'] ?? null) ? $digest['unstable_monitors'] : [];
    $dailyUsage = is_array($digest['daily_usage'] ?? null) ? $digest['daily_usage'] : [];
    $usageLabels = is_array($dailyUsage['labels'] ?? null) ? $dailyUsage['labels'] : [];
    $usageDates = is_array($dailyUsage['dates'] ?? null) ? $dailyUsage['dates'] : [];
    $usageScans = is_array($dailyUsage['scans'] ?? null) ? $dailyUsage['scans'] : [];
    $usageIncidents = is_array($dailyUsage['incidents'] ?? null) ? $dailyUsage['incidents'] : [];
    $usageApiRequests = is_array($dailyUsage['api_requests'] ?? null) ? $dailyUsage['api_requests'] : [];
@endphp

<p><strong>{{ __('TruMonit weekly digest') }}</strong></p>

<p>
    {{ __('Tenant') }}: <strong>{{ $digest['tenant']['name'] ?? '-' }}</strong><br>
    {{ __('Window') }}: <strong>{{ $digest['window']['start'] ?? '-' }} - {{ $digest['window']['end'] ?? '-' }}</strong>
</p>

<h3>{{ __('Overview') }}</h3>
<ul>
    <li><strong>{{ __('Monitors') }}:</strong> {{ $overview['active_monitors'] ?? 0 }} {{ __('active') }} / {{ $overview['monitors_total'] ?? 0 }} {{ __('total') }}</li>
    <li><strong>{{ __('Scans') }}:</strong> {{ number_format((int) ($overview['scans_total'] ?? 0)) }}</li>
    <li><strong>{{ __('Scan errors/down checks') }}:</strong> {{ number_format((int) ($overview['scans_error_total'] ?? 0)) }}</li>
    <li><strong>{{ __('Uptime') }}:</strong> {{ isset($overview['uptime_percent']) ? number_format((float) $overview['uptime_percent'], 2).'%' : '-' }}</li>
    <li><strong>{{ __('Incidents opened') }}:</strong> {{ number_format((int) ($overview['incidents_opened_total'] ?? 0)) }}</li>
    <li><strong>{{ __('Incidents resolved') }}:</strong> {{ number_format((int) ($overview['incidents_resolved_total'] ?? 0)) }}</li>
    <li><strong>{{ __('Critical incidents') }}:</strong> {{ number_format((int) ($overview['incidents_critical_total'] ?? 0)) }}</li>
    <li><strong>MTTR:</strong> {{ isset($overview['mttr_minutes']) ? ((int) $overview['mttr_minutes']).' '.__('min') : '-' }}</li>
</ul>

<h3>{{ __('Top incident kinds') }}</h3>
@if ($topKinds !== [])
    <ul>
        @foreach ($topKinds as $row)
            <li><strong>{{ $row['kind'] ?? '-' }}</strong>: {{ number_format((int) ($row['total'] ?? 0)) }}</li>
        @endforeach
    </ul>
@else
    <p>{{ __('No incident kinds in selected window.') }}</p>
@endif

<h3>{{ __('Most unstable monitors') }}</h3>
@if ($unstableMonitors !== [])
    <ul>
        @foreach ($unstableMonitors as $row)
            <li>
                <strong>{{ $row['label'] ?? '-' }}</strong>
                ({{ $row['url'] ?? '-' }}) -
                {{ __('down ratio') }} {{ number_format((float) ($row['down_ratio_percent'] ?? 0), 1) }}%
                ({{ (int) ($row['down_checks'] ?? 0) }}/{{ (int) ($row['total_checks'] ?? 0) }}),
                {{ __('avg response') }} {{ isset($row['avg_response_ms']) ? ((int) $row['avg_response_ms']).' '.__('ms') : '-' }}
            </li>
        @endforeach
    </ul>
@else
    <p>{{ __('No unstable monitors detected in selected window.') }}</p>
@endif

<h3>{{ __('Daily trend (scans / incidents / API requests)') }}</h3>
@if ($usageDates !== [])
    <ul>
        @foreach ($usageDates as $index => $date)
            @php
                $label = $usageLabels[$index] ?? $date;
                $scansValue = (int) ($usageScans[$index] ?? 0);
                $incidentsValue = (int) ($usageIncidents[$index] ?? 0);
                $apiRequestsValue = (int) ($usageApiRequests[$index] ?? 0);
            @endphp
            <li>
                <strong>{{ $label }} ({{ $date }})</strong>:
                {{ __('scans') }} {{ number_format($scansValue) }},
                {{ __('incidents') }} {{ number_format($incidentsValue) }},
                {{ __('API requests') }} {{ number_format($apiRequestsValue) }}
            </li>
        @endforeach
    </ul>
@else
    <p>{{ __('No daily usage series available.') }}</p>
@endif

<p><a href="{{ $dashboardUrl }}">{{ __('Open dashboard') }}</a></p>
