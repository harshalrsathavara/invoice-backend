@extends('admin.layout')
@section('title', $customer->exists ? 'Edit '.$customer->name : 'New customer')

@section('content')
    <div class="page-head">
        <div>
            <div class="eyebrow">Customers</div>
            <h1>{{ $customer->exists ? 'Edit '.$customer->name : 'New customer' }}</h1>
            <div class="sub">
                @if($customer->exists)
                    A bill keeps the name it was raised under, so renaming someone here also updates the name on their existing bills.
                @else
                    Names typed onto a bill are added here automatically — this form is for adding someone before they are billed.
                @endif
            </div>
        </div>
        <a href="{{ $customer->exists ? route('admin.customers.show', $customer) : route('admin.customers.index') }}" class="btn ghost small">Cancel</a>
    </div>

    <form method="POST" action="{{ $customer->exists ? route('admin.customers.update', $customer) : route('admin.customers.store') }}">
        @csrf
        @if($customer->exists) @method('PUT') @endif

        <section class="card form-card">
            <h2>Details</h2>
            <div class="body">
                <div class="form-grid">
                    @if($customer->exists)
                        <div class="fieldset span-2">
                            <label>Business</label>
                            <div class="who-cell">
                                <x-avatar :name="$business->name" small />
                                <span>{{ $business->name }}</span>
                            </div>
                            <div class="help">A customer belongs to one business and cannot be moved between them.</div>
                        </div>
                    @else
                        <x-field name="business_uuid" label="Business" type="select" required span>
                            @foreach($businesses as $b)
                                <option value="{{ $b->uuid }}" @selected(old('business_uuid') === $b->uuid)>{{ $b->name }}</option>
                            @endforeach
                        </x-field>
                    @endif

                    <x-field name="name" label="Name" :value="$customer->name" required span />
                    <x-field name="phone" label="Phone" :value="$customer->phone"
                             help="Shown beside them on the chase list." />
                    <x-field name="gst_number" label="GSTIN" :value="$customer->gst_number" />
                    <x-field name="address" label="Address" :value="$customer->address" type="textarea" span />
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn">{{ $customer->exists ? 'Save changes' : 'Add customer' }}</button>
                <a href="{{ $customer->exists ? route('admin.customers.show', $customer) : route('admin.customers.index') }}" class="btn ghost">Cancel</a>

                @if($customer->exists)
                    <div class="right">
                        <form method="POST" action="{{ route('admin.customers.destroy', $customer) }}" class="inline-form"
                              onsubmit="return confirm('Remove {{ $customer->name }} from the customer list? Their bills stay on the books.')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn danger">Remove from list</button>
                        </form>
                    </div>
                @endif
            </div>
        </section>
    </form>
@endsection
