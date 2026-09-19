@extends('admin.layout')
@section('title', $business->name)

@section('content')
    <div class="page-head">
        <div class="who-cell">
            @if($business->logo_path)
                <img class="image-preview" style="max-height:48px"
                     src="{{ route('admin.businesses.image', [$business, 'logo']) }}"
                     alt="{{ $business->name }} logo">
            @endif
            <div class="lines">
                <div class="eyebrow">Business</div>
                <h1>{{ $business->name }}</h1>
                <div class="sub">{{ $business->tagline }}</div>
            </div>
        </div>
        <div class="head-actions">
            <a href="{{ route('admin.items.index', ['business' => $business->uuid]) }}" class="btn ghost small">Catalogue</a>
            <a href="{{ route('admin.invoices.index', ['business' => $business->uuid]) }}" class="btn ghost small">Its bills</a>
            <a href="{{ route('admin.businesses.edit', $business) }}" class="btn small">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20h4L19 9a2.5 2.5 0 0 0-3.5-3.5L4.5 16.5z"/></svg>
                Edit details
            </a>
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
            <div class="value">{{ \App\Services\Money::rupees($summary['total_outstanding']) }}</div>
            <div class="foot">Owed on live bills</div>
        </div>

        <div class="kpi emerald">
            <div class="top">
                <div class="label">Collected</div>
                <div class="chip">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                </div>
            </div>
            <div class="value">{{ \App\Services\Money::rupees($summary['total_collected']) }}</div>
            @php $rate = $summary['total_billed'] > 0 ? $summary['total_collected'] / $summary['total_billed'] * 100 : 0; @endphp
            <div class="foot">{{ number_format($rate, 1) }}% of everything billed</div>
            <div class="meter"><i style="width: {{ min(100, round($rate)) }}%"></i></div>
        </div>

        <div class="kpi">
            <div class="top">
                <div class="label">Billed</div>
                <div class="chip">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2h9l5 5v15H6z"/><path d="M15 2v5h5"/><path d="M10 12h5M10 16h5"/></svg>
                </div>
            </div>
            <div class="value">{{ \App\Services\Money::rupees($summary['total_billed']) }}</div>
            <div class="foot">{{ $summary['bill_count'] }} live {{ Str::plural('bill', $summary['bill_count']) }}</div>
        </div>

        <div class="kpi amber">
            <div class="top">
                <div class="label">Next bill no.</div>
                <div class="chip">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16M4 12h16M4 17h10"/></svg>
                </div>
            </div>
            <div class="value" style="font-size:23px">{{ $business->formatBillNo($business->next_bill_no) }}</div>
            <div class="foot">{{ $business->fy_reset ? 'Resets each 1 April' : 'Runs continuously' }}</div>
        </div>
    </div>

    <div class="grid two">
        <section class="card">
            <h2>GST charged</h2>
            <div class="body flush">
                <div class="scroller">
                    <table>
                        <thead><tr><th>Label</th><th class="num">Rate</th><th class="num">Taxable</th><th class="num">Tax</th><th class="num">Bills</th></tr></thead>
                        <tbody>
                        @forelse($gst as $line)
                            <tr>
                                <td class="strong">{{ $line['label'] }}</td>
                                <td class="num">{{ rtrim(rtrim(number_format($line['percent'], 3), '0'), '.') }}%</td>
                                <td class="num"><x-money :value="$line['taxable_amount']" /></td>
                                <td class="num strong"><x-money :value="$line['tax_amount']" /></td>
                                <td class="num muted">{{ $line['bill_count'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><div class="empty"><strong>No tax charged</strong>These bills carry no GST rows.</div></td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($gst->isNotEmpty())
                <div class="body">
                    <p class="muted" style="margin:0;font-size:12.5px">
                        The taxable column repeats per row on purpose — CGST and SGST are charged on the same value. Counting each taxed bill once gives <strong><x-money :value="$summary['total_taxable_value']" /></strong>.
                    </p>
                </div>
            @endif
        </section>

        <section class="card">
            <h2>Company details</h2>
            <div class="body">
                <dl class="kv">
                    <dt>Address</dt><dd>{{ $business->address ?: '—' }}</dd>
                    <dt>Mobile</dt><dd>{{ $business->mobile ?: '—' }}</dd>
                    <dt>GSTIN</dt><dd class="mono">{{ $business->gst_number ?: '—' }}</dd>
                    <dt>UPI</dt><dd class="mono">{{ $business->upi_id ?: '—' }}</dd>
                    <dt>Jurisdiction</dt><dd>{{ $business->jurisdiction_text ?: '—' }}</dd>
                    <dt>Quotations</dt><dd class="mono">next {{ $business->next_quote_no }}</dd>
                    <dt>Challans</dt><dd class="mono">next {{ $business->next_challan_no }}</dd>
                    @if($business->terms_text)
                        <dt>Terms</dt><dd>{{ $business->terms_text }}</dd>
                    @endif
                    <dt>UUID</dt><dd class="mono" style="font-size:12px;word-break:break-all">{{ $business->uuid }}</dd>
                </dl>
            </div>
        </section>
    </div>

    <div class="grid two" style="margin-top:18px">
        <section class="card">
            <h2>Top customers</h2>
            <div class="body flush">
                <div class="scroller">
                    <table>
                        <thead><tr><th>Customer</th><th class="num">Billed</th><th class="num">Bills</th></tr></thead>
                        <tbody>
                        @forelse($topCustomers as $row)
                            <tr><td>{{ $row['name'] }}</td><td class="num strong"><x-money :value="$row['amount']" /></td><td class="num muted">{{ $row['count'] }}</td></tr>
                        @empty
                            <tr><td colspan="3"><div class="empty"><strong>No bills yet</strong></div></td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section class="card">
            <h2>Where the money comes from</h2>
            <div class="body flush">
                <div class="scroller">
                    <table>
                        <thead><tr><th>Work</th><th class="num">Value</th><th class="num">Times</th></tr></thead>
                        <tbody>
                        @forelse($topWork as $row)
                            <tr><td>{{ $row['name'] }}</td><td class="num strong"><x-money :value="$row['amount']" /></td><td class="num muted">{{ $row['count'] }}</td></tr>
                        @empty
                            <tr><td colspan="3"><div class="empty"><strong>No line items yet</strong></div></td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
@endsection
