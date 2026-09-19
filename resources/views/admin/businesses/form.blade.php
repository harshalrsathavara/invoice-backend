@extends('admin.layout')
@section('title', $business->exists ? 'Edit '.$business->name : 'New business')

@section('content')
    @php($cancel = $business->exists ? route('admin.businesses.show', $business) : route('admin.businesses.index'))

    <div class="page-head">
        <div>
            <div class="eyebrow">Business</div>
            <h1>{{ $business->exists ? 'Edit '.$business->name : 'New business' }}</h1>
            <div class="sub">These details print on every bill, quotation and challan this business raises.</div>
        </div>
        <a href="{{ $cancel }}" class="btn ghost small">Cancel</a>
    </div>

    <form method="POST" enctype="multipart/form-data"
          action="{{ $business->exists ? route('admin.businesses.update', $business) : route('admin.businesses.store') }}">
        @csrf
        @if($business->exists) @method('PUT') @endif

        <section class="card" style="margin-bottom:18px">
            <h2>Identity</h2>
            <div class="body">
                <div class="form-grid">
                    @if($business->exists)
                        <div class="fieldset span-2">
                            <label>Owner</label>
                            <div class="who-cell">
                                <x-avatar :name="$business->user->name" small />
                                <span>{{ $business->user->name }}</span>
                            </div>
                            <div class="help">A business cannot be moved between owners — sync is per account, and the handsets already signed in hold it under this one.</div>
                        </div>
                    @else
                        <x-field name="user_id" label="Owner" type="select" required span
                                 help="Whose handsets this company syncs to.">
                            @foreach($owners as $owner)
                                <option value="{{ $owner->id }}" @selected((int) old('user_id', auth()->id()) === $owner->id)>
                                    {{ $owner->name }} · {{ $owner->email }}
                                </option>
                            @endforeach
                        </x-field>
                    @endif

                    <x-field name="name" label="Business name" :value="$business->name" required span />
                    <x-field name="tagline" label="Tagline" :value="$business->tagline"
                             help="The line under the name on a printed bill." span />
                    <x-field name="address" label="Address" :value="$business->address" type="textarea" span />
                    <x-field name="mobile" label="Mobile" :value="$business->mobile" />
                    <x-field name="email" label="Email" :value="$business->email" type="email" />
                    <x-field name="gst_number" label="GSTIN" :value="$business->gst_number" />
                    <x-field name="jurisdiction_text" label="Jurisdiction" :value="$business->jurisdiction_text"
                             placeholder="Subject to … Jurisdiction" />
                </div>
            </div>
        </section>

        <section class="card" style="margin-bottom:18px">
            <h2>How they get paid</h2>
            <div class="body">
                <div class="form-grid">
                    <x-field name="upi_id" label="UPI id" :value="$business->upi_id"
                             help="What the QR code on the bill points at." />
                    <x-field name="bank_details" label="Bank details" :value="$business->bank_details" type="textarea" span />
                    <x-field name="terms_text" label="Terms" :value="$business->terms_text" type="textarea"
                             help="Printed at the foot of every document." span />
                </div>
            </div>
        </section>

        <section class="card" style="margin-bottom:18px">
            <h2>
                What prints on the bill
                <span class="hint">JPG, PNG or WebP · up to 2 MB</span>
            </h2>
            <div class="body">
                <div class="form-grid">
                    @foreach([
                        ['kind' => 'logo', 'label' => 'Logo', 'help' => 'Printed at the head of every document, beside the name.'],
                        ['kind' => 'signature', 'label' => 'Signature', 'help' => 'Printed above the authorised-signatory line at the foot.'],
                    ] as $image)
                        @php($current = $business->exists ? $business->{$image['kind'].'_path'} : null)

                        <div class="fieldset">
                            <label for="f-{{ $image['kind'] }}">{{ $image['label'] }}</label>

                            @if($current)
                                <img class="image-preview"
                                     src="{{ route('admin.businesses.image', [$business, $image['kind']]) }}"
                                     alt="{{ $business->name }} {{ $image['kind'] }}">
                            @endif

                            <input id="f-{{ $image['kind'] }}" type="file" name="{{ $image['kind'] }}"
                                   accept="image/jpeg,image/png,image/webp"
                                   @if($errors->has($image['kind'])) aria-invalid="true" @endif>

                            @if($current)
                                <label class="check" style="margin-top:8px">
                                    <input type="checkbox" name="remove_{{ $image['kind'] }}" value="1">
                                    <span>Remove the current one</span>
                                </label>
                            @endif

                            @error($image['kind'])
                                <div class="bad">{{ $message }}</div>
                            @else
                                <div class="help">
                                    {{ $image['help'] }}
                                    @if($current) Choosing a file replaces it. @elseif($business->exists) None uploaded yet. @endif
                                </div>
                            @enderror
                        </div>
                    @endforeach
                </div>

                @unless($business->exists)
                    <p class="muted" style="margin:16px 0 0;font-size:12.5px">
                        You can add these now or come back to it — nothing else on the form depends on them.
                    </p>
                @endunless
            </div>
        </section>

        <section class="card">
            <h2>
                Numbering
                @if($business->exists)
                    <span class="hint">Next bill: {{ $business->formatBillNo($business->next_bill_no) }}</span>
                @else
                    <span class="hint">Starts at 1</span>
                @endif
            </h2>
            <div class="body">
                <div class="form-grid">
                    <x-field name="bill_prefix" label="Bill prefix" :value="$business->bill_prefix"
                             help="The letters in front of the number, e.g. RS." />
                    <div class="fieldset">
                        <label>Financial year reset</label>
                        <label class="check">
                            <input type="checkbox" name="fy_reset" value="1" @checked(old('fy_reset', $business->fy_reset))>
                            <span>
                                Start again at 1 each April
                                <span class="help">The reset fires on the first document of the new year, not at midnight on 1 April.</span>
                            </span>
                        </label>
                    </div>
                </div>

                <p class="muted" style="margin:16px 0 0;font-size:12.5px">
                    The counters themselves are not editable here on purpose — they are handed out inside a locked
                    transaction shared with the handsets, and editing one by hand is how a series ends up with two
                    documents on the same number.
                </p>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn">{{ $business->exists ? 'Save changes' : 'Add business' }}</button>
                <a href="{{ $cancel }}" class="btn ghost">Cancel</a>
            </div>
        </section>
    </form>

    @if($business->exists)
        @php($load = $business->invoices_count.' '.Str::plural('document', $business->invoices_count).', '.$business->customers_count.' '.Str::plural('customer', $business->customers_count).' and '.$business->items_count.' catalogue '.Str::plural('entry', $business->items_count))

        <section class="card" style="margin-top:18px">
            <h2>Remove this business</h2>
            <div class="body">
                <p class="muted" style="margin:0;font-size:13px">
                    Removing {{ $business->name }} takes its {{ $load }} with it, here and on the handsets at their
                    next sync. Nothing is erased — the rows are marked deleted and keep their numbers.
                </p>
            </div>
            <div class="form-actions">
                <form method="POST" action="{{ route('admin.businesses.destroy', $business) }}" class="inline-form"
                      onsubmit="return confirm('Remove {{ $business->name }} along with its {{ $load }}? The handsets drop it on their next sync.')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn danger">Remove business</button>
                </form>
            </div>
        </section>
    @endif
@endsection
