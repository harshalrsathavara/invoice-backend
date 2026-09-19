@extends('admin.layout')
@section('title', 'Pending payments')

@section('content')
    @php use App\Services\Money; @endphp

    <div class="page-head">
        <div>
            <div class="eyebrow">Reports</div>
            <h1>Pending payments</h1>
            <div class="sub">
                Unpaid bills older than {{ $minDays }} {{ Str::plural('day', $minDays) }}, oldest first, with a phone number beside each.
                Age is measured from the bill date — there are no payment terms in this system.
            </div>
        </div>
        <div class="head-actions no-print">
            <button type="button" class="btn ghost small" onclick="window.print()">Print</button>
        </div>
    </div>

    <form method="GET" class="filters">
        <div class="field">
            <label for="days">Older than</label>
            <select id="days" name="days">
                @foreach([0 => 'Any unpaid', 30 => '30 days', 60 => '60 days', 90 => '90 days', 180 => '180 days'] as $value => $label)
                    <option value="{{ $value }}" @selected($minDays === $value)>{{ $label }}</option>
                @endforeach
            </select>
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
        <div class="actions">
            <button type="submit" class="btn">Apply</button>
            <a href="{{ route('admin.reports.overdue') }}" class="btn ghost">Clear</a>
        </div>
    </form>

    <div class="kpis">
        <div class="kpi rose">
            <div class="top">
                <div class="label">To chase</div>
                <div class="chip"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3.5 21 19H3z"/><path d="M12 9.5v4M12 16.5h.01"/></svg></div>
            </div>
            <div class="value">{{ Money::rupees($total) }}</div>
            <div class="foot">{{ $rows->count() }} {{ Str::plural('bill', $rows->count()) }} over {{ $minDays }} {{ Str::plural('day', $minDays) }} old</div>
        </div>
        @if($rows->isNotEmpty())
            <div class="kpi amber">
                <div class="top">
                    <div class="label">Oldest</div>
                    <div class="chip"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/></svg></div>
                </div>
                <div class="value">{{ $rows->first()['age'] }} days</div>
                <div class="foot">{{ $rows->first()['invoice']->customer_name }} · {{ Money::rupees($rows->first()['owed']) }}</div>
            </div>
            <div class="kpi">
                <div class="top">
                    <div class="label">Biggest</div>
                    <div class="chip"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19V9M10 19V5M16 19v-7M22 19H2"/></svg></div>
                </div>
                @php $biggest = $rows->sortByDesc('owed')->first(); @endphp
                <div class="value">{{ Money::rupees($biggest['owed']) }}</div>
                <div class="foot">{{ $biggest['invoice']->customer_name }} · {{ $biggest['age'] }} days</div>
            </div>
        @endif
    </div>

    <section class="card">
        <div class="body flush">
            <div class="scroller">
                <table>
                    <thead>
                    <tr><th>No.</th><th class="date">Date</th><th class="num">Age</th><th>Customer</th><th>Phone</th><th class="num">Owed</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                    @forelse($rows as $row)
                        @php $invoice = $row['invoice']; @endphp
                        <tr>
                            <td class="mono"><a href="{{ route('admin.invoices.show', $invoice) }}">{{ $invoice->display_no }}</a></td>
                            <td class="date">{{ $invoice->date?->format('d M Y') }}</td>
                            <td class="num strong" style="{{ $row['age'] >= 90 ? 'color:var(--st-crit-ink)' : '' }}">{{ $row['age'] }}d</td>
                            <td>
                                <div class="who-cell">
                                    <x-avatar :name="$invoice->customer_name" small />
                                    <div class="lines">
                                        <div>
                                            @if($row['customer'])
                                                <a href="{{ route('admin.customers.show', $row['customer']) }}">{{ $invoice->customer_name }}</a>
                                            @else
                                                {{ $invoice->customer_name }}
                                            @endif
                                        </div>
                                        <div>{{ $invoice->business->name }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="mono">
                                @if($row['phone'])
                                    <a href="tel:{{ preg_replace('/\s+/', '', $row['phone']) }}">{{ $row['phone'] }}</a>
                                @else
                                    <span class="muted">Not on file</span>
                                @endif
                            </td>
                            <td class="num strong">{{ Money::rupees($row['owed']) }}</td>
                            <td><x-status-pill :status="$invoice->status" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><div class="empty"><strong>Nothing to chase</strong>No unpaid bill is older than {{ $minDays }} {{ Str::plural('day', $minDays) }}.</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>
@endsection
