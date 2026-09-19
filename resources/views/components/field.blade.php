@props([
    'name',
    'label',
    'value' => null,
    'type' => 'text',
    'help' => null,
    'required' => false,
    'placeholder' => null,
    'span' => false,
])
@php
    $id = 'f-'.str_replace(['[', ']', '.', '_'], '-', $name);
    $shown = old($name, $value);
@endphp
<div class="fieldset {{ $span ? 'span-2' : '' }}">
    <label for="{{ $id }}">{{ $label }}@if($required) <span aria-hidden="true">*</span>@endif</label>

    @if($type === 'textarea')
        <textarea id="{{ $id }}" name="{{ $name }}" placeholder="{{ $placeholder }}"
                  @if($errors->has($name)) aria-invalid="true" @endif
                  @if($required) required @endif>{{ $shown }}</textarea>
    @elseif($type === 'select')
        <select id="{{ $id }}" name="{{ $name }}" @if($errors->has($name)) aria-invalid="true" @endif @if($required) required @endif>
            {{ $slot }}
        </select>
    @else
        <input id="{{ $id }}" type="{{ $type }}" name="{{ $name }}" value="{{ $shown }}"
               placeholder="{{ $placeholder }}"
               @if($type === 'number') step="any" @endif
               @if($errors->has($name)) aria-invalid="true" @endif
               @if($required) required @endif>
    @endif

    @error($name)
        <div class="bad">{{ $message }}</div>
    @else
        @if($help)<div class="help">{{ $help }}</div>@endif
    @enderror
</div>
