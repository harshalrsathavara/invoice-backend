@props([
    'points',          // [['label' => 'Apr 2026', 'value' => 285115.0], ...]
    'caption' => null, // what the single series is — stands in for a legend
])
@php
    use App\Services\Money;

    $points = collect($points)->values();
    $values = $points->map(fn ($p) => (float) $p['value']);

    // A clean top tick: round up to 1/2/5 × a power of ten.
    $max = (float) $values->max();
    $niceMax = 1.0;
    if ($max > 0) {
        $pow = 10 ** floor(log10($max));
        $niceMax = collect([1, 1.5, 2, 2.5, 3, 4, 5, 10])
            ->map(fn ($m) => $m * $pow)
            ->first(fn ($candidate) => $candidate >= $max) ?? 10 * $pow;
    }

    // Geometry, in viewBox units.
    $w = 720; $h = 250;
    $left = 58; $right = 14; $top = 16; $bottom = 34;
    $plotW = $w - $left - $right;
    $plotH = $h - $top - $bottom;
    $step = $points->count() > 1 ? $plotW / ($points->count() - 1) : 0;

    $x = fn (int $i) => round($left + $i * $step, 2);
    $y = fn (float $v) => round($top + $plotH - ($niceMax > 0 ? $v / $niceMax : 0) * $plotH, 2);

    $coords = $points->map(fn ($p, $i) => [$x($i), $y((float) $p['value'])]);
    $line = $coords->map(fn ($c, $i) => ($i === 0 ? 'M' : 'L').$c[0].' '.$c[1])->implode(' ');
    $wash = $line.' L'.$coords->last()[0].' '.($top + $plotH).' L'.$coords->first()[0].' '.($top + $plotH).' Z';

    $ticks = [0.0, $niceMax / 2, $niceMax];
    $lastIndex = $points->count() - 1;
@endphp

<div class="chart" data-areachart>
    <svg viewBox="0 0 {{ $w }} {{ $h }}" role="img"
         aria-label="{{ $caption ?? 'Trend' }}: {{ $points->map(fn ($p) => $p['label'].' '.Money::rupees((float) $p['value']))->implode(', ') }}">

        {{-- Hairline grid, solid, one step off the surface. --}}
        @foreach($ticks as $tick)
            @php $ty = $y($tick); @endphp
            <line class="{{ $tick == 0 ? 'baseline' : 'gridline' }}"
                  x1="{{ $left }}" y1="{{ $ty }}" x2="{{ $w - $right }}" y2="{{ $ty }}"
                  vector-effect="non-scaling-stroke" />
            <text class="tick y" x="{{ $left - 10 }}" y="{{ $ty + 3.5 }}">{{ Money::compact($tick) }}</text>
        @endforeach

        <path class="wash" d="{{ $wash }}" />
        <path class="line" d="{{ $line }}" vector-effect="non-scaling-stroke" />

        {{-- Only the endpoint is direct-labelled; the axis and the tooltip carry the rest. --}}
        <circle class="knot" cx="{{ $coords->last()[0] }}" cy="{{ $coords->last()[1] }}" r="4.5" />
        <text class="endlabel" x="{{ $coords->last()[0] }}" y="{{ max($top + 11, $coords->last()[1] - 13) }}">
            {{ Money::compact((float) $points->last()['value']) }}
        </text>

        <line class="crosshair" x1="0" y1="{{ $top }}" x2="0" y2="{{ $top + $plotH }}" vector-effect="non-scaling-stroke" />
        <circle class="hover-knot" cx="0" cy="0" r="5" />

        @foreach($points as $i => $point)
            <text class="tick x" x="{{ $x($i) }}" y="{{ $h - 12 }}">{{ \Str::before($point['label'], ' ') }}</text>
            {{-- Hit bands span the full column height, so hovering never needs precision. --}}
            <rect class="hit"
                  x="{{ round($x($i) - $step / 2, 2) }}" y="{{ $top }}"
                  width="{{ round($step > 0 ? $step : $plotW, 2) }}" height="{{ $plotH }}"
                  data-x="{{ $x($i) }}" data-y="{{ $y((float) $point['value']) }}"
                  data-label="{{ $point['label'] }}"
                  data-value="{{ Money::rupees((float) $point['value']) }}"
                  data-note="{{ $point['bill_count'] ?? null ? $point['bill_count'].' '.\Str::plural('bill', $point['bill_count']) : '' }}" />
        @endforeach
    </svg>

    <details class="table-view">
        <summary>Table view</summary>
        <table>
            <thead><tr><th>Month</th><th class="num">Billed</th><th class="num">Bills</th></tr></thead>
            <tbody>
            @foreach($points as $point)
                <tr>
                    <td>{{ $point['label'] }}</td>
                    <td class="num strong">{{ Money::rupees((float) $point['value']) }}</td>
                    <td class="num muted">{{ $point['bill_count'] ?? '—' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </details>
</div>
