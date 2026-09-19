@extends('admin.layout')
@section('title', 'Customers')

@section('content')
    <div class="page-head">
        <div>
            <div class="eyebrow">Billing</div>
            <h1>Customers &amp; ledger</h1>
            <div class="sub">Heaviest debt first — the order anyone chasing money actually wants.</div>
        </div>
        <div class="head-actions">
            <a href="{{ route('admin.reports.overdue') }}" class="btn ghost small">Pending payments</a>
            <a href="{{ route('admin.customers.create') }}" class="btn">
                <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                New customer
            </a>
        </div>
    </div>

    <form method="GET" class="filters">
        <div class="field">
            <label for="search">Search</label>
            <input id="search" type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Customer name">
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
        <div class="actions">
            <button type="submit" class="btn">Filter</button>
            <a href="{{ route('admin.customers.index') }}" class="btn ghost">Clear</a>
        </div>
    </form>

    <section class="card">
        <div class="body flush">
            <div class="scroller">
                <table>
                    <thead>
                    <tr><th>Customer</th><th>Phone</th><th class="num">Bills</th><th class="num">Billed</th><th class="num">Collected</th><th class="num">Outstanding</th></tr>
                    </thead>
                    <tbody>
                    @forelse($rows as $row)
                        @php $c = $row['customer']; @endphp
                        <tr>
                            <td>
                                <div class="who-cell">
                                    <x-avatar :name="$c->name" small />
                                    <div class="lines">
                                        <div><a href="{{ route('admin.customers.show', $c) }}" class="strong">{{ $c->name }}</a></div>
                                        <div>{{ $c->business->name }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="mono">{{ $c->phone ?: '—' }}</td>
                            <td class="num">{{ $row['bill_count'] }}</td>
                            <td class="num"><x-money :value="$row['billed']" /></td>
                            <td class="num"><x-money :value="$row['collected']" /></td>
                            <td class="num strong" style="{{ $row['outstanding'] > 0.001 ? 'color:var(--st-crit-ink)' : 'color:var(--ink-3)' }}">
                                <x-money :value="$row['outstanding']" />
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><div class="empty"><strong>No customers</strong>They arrive with the first sync or import.</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>
@endsection
