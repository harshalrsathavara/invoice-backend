@extends('admin.layout')
@section('title', 'Dashboard')

@section('content')
    @php
        use App\Services\Money;

        // Every figure below is computed from data that exists. Where there is no
        // history to compare against, the card carries a share or a rate instead
        // of an invented trend.
        $thisMonth = (float) $monthly->last()['billed'];
        $prevMonth = $monthly->count() > 1 ? (float) $monthly[$monthly->count() - 2]['billed'] : null;
        $momChange = ($prevMonth !== null && $prevMonth > 0)
            ? round(($thisMonth - $prevMonth) / $prevMonth * 100)
            : null;
        // The last bucket is the month we are standing in, so it is incomplete.
        $lastIsCurrent = $monthly->last()['month'] === now()->format('Y-m');

        $collectionRate = $summary['total_billed'] > 0
            ? $summary['total_collected'] / $summary['total_billed'] * 100
            : 0;
        $over60Share = $ageing['total'] > 0 ? $ageing['over_60'] / $ageing['total'] * 100 : 0;
        $effectiveTax = $summary['total_taxable_value'] > 0
            ? $summary['total_tax'] / $summary['total_taxable_value'] * 100
            : 0;

        // Sparkline for the billed card: the same six months, in the card's own ink.
        $sparkMax = max(1, (float) $monthly->max('billed'));
        $spark = $monthly->values()->map(function ($month, $i) use ($monthly, $sparkMax) {
            $x = $monthly->count() > 1 ? round($i / ($monthly->count() - 1) * 100, 2) : 0;
            $y = round(26 - ((float) $month['billed'] / $sparkMax) * 24, 2);
            return ($i === 0 ? 'M' : 'L')."$x $y";
        })->implode(' ');
    @endphp

    <div class="page-head">
        <div>
            <div class="eyebrow">Overview</div>
            <h1>{{ ($currentBusiness ?? null) ? $currentBusiness->name : 'Everything, as it stands' }}</h1>
            <div class="sub">
                @if($currentBusiness ?? null)
                    This business only · figures computed from the bills themselves
                @else
                    Across {{ $businesses->count() }} {{ Str::plural('business', $businesses->count()) }} · figures computed from the bills themselves
                @endif
            </div>
        </div>
        <div class="muted" style="font-size:12.5px">{{ now()->format('D d M Y, H:i') }}</div>
    </div>

    @if($openConflicts)
        <div class="notice warn">
            <div>
                {{ $openConflicts }} {{ Str::plural('change', $openConflicts) }} from a handset lost a last-write-wins comparison and {{ $openConflicts === 1 ? 'is' : 'are' }} waiting to be looked at.
                <a href="{{ route('admin.conflicts.index') }}">Review {{ $openConflicts === 1 ? 'it' : 'them' }}</a>.
            </div>
        </div>
    @endif

    <div class="kpis">
        <div class="kpi rose">
            <div class="top">
                <div class="label">Outstanding</div>
                <div class="chip">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3.5 21 19H3z"/><path d="M12 9.5v4M12 16.5h.01"/></svg>
                </div>
            </div>
            <div class="value">{{ Money::rupees($summary['total_outstanding']) }}</div>
            <div class="foot">
                <span class="delta">{{ round($over60Share) }}%</span>
                over 60 days old
            </div>
        </div>

        <div class="kpi emerald">
            <div class="top">
                <div class="label">Collected</div>
                <div class="chip">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                </div>
            </div>
            <div class="value">{{ Money::rupees($summary['total_collected']) }}</div>
            <div class="foot">{{ number_format($collectionRate, 1) }}% of everything billed</div>
            <div class="meter"><i style="width: {{ min(100, round($collectionRate)) }}%"></i></div>
        </div>

        <div class="kpi">
            <div class="top">
                <div class="label">Billed</div>
                <div class="chip">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2h9l5 5v15H6z"/><path d="M15 2v5h5"/><path d="M10 12h5M10 16h5"/></svg>
                </div>
            </div>
            <div class="value">{{ Money::rupees($summary['total_billed']) }}</div>
            <div class="foot">
                @if($momChange !== null)
                    <span class="delta">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                            @if($momChange >= 0)<path d="M12 19V5M6 11l6-6 6 6"/>@else<path d="M12 5v14M18 13l-6 6-6-6"/>@endif
                        </svg>
                        {{ abs($momChange) }}%
                    </span>
                    {{ Str::before($monthly->last()['label'], ' ') }}{{ $lastIsCurrent ? ' so far' : '' }} vs {{ Str::before($monthly[$monthly->count() - 2]['label'], ' ') }}
                @else
                    {{ $summary['bill_count'] }} {{ Str::plural('bill', $summary['bill_count']) }}, all time
                @endif
            </div>
            <svg class="spark" viewBox="0 0 100 30" preserveAspectRatio="none" aria-hidden="true">
                <path d="{{ $spark }}" fill="none" stroke="rgba(255,255,255,.9)" stroke-width="2"
                      stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke" />
            </svg>
        </div>

        <div class="kpi amber">
            <div class="top">
                <div class="label">Tax charged</div>
                <div class="chip">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M19 5 5 19"/><circle cx="7.5" cy="7.5" r="2.5"/><circle cx="16.5" cy="16.5" r="2.5"/></svg>
                </div>
            </div>
            <div class="value">{{ Money::rupees($summary['total_tax']) }}</div>
            <div class="foot">
                <span class="delta">{{ number_format($effectiveTax, 1) }}%</span>
                on {{ Money::rupees($summary['total_taxable_value']) }} taxable
            </div>
        </div>
    </div>

    <div class="grid wide">
        <section class="card">
            <h2>
                Billing, last six months
                <span class="hint">Live bills, by bill date</span>
            </h2>
            <div class="body">
                <x-area-chart :points="$monthly->map(fn ($m) => ['label' => $m['label'], 'value' => $m['billed'], 'bill_count' => $m['bill_count']])"
                              caption="Billed per month" />
            </div>
        </section>

        <section class="card">
            <h2>
                What's owed, by age
                <span class="hint">From the bill date</span>
            </h2>
            <div class="body">
                <x-donut
                    :bands="[
                        ['label' => '0–30 days', 'value' => $ageing['up_to_30'], 'token' => 'age-1'],
                        ['label' => '31–60 days', 'value' => $ageing['from_31_to_60'], 'token' => 'age-2'],
                        ['label' => 'Over 60 days', 'value' => $ageing['over_60'], 'token' => 'age-3', 'flag' => 'Chase'],
                    ]"
                    centre-cap="Owed"
                    :centre-value="'₹'.Money::compact($ageing['total'])" />

                <p class="muted" style="margin:16px 0 0;font-size:12.5px">
                    There are no payment terms in this system, so age is measured from the bill date — the honest question it can answer.
                </p>
            </div>
        </section>
    </div>

    <div class="grid wide" style="margin-top:18px">
        <section class="card">
            <h2>
                Latest documents
                <a href="{{ route('admin.invoices.index') }}">See all →</a>
            </h2>
            <div class="body flush">
                <div class="scroller">
                    <table>
                        <thead>
                        <tr><th>No.</th><th>Customer</th><th class="num">Total</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                        @forelse($recent as $invoice)
                            <tr>
                                <td class="mono"><a href="{{ route('admin.invoices.show', $invoice) }}">{{ $invoice->display_no }}</a></td>
                                <td>
                                    <div class="who-cell">
                                        <x-avatar :name="$invoice->customer_name" small />
                                        <div class="lines">
                                            <div>{{ $invoice->customer_name }}</div>
                                            <div>{{ $invoice->business->name }} · {{ $invoice->date?->format('d M Y') }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="num strong">{{ Money::rupees((float) $invoice->total) }}</td>
                                <td><x-status-pill :status="$invoice->status" /></td>
                            </tr>
                        @empty
                            <tr><td colspan="4"><div class="empty"><strong>Nothing yet</strong>Bills will appear here once a handset syncs.</div></td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section class="card">
            <h2>
                Who owes most
                <span class="hint">By value billed</span>
            </h2>
            <div class="body">
                @php $topMax = max(0.01, (float) $topCustomers->max('amount')); @endphp
                <div class="meters">
                    @forelse($topCustomers as $row)
                        <div class="row">
                            <div class="cap">
                                <span class="who-cell">
                                    <x-avatar :name="$row['name']" small />
                                    <span>{{ $row['name'] }}</span>
                                </span>
                            </div>
                            <div class="val">{{ Money::rupees((float) $row['amount']) }}</div>
                            <div class="track">
                                <div class="fill" style="width: {{ round($row['amount'] / $topMax * 100) }}%"></div>
                            </div>
                        </div>
                    @empty
                        <div class="empty"><strong>No customers yet</strong>They arrive with the first sync.</div>
                    @endforelse
                </div>
            </div>
        </section>
    </div>

    <section class="card" style="margin-top:18px">
        <h2>
            Handsets
            <a href="{{ route('admin.devices.index') }}">Manage →</a>
        </h2>
        <div class="body flush">
            <div class="scroller">
                <table>
                    <thead><tr><th>Device</th><th>Owner</th><th class="date">Last pushed</th><th class="date">Last pulled</th><th></th></tr></thead>
                    <tbody>
                    @forelse($devices as $device)
                        <tr>
                            <td class="strong">{{ $device->name }}<div class="muted" style="font-size:12px">{{ $device->platform }} · v{{ $device->app_version ?? '—' }}</div></td>
                            <td>
                                <div class="who-cell">
                                    <x-avatar :name="$device->user->name" small />
                                    <span>{{ $device->user->name }}</span>
                                </div>
                            </td>
                            <td class="date">{{ $device->last_pushed_at?->diffForHumans() ?? '—' }}</td>
                            <td class="date">{{ $device->last_pulled_at?->diffForHumans() ?? '—' }}</td>
                            <td>@if($device->is_stale)<span class="pill unpaid">Stale</span>@else<span class="pill paid">Current</span>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><div class="empty"><strong>No handset has signed in</strong>A device registers itself the first time the app logs in.</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>
@endsection
