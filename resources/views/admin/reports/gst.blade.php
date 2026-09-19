@extends('admin.layout')
@section('title', 'GST summary')

@section('content')
    @php use App\Services\Money; @endphp

    <div class="page-head">
        <div>
            <div class="eyebrow">Reports</div>
            <h1>GST for a period</h1>
            <div class="sub">Tax charged between two dates — the figures a return is filed from. Cancelled documents are excluded.</div>
        </div>
        <div class="head-actions no-print">
            <button type="button" class="btn ghost small" onclick="window.print()">Print</button>
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
        <div class="actions">
            <button type="submit" class="btn">Apply</button>
        </div>
    </form>

    <div class="presets no-print" style="margin:-6px 2px 18px">
        @foreach($presets as $label => [$presetFrom, $presetTo])
            <a href="{{ route('admin.reports.gst', array_filter(['from' => $presetFrom->toDateString(), 'to' => $presetTo->toDateString(), 'business' => $business?->uuid])) }}"
               class="{{ $from->toDateString() === $presetFrom->toDateString() && $to->toDateString() === $presetTo->toDateString() ? 'on' : '' }}">{{ $label }}</a>
        @endforeach
    </div>

    <div class="kpis">
        <div class="kpi">
            <div class="top">
                <div class="label">Tax charged</div>
                <div class="chip"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"><path d="M19 5 5 19"/><circle cx="7.5" cy="7.5" r="2.5"/><circle cx="16.5" cy="16.5" r="2.5"/></svg></div>
            </div>
            <div class="value">{{ Money::rupees($summary['total_tax']) }}</div>
            <div class="foot">{{ $from->format('d M Y') }} — {{ $to->format('d M Y') }}</div>
        </div>
        <div class="kpi emerald">
            <div class="top">
                <div class="label">Taxable value</div>
                <div class="chip"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16M4 12h16M4 17h10"/></svg></div>
            </div>
            <div class="value">{{ Money::rupees($summary['total_taxable_value']) }}</div>
            <div class="foot">Counting each taxed document once</div>
        </div>
        <div class="kpi amber">
            <div class="top">
                <div class="label">Billed in period</div>
                <div class="chip"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2h9l5 5v15H6z"/><path d="M15 2v5h5"/></svg></div>
            </div>
            <div class="value">{{ Money::rupees($summary['total_billed']) }}</div>
            <div class="foot">{{ $summary['bill_count'] }} live {{ Str::plural('document', $summary['bill_count']) }}</div>
        </div>
    </div>

    <section class="card">
        <h2>By rate</h2>
        <div class="body flush">
            <div class="scroller">
                <table>
                    <thead><tr><th>Label</th><th class="num">Rate</th><th class="num">Taxable</th><th class="num">Tax</th><th class="num">Documents</th></tr></thead>
                    <tbody>
                    @forelse($gst as $line)
                        <tr>
                            <td class="strong">{{ $line['label'] }}</td>
                            <td class="num">{{ rtrim(rtrim(number_format($line['percent'], 3), '0'), '.') }}%</td>
                            <td class="num">{{ Money::rupees($line['taxable_amount']) }}</td>
                            <td class="num strong">{{ Money::rupees($line['tax_amount']) }}</td>
                            <td class="num muted">{{ $line['bill_count'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><div class="empty"><strong>No tax charged in this period</strong>Either nothing was billed with GST, or the window is wrong.</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($gst->isNotEmpty())
            <div class="body">
                <p class="muted" style="margin:0;font-size:12.5px">
                    The taxable column repeats per row on purpose — CGST and SGST are charged on the same value.
                    Counting each taxed document once gives <strong>{{ Money::rupees($summary['total_taxable_value']) }}</strong>.
                </p>
            </div>
        @endif
    </section>

    @if($bills->isNotEmpty())
        <section class="card" style="margin-top:18px">
            <h2>
                Document by document
                <span class="hint">{{ $bills->count() }} taxed {{ Str::plural('document', $bills->count()) }}</span>
            </h2>
            <div class="body flush">
                <div class="scroller">
                    <table>
                        <thead><tr><th>No.</th><th class="date">Date</th><th>Customer</th><th class="num">Taxable</th><th class="num">Tax</th><th class="num">Total</th></tr></thead>
                        <tbody>
                        @foreach($bills as $bill)
                            <tr>
                                <td class="mono"><a href="{{ route('admin.invoices.show', $bill) }}">{{ $bill->display_no }}</a></td>
                                <td class="date">{{ $bill->date?->format('d M Y') }}</td>
                                <td>{{ $bill->customer_name }}</td>
                                <td class="num">{{ Money::rupees($bill->taxable_amount) }}</td>
                                <td class="num">{{ Money::rupees($bill->total_tax) }}</td>
                                <td class="num strong">{{ Money::rupees((float) $bill->total) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    @endif
@endsection
