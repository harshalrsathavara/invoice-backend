@extends('admin.layout')
@section('title', $item->exists ? 'Edit '.$item->name : 'New catalogue entry')

@section('content')
    <div class="page-head">
        <div>
            <div class="eyebrow">Items &amp; rates</div>
            <h1>{{ $item->exists ? 'Edit '.$item->name : 'New catalogue entry' }}</h1>
            <div class="sub">What the app offers as a suggestion when someone types a line onto a bill.</div>
        </div>
        <a href="{{ route('admin.items.index') }}" class="btn ghost small">Cancel</a>
    </div>

    <form method="POST" action="{{ $item->exists ? route('admin.items.update', $item) : route('admin.items.store') }}">
        @csrf
        @if($item->exists) @method('PUT') @endif

        <section class="card form-card">
            <h2>Details</h2>
            <div class="body">
                <div class="form-grid">
                    @if($item->exists)
                        <div class="fieldset span-2">
                            <label>Business</label>
                            <div class="who-cell">
                                <x-avatar :name="$business->name" small />
                                <span>{{ $business->name }}</span>
                            </div>
                        </div>
                    @else
                        <x-field name="business_uuid" label="Business" type="select" required span>
                            @foreach($businesses as $b)
                                <option value="{{ $b->uuid }}" @selected(old('business_uuid', $business?->uuid) === $b->uuid)>{{ $b->name }}</option>
                            @endforeach
                        </x-field>
                    @endif

                    <x-field name="name" label="Work or item" :value="$item->name" required span
                             placeholder="e.g. Surface Grinding" />
                    <x-field name="default_rate" label="Default rate" :value="$item->default_rate" type="number"
                             help="Suggested on a new line. Bills already raised keep their own rate." />
                    <x-field name="hsn_code" label="HSN / SAC code" :value="$item->hsn_code" />
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn">{{ $item->exists ? 'Save changes' : 'Add to catalogue' }}</button>
                <a href="{{ route('admin.items.index') }}" class="btn ghost">Cancel</a>

                @if($item->exists)
                    <div class="right">
                        <form method="POST" action="{{ route('admin.items.destroy', $item) }}" class="inline-form"
                              onsubmit="return confirm('Remove {{ $item->name }} from the catalogue? Bills that used it are untouched.')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn danger">Remove</button>
                        </form>
                    </div>
                @endif
            </div>
        </section>
    </form>
@endsection
