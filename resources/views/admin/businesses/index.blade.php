@extends('admin.layout')
@section('title', 'Businesses')

@section('content')
    <div class="page-head">
        <div>
            <div class="eyebrow">Overview</div>
            <h1>Businesses</h1>
            <div class="sub">One row per company syncing to this server.</div>
        </div>
        <div class="head-actions">
            <a href="{{ route('admin.businesses.create') }}" class="btn">
                <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                New business
            </a>
        </div>
    </div>

    <section class="card">
        <div class="body flush">
            <div class="scroller">
                <table>
                    <thead>
                    <tr><th>Business</th><th>Owner</th><th>Numbering</th><th class="num">Bills</th><th class="num">Customers</th><th class="num">Items</th><th></th></tr>
                    </thead>
                    <tbody>
                    @forelse($businesses as $business)
                        <tr>
                            <td>
                                <div class="who-cell">
                                    <x-avatar :name="$business->name" small />
                                    <div class="lines">
                                        <div><a href="{{ route('admin.businesses.show', $business) }}" class="strong">{{ $business->name }}</a></div>
                                        <div>{{ $business->tagline ?: $business->mobile }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>{{ $business->user->name }}</td>
                            <td class="mono">{{ $business->formatBillNo($business->next_bill_no) }}{{ $business->fy_reset ? ' · FY reset' : '' }}</td>
                            <td class="num">{{ $business->invoices_count }}</td>
                            <td class="num">{{ $business->customers_count }}</td>
                            <td class="num">{{ $business->items_count }}</td>
                            <td class="num"><a href="{{ route('admin.businesses.edit', $business) }}" class="btn ghost small">Edit</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><div class="empty"><strong>No businesses yet</strong>Add one here, import a backup, or let a handset sync one up.</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>
@endsection
