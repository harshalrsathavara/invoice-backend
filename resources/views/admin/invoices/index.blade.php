@extends('admin.layout')
@section('title', 'Bills')

@section('content')
    <div class="page-head">
        <div>
            <div class="eyebrow">Billing</div>
            <h1>Bills &amp; invoices</h1>
            <div class="sub">Bills, quotations and challans share one list — they are the same document with a different name on top.</div>
        </div>
        <div class="head-actions">
            <a href="{{ route('admin.invoices.create') }}" class="btn">
                <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                New document
            </a>
        </div>
    </div>

    <form method="GET" class="filters">
        <div class="field">
            <label for="search">Search</label>
            <input id="search" type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Customer or bill no.">
        </div>
        <div class="field">
            <label for="business">Business</label>
            <select id="business" name="business">
                <option value="">All</option>
                @foreach($businesses as $b)
                    <option value="{{ $b->uuid }}" @selected(($filters['business'] ?? null) === $b->uuid)>{{ $b->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label for="doc_type">Kind</label>
            <select id="doc_type" name="doc_type">
                <option value="">All</option>
                @foreach(['bill' => 'Bills', 'quotation' => 'Quotations', 'challan' => 'Challans'] as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['doc_type'] ?? null) === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label for="status">Status</label>
            <select id="status" name="status">
                <option value="">Any</option>
                @foreach(['Unpaid', 'Partial', 'Paid', 'Cancelled'] as $value)
                    <option value="{{ $value }}" @selected(($filters['status'] ?? null) === $value)>{{ $value }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label for="from">From</label>
            <input id="from" type="date" name="from" value="{{ $filters['from'] ?? '' }}">
        </div>
        <div class="field">
            <label for="to">To</label>
            <input id="to" type="date" name="to" value="{{ $filters['to'] ?? '' }}">
        </div>
        @include('admin.partials.records-filter')
        <div class="actions">
            <button type="submit" class="btn">Filter</button>
            <a href="{{ route('admin.invoices.index') }}" class="btn ghost">Clear</a>
        </div>
    </form>

    <section class="card">
        <div class="body flush">
            <div class="scroller">
                <table>
                    <thead>
                    <tr>
                        <th>No.</th><th>Kind</th><th>Customer</th><th class="date">Date</th>
                        <th class="num">Total</th><th class="num">Paid</th><th class="num">Balance</th><th>Status</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($invoices as $invoice)
                        <tr class="{{ $invoice->trashed() ? 'is-deleted' : '' }}">
                            <td class="mono">
                                <a href="{{ route('admin.invoices.show', $invoice) }}">{{ $invoice->display_no }}</a>
                                @if($invoice->trashed())<span class="pill deleted">Deleted</span>@endif
                            </td>
                            <td><span class="pill {{ $invoice->doc_type }}">{{ ucfirst($invoice->doc_type) }}</span></td>
                            <td>
                                {{ $invoice->customer_name }}
                                <div class="muted" style="font-size:12px">{{ $invoice->business->name }}</div>
                            </td>
                            <td class="date">{{ $invoice->date?->format('d M Y') }}</td>
                            <td class="num strong"><x-money :value="$invoice->total" /></td>
                            <td class="num"><x-money :value="$invoice->paid_amount" /></td>
                            <td class="num {{ $invoice->balance > 0.001 ? 'strong' : 'muted' }}"><x-money :value="$invoice->balance" /></td>
                            <td><x-status-pill :status="$invoice->status" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><div class="empty"><strong>No documents match</strong>Try clearing the filters.</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    {{ $invoices->links() }}
@endsection
