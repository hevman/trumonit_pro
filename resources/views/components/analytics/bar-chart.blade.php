@props([
    'values' => [],
    'labels' => [],
    'drilldownUrls' => [],
    'maxValue' => null,
    'hasData' => null,
    'seriesName' => 'Series',
    'legend' => null,
    'unit' => '',
    'tone' => 'indigo',
    'heightClass' => 'h-40',
    'minBarHeight' => 8,
    'labelStep' => 2,
    'emptyText' => 'No data available.',
    'maxBarHeightPx' => 145,
])

@php
    $chartValues = collect(is_array($values) ? $values : [])
        ->map(fn ($value) => is_numeric($value) ? (float) $value : 0.0)
        ->values()
        ->all();

    $chartLabels = array_values(is_array($labels) ? $labels : []);
    $chartDrilldownUrls = array_values(is_array($drilldownUrls) ? $drilldownUrls : []);
    $chartHasData = is_bool($hasData)
        ? $hasData
        : collect($chartValues)->contains(fn (float $value): bool => $value > 0);

    $computedMax = is_numeric($maxValue)
        ? max(1.0, (float) $maxValue)
        : max(1.0, max($chartValues ?: [0.0]));

    $barMaxHeight = max(40, (int) $maxBarHeightPx);
    $barMinHeight = max(2, (int) $minBarHeight);
    $labelInterval = max(1, (int) $labelStep);
    $legendText = trim((string) $legend) !== '' ? (string) $legend : (string) $seriesName;
    $unitText = trim((string) $unit);
    $labelCount = count($chartLabels);
    $maxDecimals = abs($computedMax - round($computedMax)) < 0.001 ? 0 : 1;

    $toneClasses = match ((string) $tone) {
        'teal' => [
            'bar' => 'bg-teal-500/80',
            'bar_hover' => 'group-hover:bg-teal-500',
            'dot' => 'bg-teal-500',
        ],
        'emerald' => [
            'bar' => 'bg-emerald-500/80',
            'bar_hover' => 'group-hover:bg-emerald-500',
            'dot' => 'bg-emerald-500',
        ],
        'amber' => [
            'bar' => 'bg-amber-500/80',
            'bar_hover' => 'group-hover:bg-amber-500',
            'dot' => 'bg-amber-500',
        ],
        'red' => [
            'bar' => 'bg-red-500/80',
            'bar_hover' => 'group-hover:bg-red-500',
            'dot' => 'bg-red-500',
        ],
        default => [
            'bar' => 'bg-indigo-500/80',
            'bar_hover' => 'group-hover:bg-indigo-500',
            'dot' => 'bg-indigo-500',
        ],
    };
@endphp

<div class="app-analytics-chart">
    <div class="app-analytics-chart-legend">
        <div class="app-analytics-chart-legend-label">
            <span class="app-analytics-chart-legend-dot {{ $toneClasses['dot'] }}"></span>
            <span>{{ $legendText }}</span>
        </div>
        <div class="app-analytics-chart-legend-meta">
            max {{ number_format($computedMax, $maxDecimals) }}{{ $unitText !== '' ? ' '.$unitText : '' }}
        </div>
    </div>

    @if ($chartHasData && $chartValues !== [])
        <div class="app-analytics-chart-bars {{ $heightClass }}">
            @foreach ($chartValues as $index => $value)
                @php
                    $heightRatio = $computedMax > 0 ? ($value / $computedMax) : 0.0;
                    $barHeight = max($barMinHeight, (int) round($heightRatio * $barMaxHeight));
                    $barLabel = $chartLabels[$index] ?? '#'.($index + 1);
                    $formattedValue = abs($value - round($value)) < 0.001
                        ? number_format((int) round($value))
                        : number_format($value, 1);
                    $tooltip = $barLabel.': '.$formattedValue.($unitText !== '' ? ' '.$unitText : '');
                    $drilldownUrl = $chartDrilldownUrls[$index] ?? null;
                    $isClickable = is_string($drilldownUrl) && trim($drilldownUrl) !== '' && $value > 0;
                @endphp
                @if ($isClickable)
                    <a href="{{ $drilldownUrl }}" class="app-analytics-chart-bar-item app-analytics-chart-bar-item-clickable group" title="{{ $tooltip }}" aria-label="{{ $tooltip }}">
                        <span class="app-analytics-chart-tooltip">{{ $tooltip }}</span>
                        <span class="app-analytics-chart-bar {{ $toneClasses['bar'] }} {{ $toneClasses['bar_hover'] }}" style="height: {{ $barHeight }}px"></span>
                    </a>
                @else
                    <div class="app-analytics-chart-bar-item group" title="{{ $tooltip }}" aria-label="{{ $tooltip }}">
                        <span class="app-analytics-chart-tooltip">{{ $tooltip }}</span>
                        <span class="app-analytics-chart-bar {{ $toneClasses['bar'] }} {{ $toneClasses['bar_hover'] }}" style="height: {{ $barHeight }}px"></span>
                    </div>
                @endif
            @endforeach
        </div>

        @if ($chartLabels !== [])
            <div class="app-analytics-chart-labels">
                @foreach ($chartLabels as $labelIndex => $label)
                    <div class="app-analytics-chart-label">
                        @if ($labelIndex % $labelInterval === 0 || $labelIndex === ($labelCount - 1))
                            {{ $label }}
                        @else
                            &nbsp;
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    @else
        <div class="app-analytics-chart-empty">
            {{ $emptyText }}
        </div>
    @endif
</div>
