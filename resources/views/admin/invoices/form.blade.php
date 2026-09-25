@extends('admin.layout')
@section('title', $invoice->exists ? 'Edit '.$invoice->display_no : 'New document')

@section('content')
    @php
        use App\Models\Invoice;
        use App\Services\Money;

        $isEdit = $invoice->exists;
        // old() wins on a validation bounce, so nothing typed is ever lost.
        $formLines = old('lines', $lines->map(fn ($l) => [
            'uuid' => $l['uuid'] ?? ($l->uuid ?? null),
            'particulars' => $l['particulars'] ?? $l->particulars,
            'quantity' => $l['quantity'] ?? $l->quantity,
            'rate' => $l['rate'] ?? $l->rate,
            'gst_rate' => $l['gst_rate'] ?? ($l->gst_rate ?? 0),
        ])->all());
        $formTaxes = old('taxes', $taxes->map(fn ($t) => [
            'uuid' => $t->uuid, 'label' => $t->label, 'percent' => $t->percent,
        ])->all());
    @endphp

    <div class="page-head">
        <div>
            <div class="eyebrow">{{ $isEdit ? 'Document' : 'Billing' }}</div>
            <h1>{{ $isEdit ? 'Edit '.$invoice->display_no : 'New document' }}</h1>
            <div class="sub">
                @if($isEdit)
                    The number and the receipts already recorded are kept. Handsets pick the change up on their next sync.
                @else
                    The number is allocated when you save, by the same counter the handsets use.
                @endif
            </div>
        </div>
        <a href="{{ $isEdit ? route('admin.invoices.show', $invoice) : route('admin.invoices.index') }}" class="btn ghost small">Cancel</a>
    </div>

    @if($errors->any())
        <div class="notice errors">
            <div>
                {{ $errors->count() === 1 ? 'One field needs attention' : $errors->count().' fields need attention' }} —
                {{ $errors->first() }}
            </div>
        </div>
    @endif

    <form method="POST" action="{{ $isEdit ? route('admin.invoices.update', $invoice) : route('admin.invoices.store') }}"
          id="doc-form" data-doc-form>
        @csrf
        @if($isEdit) @method('PUT') @endif

        <div class="grid wide">
            <div class="grid">
                <section class="card">
                    <h2>Heading</h2>
                    <div class="body">
                        <div class="form-grid">
                            @if($isEdit)
                                <div class="fieldset">
                                    <label>Business</label>
                                    <div class="who-cell">
                                        <x-avatar :name="$business->name" small />
                                        <span>{{ $business->name }}</span>
                                    </div>
                                </div>
                                <div class="fieldset">
                                    <label>Number</label>
                                    <div class="strong mono" style="padding-top:7px">{{ $invoice->display_no }}</div>
                                    <div class="help">Frozen when it was raised. Editing never renumbers a document.</div>
                                </div>
                            @else
                                <x-field name="business_uuid" label="Business" type="select" required>
                                    @foreach($businesses as $b)
                                        <option value="{{ $b->uuid }}" @selected(old('business_uuid', $business?->uuid) === $b->uuid)>{{ $b->name }}</option>
                                    @endforeach
                                </x-field>
                                <x-field name="doc_type" label="Kind" type="select" required>
                                    @foreach(['bill' => 'Bill', 'quotation' => 'Quotation', 'challan' => 'Challan'] as $value => $label)
                                        <option value="{{ $value }}" @selected(old('doc_type', $invoice->doc_type) === $value)>{{ $label }}</option>
                                    @endforeach
                                </x-field>
                            @endif

                            <x-field name="customer_name" label="Customer" :value="$invoice->customer_name" required
                                     placeholder="Their name as it should print" />
                            <x-field name="date" label="Date" :value="$invoice->exists ? $invoice->date?->format('Y-m-d') : now()->toDateString()" type="date" required />
                            <x-field name="notes" label="Note" :value="$invoice->notes" type="textarea" span
                                     help="Printed on the document, under the items." />
                        </div>
                    </div>
                </section>

                <section class="card">
                    <h2>
                        Items
                        <button type="button" class="btn ghost small" data-add-line>
                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                            Add line
                        </button>
                    </h2>
                    <div class="body flush">
                        <div class="scroller lines-editor">
                            <table>
                                <thead>
                                <tr>
                                    <th style="width:42%">Particulars</th>
                                    <th style="width:14%">Qty</th>
                                    <th style="width:19%">Rate</th>
                                    <th style="width:9%" class="num">GST %</th>
                                    <th style="width:16%" class="num">Amount</th>
                                    <th style="width:6%"></th>
                                </tr>
                                </thead>
                                <tbody data-lines>
                                @foreach($formLines as $i => $line)
                                    <tr data-line-row>
                                        <td>
                                            <input type="hidden" name="lines[{{ $i }}][uuid]" value="{{ $line['uuid'] ?? '' }}">
                                            <input type="text" name="lines[{{ $i }}][particulars]" value="{{ $line['particulars'] ?? '' }}"
                                                   list="catalogue" placeholder="What was done" aria-label="Particulars">
                                        </td>
                                        <td><input type="number" step="any" name="lines[{{ $i }}][quantity]" value="{{ $line['quantity'] ?? '' }}" data-qty aria-label="Quantity"></td>
                                        <td><input type="number" step="any" name="lines[{{ $i }}][rate]" value="{{ $line['rate'] ?? '' }}" data-rate aria-label="Rate"></td>
                                        {{-- The slab this line is charged at. Comes from the
                                             catalogue item on the handset; typed here. --}}
                                        <td><input type="number" step="any" min="0" max="100" name="lines[{{ $i }}][gst_rate]" value="{{ $line['gst_rate'] ?? '' }}" data-gst placeholder="0" aria-label="GST percent"></td>
                                        <td class="num"><span class="amount" data-amount>0.00</span></td>
                                        <td>
                                            <button type="button" class="row-drop" data-drop-line aria-label="Remove this line">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <section class="card">
                    <h2>
                        Tax, discount and rounding
                        <button type="button" class="btn ghost small" data-add-tax>
                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                            Add tax row
                        </button>
                    </h2>
                    <div class="body">
                        <div style="display:grid;gap:10px" data-taxes>
                            @foreach($formTaxes as $i => $tax)
                                <div class="tax-row" data-tax-row>
                                    <input type="hidden" name="taxes[{{ $i }}][uuid]" value="{{ $tax['uuid'] ?? '' }}">
                                    <input type="text" name="taxes[{{ $i }}][label]" value="{{ $tax['label'] ?? '' }}" placeholder="CGST" aria-label="Tax label">
                                    <input type="number" step="any" name="taxes[{{ $i }}][percent]" value="{{ $tax['percent'] ?? '' }}" placeholder="9" data-percent aria-label="Percent">
                                    <button type="button" class="row-drop" data-drop-tax aria-label="Remove this tax row">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                                    </button>
                                </div>
                            @endforeach
                        </div>

                        @if(empty($formTaxes))
                            <p class="muted" style="margin:0;font-size:12.5px">No tax on this document. Add a row for CGST and another for SGST — each normally half the headline rate.</p>
                        @endif

                        <div class="form-grid" style="margin-top:16px">
                            <x-field name="discount_type" label="Discount" type="select">
                                @foreach(['none' => 'No discount', 'percent' => 'Percent of subtotal', 'amount' => 'Flat amount'] as $value => $label)
                                    <option value="{{ $value }}" @selected(old('discount_type', $invoice->discount_type ?? 'none') === $value)>{{ $label }}</option>
                                @endforeach
                            </x-field>
                            <x-field name="discount_value" label="Discount value" :value="$invoice->discount_value ?? 0" type="number" />
                            <x-field name="round_off" label="Round off" :value="$invoice->round_off ?? 0" type="number"
                                     help="Added to the total. May be negative." />
                        </div>
                    </div>
                </section>
            </div>

            <div>
                <section class="card live-total">
                    <h2>As it stands</h2>
                    <div class="body">
                        <dl class="totals" style="max-width:none;margin-left:0">
                            <dt>Subtotal</dt><dd data-sum-subtotal>0.00</dd>
                            <dt data-sum-discount-label>Discount</dt><dd data-sum-discount>0.00</dd>
                            <dt>Taxable</dt><dd data-sum-taxable>0.00</dd>
                            <dt data-sum-tax-label>Tax</dt><dd data-sum-tax>0.00</dd>
                            <dt>Round off</dt><dd data-sum-round>0.00</dd>
                            <div class="grand">
                                <dt>Total</dt><dd><span data-sum-total>₹0.00</span></dd>
                            </div>
                        </dl>

                        <p class="muted" style="margin:14px 0 0;font-size:12px">
                            Worked out in the browser as you type. The server recomputes it on save — that figure, not this one,
                            is what gets stored.
                        </p>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn">{{ $isEdit ? 'Save document' : 'Raise document' }}</button>
                        <a href="{{ $isEdit ? route('admin.invoices.show', $invoice) : route('admin.invoices.index') }}" class="btn ghost">Cancel</a>
                    </div>
                </section>
            </div>
        </div>
    </form>

    {{-- Suggestions for the particulars field, the same catalogue the app offers. --}}
    <datalist id="catalogue">
        @foreach(($business?->items() ?? \App\Models\Item::query())->orderBy('name')->pluck('name')->unique() as $name)
            <option value="{{ $name }}"></option>
        @endforeach
    </datalist>

    <template data-line-template>
        <tr data-line-row>
            <td>
                <input type="hidden" name="lines[__i__][uuid]" value="">
                <input type="text" name="lines[__i__][particulars]" list="catalogue" placeholder="What was done" aria-label="Particulars">
            </td>
            <td><input type="number" step="any" name="lines[__i__][quantity]" value="1" data-qty aria-label="Quantity"></td>
            <td><input type="number" step="any" name="lines[__i__][rate]" value="0" data-rate aria-label="Rate"></td>
            <td><input type="number" step="any" min="0" max="100" name="lines[__i__][gst_rate]" value="" data-gst placeholder="0" aria-label="GST percent"></td>
            <td class="num"><span class="amount" data-amount>0.00</span></td>
            <td>
                <button type="button" class="row-drop" data-drop-line aria-label="Remove this line">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                </button>
            </td>
        </tr>
    </template>

    <template data-tax-template>
        <div class="tax-row" data-tax-row>
            <input type="hidden" name="taxes[__i__][uuid]" value="">
            <input type="text" name="taxes[__i__][label]" placeholder="CGST" aria-label="Tax label">
            <input type="number" step="any" name="taxes[__i__][percent]" placeholder="9" data-percent aria-label="Percent">
            <button type="button" class="row-drop" data-drop-tax aria-label="Remove this tax row">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </button>
        </div>
    </template>
@endsection
