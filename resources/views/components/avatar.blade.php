@props(['name', 'small' => false])
@php
    // A stable colour per name, so the same customer keeps the same chip.
    $initials = collect(preg_split('/\s+/', trim((string) $name)))
        ->filter()
        ->take(2)
        ->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))
        ->implode('') ?: '?';
    $slot = 'c'.((crc32(mb_strtolower((string) $name)) % 4) + 1);
@endphp
<span class="avatar {{ $slot }} {{ $small ? 'sm' : '' }}" aria-hidden="true">{{ $initials }}</span>
