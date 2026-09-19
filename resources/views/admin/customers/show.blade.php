@extends('admin.layout')
@section('title', $customer->name)

@section('content')
    @php
        $billed = $invoices->where('doc_type', 'bill')->whereNull('voided_at')->sum(fn($i) => (float) $i->total);
        $collected = $invoices->where('doc_type', 'bill')->whereNull('voided_at')->sum(fn($i) => (float) $i->paid_amount);
    @endphp

    <div class="page-head">
        <div>
            <div class="eyebrow">Customer · {{ $customer->business->name }}</div>
            <h1>{{ $customer->name }}</h1>
            <div class="sub">{{ $customer->phone ?: 'No phone recorded' }}</div>
        </div>
        <div class="head-actions">
            <a href="{{ route('admin.customers.statement', $customer) }}" class="btn ghost small">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2h9l5 5v15H6z"/><path d="M15 2v5h5"/><path d="M10 12h5M10 16h5"/></svg>
                Statement
            </a>
            <a href="{{ route('admin.customers.edit', $customer) }}" class="btn small">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20h4L19 9a2.5 2.5 0 0 0-3.5-3.5L4.5 16.5z"/></svg>
                Edit
            </a>
            <a href="{{ route('admin.customers.index') }}" class="btn ghost small">Back to ledger</a>
        </div>
    </div>

    <div class="kpis">
        <div class="kpi rose">
            <div class="top">
                <div class="label">Outstanding</div>
                <div class="chip">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3.5 21 19H3z"/><path d="M12 9.5v4M12 16.5h.01"/></svg>
                </div>
            </div>
            <div class="value">{{ \App\Services\Money::rupees($billed - $collected) }}</div>
            <div class="foot">Owed on their live bills</div>
        </div>

        <div class="kpi emerald">
            <div class="top">
                <div class="label">Collected</div>
                <div class="chip">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                </div>
            </div>
            <div class="value">{{ \App\Services\Money::rupees($collected) }}</div>
            @php $rate = $billed > 0 ? $collected / $billed * 100 : 0; @endphp
            <div class="foot">{{ number_format($rate, 1) }}% of what they were billed</div>
            <div class="meter"><i style="width: {{ min(100, round($rate)) }}%"></i></div>
        </div>

        <div class="kpi">
            <div class="top">
                <div class="label">Billed</div>
                <div class="chip">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2h9l5 5v15H6z"/><path d="M15 2v5h5"/><path d="M10 12h5M10 16h5"/></svg>
                </div>
            </div>
            <div class="value">{{ \App\Services\Money::rupees($billed) }}</div>
            <div class="foot">{{ $invoices->count() }} {{ Str::plural('document', $invoices->count()) }} on file</div>
        </div>
    </div>
    </div>

    <div class="grid two">
        <section class="card">
            <h2>Their documents</h2>
            <div class="body flush">
                <div class="scroller">
                    <table>
                        <thead><tr><th>No.</th><th class="date">Date</th><th class="num">Total</th><th class="num">Balance</th><th>Status</th></tr></thead>
                        <tbody>
                        @forelse($invoices as $invoice)
                            <tr>
                                <td class="mono"><a href="{{ route('admin.invoices.show', $invoice) }}">{{ $invoice->display_no }}</a></td>
                                <td class="date">{{ $invoice->date?->format('d M Y') }}</td>
                                <td class="num strong"><x-money :value="$invoice->total" /></td>
                                <td class="num"><x-money :value="$invoice->balance" /></td>
                                <td><x-status-pill :status="$invoice->status" /></td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><div class="empty"><strong>Never billed</strong>This name is on the customer list but has no documents.</div></td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section class="card">
            <h2>On file</h2>
            <div class="body">
                <dl class="kv">
                    <dt>Phone</dt><dd class="mono">{{ $customer->phone ?: '—' }}</dd>
                    <dt>Address</dt><dd>{{ $customer->address ?: '—' }}</dd>
                    <dt>GSTIN</dt><dd class="mono">{{ $customer->gst_number ?: '—' }}</dd>
                    <dt>Business</dt><dd><a href="{{ route('admin.businesses.show', $customer->business) }}">{{ $customer->business->name }}</a></dd>
                    <dt>UUID</dt><dd class="mono" style="font-size:12px;word-break:break-all">{{ $customer->uuid }}</dd>
                </dl>
            </div>
        </section>
    </div>
@endsection
