@extends('admin.layout')
@section('title', 'Statement · '.$customer->name)

@section('content')
    @php use App\Services\Money; @endphp

    <div class="page-head no-print">
        <div>
            <div class="eyebrow">Customer</div>
            <h1>Statement</h1>
            <div class="sub">Every bill and receipt in the order it happened, with a running balance. Built to be printed and handed over.</div>
        </div>
        <div class="head-actions">
            <button type="button" class="btn" onclick="window.print()">
                <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9V3h12v6"/><rect x="3" y="9" width="18" height="7" rx="2"/><path d="M6 14h12v7H6z"/></svg>
                Print
            </button>
            <a href="{{ route('admin.customers.show', $customer) }}" class="btn ghost">Back</a>
        </div>
    </div>

    <div class="statement">
        <div class="sheet">
            <div class="masthead">
                <div>
                    <h2>{{ $business->name }}</h2>
                    @if($business->tagline)<div class="addr">{{ $business->tagline }}</div>@endif
                    <div class="addr">
                        {{ $business->address }}
                        @if($business->mobile)<br>{{ $business->mobile }}@endif
                        @if($business->gst_number)<br>GSTIN {{ $business->gst_number }}@endif
                    </div>
                </div>
                <div class="right">
                    <div class="cap">Statement of account</div>
                    <div class="muted" style="font-size:12.5px;margin-top:4px">As at {{ now()->format('d M Y') }}</div>
                </div>
            </div>

            <div class="to">
                <div class="cap">To</div>
                <div class="name">{{ $customer->name }}</div>
                @if($customer->address)<div class="muted" style="font-size:12.5px">{{ $customer->address }}</div>@endif
                @if($customer->phone)<div class="muted" style="font-size:12.5px">{{ $customer->phone }}</div>@endif
                @if($customer->gst_number)<div class="muted" style="font-size:12.5px">GSTIN {{ $customer->gst_number }}</div>@endif
            </div>

            <div class="scroller">
                <table>
                    <thead>
                    <tr>
                        <th class="date">Date</th><th>Particulars</th><th>Reference</th>
                        <th class="num">Billed</th><th class="num">Received</th><th class="num">Balance</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($entries as $entry)
                        <tr>
                            <td class="date">{{ \Carbon\Carbon::parse($entry['date'])->format('d M Y') }}</td>
                            <td>
                                {{ $entry['kind'] === 'bill' ? 'Bill raised' : 'Payment received' }}
                                @if($entry['detail'])<div class="muted" style="font-size:12px">{{ $entry['detail'] }}</div>@endif
                            </td>
                            <td class="mono">{{ $entry['ref'] }}</td>
                            <td class="num">{{ $entry['debit'] > 0 ? Money::amount($entry['debit']) : '—' }}</td>
                            <td class="num">{{ $entry['credit'] > 0 ? Money::amount($entry['credit']) : '—' }}</td>
                            <td class="num strong">{{ Money::amount($entry['balance']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><div class="empty"><strong>Nothing on this account</strong>No live bill has been raised for {{ $customer->name }}.</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            <div class="closing">
                <div class="cap">{{ $closing > 0 ? 'Balance due' : ($closing < 0 ? 'In credit' : 'Settled in full') }}</div>
                <div class="fig">{{ Money::rupees(abs($closing)) }}</div>
            </div>

            <div class="foot-note">
                Quotations and challans are not included — only bills and the receipts against them.
                Cancelled bills are excluded, which is why a number may be missing from the sequence.
                @if($business->upi_id)
                    <br>Payment may be made to UPI <strong>{{ $business->upi_id }}</strong>.
                @endif
            </div>
        </div>
    </div>
@endsection
