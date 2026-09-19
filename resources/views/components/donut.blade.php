@props([
    'bands',            // [['label' => '0–30 days', 'value' => 1.0, 'token' => 'age-1', 'flag' => null], ...]
    'centreCap' => '',
    'centreValue' => '',
])
@php
    use App\Services\Money;

    $bands = collect($bands)->values();
    $total = (float) $bands->sum(fn ($b) => (float) $b['value']);

    $r = 62; $cx = 84; $cy = 84; $stroke = 22;
    $circumference = 2 * M_PI * $r;
    $gap = 3;                                   // the 2px surface gap, in user units
    $drawn = $bands->filter(fn ($b) => (float) $b['value'] > 0)->count();

    $offset = 0.0;
    $arcs = [];
    foreach ($bands as $band) {
        $value = (float) $band['value'];
        if ($value <= 0 || $total <= 0) {
            continue;
        }
        $length = $value / $total * $circumference;
        $arcs[] = [
            'token' => $band['token'],
            'dash' => round($drawn > 1 ? max(1, $length - $gap) : $length, 2),
            'rest' => round($circumference, 2),
            'offset' => round(-$offset, 2),
            'label' => $band['label'],
            'value' => $value,
        ];
        $offset += $length;
    }
@endphp

<div class="donut">
    <div class="ring">
        <svg viewBox="0 0 168 168" role="img"
             aria-label="{{ $centreCap }} {{ $centreValue }}: {{ $bands->map(fn ($b) => $b['label'].' '.Money::rupees((float) $b['value']))->implode(', ') }}">
            <g transform="rotate(-90 {{ $cx }} {{ $cy }})">
                <circle cx="{{ $cx }}" cy="{{ $cy }}" r="{{ $r }}" fill="none"
                        stroke="var(--sunk)" stroke-width="{{ $stroke }}" />
                @foreach($arcs as $arc)
                    <circle class="arc" cx="{{ $cx }}" cy="{{ $cy }}" r="{{ $r }}"
                            stroke="var(--{{ $arc['token'] }})" stroke-width="{{ $stroke }}"
                            stroke-dasharray="{{ $arc['dash'] }} {{ $arc['rest'] }}"
                            stroke-dashoffset="{{ $arc['offset'] }}">
                        <title>{{ $arc['label'] }} — {{ Money::rupees($arc['value']) }}</title>
                    </circle>
                @endforeach
            </g>
        </svg>
        <div class="hole">
            <div class="cap">{{ $centreCap }}</div>
            <div class="fig">{{ $centreValue }}</div>
        </div>
    </div>

    {{-- The legend carries every value, so nothing is gated behind a hover. --}}
    <div class="legend">
        @foreach($bands as $band)
            <div class="row">
                <span class="key" style="background: var(--{{ $band['token'] }})"></span>
                <span class="name">
                    {{ $band['label'] }}
                    @if(($band['flag'] ?? null) && (float) $band['value'] > 0)
                        <span class="pill unpaid">{{ $band['flag'] }}</span>
                    @endif
                </span>
                <span>
                    <span class="val">{{ Money::rupees((float) $band['value']) }}</span>
                    <span class="pct">{{ $total > 0 ? round((float) $band['value'] / $total * 100) : 0 }}%</span>
                </span>
            </div>
        @endforeach
    </div>
</div>
