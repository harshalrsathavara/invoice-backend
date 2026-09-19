@props(['value', 'symbol' => true])
{{ $symbol ? \App\Services\Money::rupees((float) $value) : \App\Services\Money::amount((float) $value) }}
