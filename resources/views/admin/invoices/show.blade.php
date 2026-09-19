@extends('admin.layout')
@section('title', $invoice->display_no)

@section('content')
    <div class="page-head">
        <div>
            <div class="eyebrow">{{ ucfirst($invoice->doc_type) }} · {{ $invoice->business->name }}</div>
            <h1>{{ $invoice->display_no }}</h1>
            <div class="sub">{{ $invoice->customer_name }} · {{ $invoice->date?->format('d M Y') }}</div>
        </div>
        <div class="head-actions">
            <x-status-pill :status="$invoice->status" />
            @unless($invoice->is_voided)
                <a href="{{ route('admin.invoices.edit', $invoice) }}" class="btn small">
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20h4L19 9a2.5 2.5 0 0 0-3.5-3.5L4.5 16.5z"/></svg>
                    Edit
                </a>
            @endunless
            <a href="{{ route('admin.invoices.index') }}" class="btn ghost small">Back to list</a>
        </div>
    </div>

    @if($invoice->is_voided)
        <div class="notice warn">
            <div>
                Cancelled {{ $invoice->voided_at?->format('d M Y') }}@if($invoice->void_reason) — {{ $invoice->void_reason }}@endif.
                The number stays used, so the series has no hole.
                <form method="POST" action="{{ route('admin.invoices.unvoid', $invoice) }}" class="inline-form" style="margin-left:8px">
                    @csrf
                    <button type="submit" class="btn ghost small">Reinstate it</button>
                </form>
            </div>
        </div>
    @endif

    @if($errors->any())
        <div class="notice errors"><div>{{ $errors->first() }}</div></div>
    @endif

    @if(\App\Models\InvoiceTax::hasMismatchedSplit($invoice->taxes))
        <div class="notice warn">CGST and SGST carry different rates on this bill. Usually a typo — each is normally half the headline rate.</div>
    @endif

    <div class="grid two">
        <section class="card">
            <h2>Items</h2>
            <div class="body flush">
                <div class="scroller">
                    <table>
                        <thead><tr><th>Particulars</th><th class="num">Qty</th><th class="num">Rate</th><th class="num">Amount</th></tr></thead>
                        <tbody>
                        @foreach($invoice->lines as $line)
                            <tr>
                                <td>{{ $line->particulars }}</td>
                                <td class="num">{{ rtrim(rtrim(number_format($line->quantity, 3, '.', ''), '0'), '.') }}</td>
                                <td class="num"><x-money :value="$line->rate" :symbol="false" /></td>
                                <td class="num strong"><x-money :value="$line->amount" :symbol="false" /></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="body">
                <dl class="totals">
                    <dt>Subtotal</dt><dd><x-money :value="$invoice->subtotal" :symbol="false" /></dd>

                    @if($invoice->discount_amount > 0)
                        <dt>Discount @if($invoice->discount_type === 'percent')({{ rtrim(rtrim(number_format($invoice->discount_value, 2), '0'), '.') }}%)@endif</dt>
                        <dd>−<x-money :value="$invoice->discount_amount" :symbol="false" /></dd>
                        <dt>Taxable amount</dt><dd><x-money :value="$invoice->taxable_amount" :symbol="false" /></dd>
                    @endif

                    @foreach($invoice->taxes as $tax)
                        <dt>{{ $tax->label }} @ {{ rtrim(rtrim(number_format($tax->percent, 3), '0'), '.') }}%</dt>
                        <dd><x-money :value="$invoice->taxAmountFor($tax)" :symbol="false" /></dd>
                    @endforeach

                    @if((float) $invoice->round_off !== 0.0)
                        <dt>Round off</dt><dd><x-money :value="$invoice->round_off" :symbol="false" /></dd>
                    @endif

                    <div class="grand">
                        <dt>Grand total</dt><dd><x-money :value="$invoice->total" /></dd>
                    </div>
                </dl>

                @if($invoice->amount_in_words)
                    <p class="muted" style="margin:16px 0 0;font-size:12.5px;max-width:52ch">{{ $invoice->amount_in_words }}</p>
                @endif
            </div>
        </section>

        <div class="grid">
            <section class="card">
                <h2>Payments received</h2>
                <div class="body flush">
                    <div class="scroller">
                        <table>
                            <thead><tr><th class="date">Date</th><th>Mode</th><th>Note</th><th class="num">Amount</th><th></th></tr></thead>
                            <tbody>
                            @forelse($invoice->payments as $payment)
                                <tr>
                                    <td class="date">{{ $payment->date?->format('d M Y') }}</td>
                                    <td>{{ ucfirst($payment->mode) }}</td>
                                    <td class="muted">{{ $payment->note ?: '—' }}</td>
                                    <td class="num strong"><x-money :value="$payment->amount" /></td>
                                    <td class="num">
                                        <form method="POST" action="{{ route('admin.payments.destroy', [$invoice, $payment]) }}" class="inline-form"
                                              onsubmit="return confirm('Remove this receipt? The balance will be recalculated from what is left.')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="row-drop" aria-label="Remove this receipt">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5"><div class="empty"><strong>Nothing received yet</strong>Record one below, or let a handset sync one up.</div></td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="body">
                    <dl class="totals">
                        <dt>Paid</dt><dd><x-money :value="$invoice->paid_amount" /></dd>
                        <div class="grand"><dt>Balance</dt><dd><x-money :value="$invoice->balance" /></dd></div>
                    </dl>
                </div>

                @if($invoice->doc_type === \App\Models\Invoice::TYPE_BILL && ! $invoice->is_voided)
                    <form method="POST" action="{{ route('admin.payments.store', $invoice) }}">
                        @csrf
                        <div class="body" style="border-top:1px solid var(--rule)">
                            <div class="form-grid">
                                <x-field name="amount" label="Record a receipt" type="number" required
                                         :value="round($invoice->balance, 2) > 0 ? round($invoice->balance, 2) : null"
                                         help="Defaults to the outstanding balance." />
                                <x-field name="date" label="On" type="date" :value="now()->toDateString()" />
                                <x-field name="mode" label="Mode" type="select">
                                    @foreach(\App\Models\Payment::MODES as $value)
                                        <option value="{{ $value }}" @selected(old('mode') === $value)>{{ ucfirst($value) }}</option>
                                    @endforeach
                                </x-field>
                                <x-field name="note" label="Note" placeholder="Cheque no., UPI ref…" />
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn">Record receipt</button>
                        </div>
                    </form>
                @endif
            </section>

            <section class="card">
                <h2>Document</h2>
                <div class="body">
                    <dl class="kv">
                        <dt>Reference</dt><dd class="mono">{{ $invoice->display_no }}</dd>
                        <dt>Raw number</dt><dd class="mono">{{ $invoice->bill_no }}</dd>
                        <dt>Kind</dt><dd>{{ ucfirst($invoice->doc_type) }}</dd>
                        <dt>Business</dt><dd><a href="{{ route('admin.businesses.show', $invoice->business) }}">{{ $invoice->business->name }}</a></dd>
                        @if($invoice->notes)
                            <dt>Note</dt><dd>{{ $invoice->notes }}</dd>
                        @endif
                        @if($invoice->converted_from_uuid)
                            <dt>Converted from</dt><dd class="mono">{{ $invoice->converted_from_uuid }}</dd>
                        @endif
                        <dt>UUID</dt><dd class="mono" style="font-size:12px;word-break:break-all">{{ $invoice->uuid }}</dd>
                        <dt>Last synced</dt><dd>{{ $invoice->updated_at?->format('d M Y, H:i') }}</dd>
                    </dl>
                </div>

                @unless($invoice->is_voided)
                    <form method="POST" action="{{ route('admin.invoices.void', $invoice) }}"
                          onsubmit="return confirm('Cancel {{ $invoice->display_no }}? The number stays used and the document stays visible.')">
                        @csrf
                        <div class="body" style="border-top:1px solid var(--rule)">
                            <x-field name="reason" label="Cancel this document" required
                                     placeholder="Why it is being cancelled"
                                     help="Recorded against the document, so a cancellation is never unexplained." />
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn danger">Cancel document</button>
                        </div>
                    </form>
                @endunless
            </section>
        </div>
    </div>
@endsection
