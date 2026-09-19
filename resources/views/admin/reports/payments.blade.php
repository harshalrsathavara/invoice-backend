@extends('admin.layout')
@section('title', 'Payments received')

@section('content')
    @php use App\Services\Money; @endphp

    <div class="page-head">
        <div>
            <div class="eyebrow">Reports</div>
            <h1>Payments received</h1>
            <div class="sub">Receipts as they landed — the other half of the ledger from what was billed.</div>
        </div>
    </div>

    <form method="GET" class="filters">
        <div class="field">
            <label for="from">From</label>
            <input id="from" type="date" name="from" value="{{ $from->toDateString() }}">
        </div>
        <div class="field">
            <label for="to">To</label>
            <input id="to" type="date" name="to" value="{{ $to->toDateString() }}">
        </div>
        <div class="field">
            <label for="business">Business</label>
            <select id="business" name="business">
                <option value="">All</option>
                @foreach($businesses as $b)
                    <option value="{{ $b->uuid }}" @selected($business?->uuid === $b->uuid)>{{ $b->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label for="mode">Mode</label>
            <select id="mode" name="mode">
                <option value="">Any</option>
                @foreach(\App\Models\Payment::MODES as $value)
                    <option value="{{ $value }}" @selected($mode === $value)>{{ ucfirst($value) }}</option>
                @endforeach
            </select>
        </div>
        <div class="actions">
            <button type="submit" class="btn">Apply</button>
            <a href="{{ route('admin.reports.payments') }}" class="btn ghost">Clear</a>
        </div>
    </form>

    <div class="presets" style="margin:-6px 2px 18px">
        @foreach($presets as $label => [$presetFrom, $presetTo])
            <a href="{{ route('admin.reports.payments', array_filter(['from' => $presetFrom->toDateString(), 'to' => $presetTo->toDateString(), 'business' => $business?->uuid, 'mode' => $mode])) }}"
               class="{{ $from->toDateString() === $presetFrom->toDateString() && $to->toDateString() === $presetTo->toDateString() ? 'on' : '' }}">{{ $label }}</a>
        @endforeach
    </div>

    <div class="kpis">
        <div class="kpi emerald">
            <div class="top">
                <div class="label">Received</div>
                <div class="chip"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></div>
            </div>
            <div class="value">{{ Money::rupees($total) }}</div>
            <div class="foot">{{ $payments->count() }} {{ Str::plural('receipt', $payments->count()) }} · {{ $from->format('d M') }} — {{ $to->format('d M Y') }}</div>
        </div>

        @foreach($byMode->take(3) as $row)
            <div class="kpi {{ ['', 'amber', 'rose'][$loop->index] ?? '' }}">
                <div class="top">
                    <div class="label">{{ ucfirst($row['mode']) }}</div>
                    <div class="chip"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="2.5" y="5.5" width="19" height="13" rx="2.5"/><path d="M2.5 10h19"/></svg></div>
                </div>
                <div class="value">{{ Money::rupees($row['amount']) }}</div>
                <div class="foot">
                    <span class="delta">{{ $total > 0 ? round($row['amount'] / $total * 100) : 0 }}%</span>
                    of what came in
                </div>
            </div>
        @endforeach
    </div>

    @if($daily->isNotEmpty())
        <section class="card" style="margin-bottom:18px">
            <h2>
                Day by day
                <span class="hint">{{ $from->format('d M') }} — {{ $to->format('d M Y') }}</span>
            </h2>
            <div class="body">
                <x-area-chart :points="$daily" caption="Received per day" />
            </div>
        </section>
    @else
        <div class="notice warn">
            <div>That window is longer than three months, so the daily chart is left out — the receipts themselves are all below.</div>
        </div>
    @endif

    <section class="card">
        <h2>
            Every receipt
            <span class="hint">Newest first</span>
        </h2>
        <div class="body flush">
            <div class="scroller">
                <table>
                    <thead><tr><th class="date">Date</th><th>Against</th><th>Customer</th><th>Mode</th><th>Note</th><th class="num">Amount</th></tr></thead>
                    <tbody>
                    @forelse($payments as $payment)
                        <tr>
                            <td class="date">{{ $payment->date?->format('d M Y') }}</td>
                            <td class="mono">
                                @if($payment->invoice)
                                    <a href="{{ route('admin.invoices.show', $payment->invoice) }}">{{ $payment->invoice->display_no }}</a>
                                @else
                                    —
                                @endif
                            </td>
                            <td>
                                <div class="who-cell">
                                    <x-avatar :name="$payment->invoice?->customer_name ?? '?'" small />
                                    <div class="lines">
                                        <div>{{ $payment->invoice?->customer_name ?? 'Unknown' }}</div>
                                        <div>{{ $payment->invoice?->business?->name }}</div>
                                    </div>
                                </div>
                            </td>
                            <td><span class="pill quotation">{{ ucfirst($payment->mode) }}</span></td>
                            <td class="muted">{{ $payment->note ?: '—' }}</td>
                            <td class="num strong">{{ Money::rupees((float) $payment->amount) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><div class="empty"><strong>Nothing received in this window</strong>Try a wider date range.</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>
@endsection
