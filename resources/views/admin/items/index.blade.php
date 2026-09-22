@extends('admin.layout')
@section('title', 'Items & rates')

@section('content')
    @php use App\Services\Money; @endphp

    <div class="page-head">
        <div>
            <div class="eyebrow">Billing</div>
            <h1>Items &amp; rates</h1>
            <div class="sub">What the app suggests when a line is typed onto a bill — ordered by what each has actually earned.</div>
        </div>
        <div class="head-actions">
            <a href="{{ route('admin.items.create') }}" class="btn">
                <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                New entry
            </a>
        </div>
    </div>

    <form method="GET" class="filters">
        <div class="field">
            <label for="search">Search</label>
            <input id="search" type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Work or item">
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
        @include('admin.partials.records-filter')
        <div class="actions">
            <button type="submit" class="btn">Filter</button>
            <a href="{{ route('admin.items.index') }}" class="btn ghost">Clear</a>
        </div>
    </form>

    <section class="card">
        <div class="body flush">
            <div class="scroller">
                <table>
                    <thead>
                    <tr>
                        <th>Work or item</th><th>HSN</th>
                        <th class="num">Default rate</th><th class="num">Last billed at</th>
                        <th class="num">Times</th><th class="num">Earned</th><th></th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($rows as $row)
                        @php $item = $row['item']; @endphp
                        <tr class="{{ $item->trashed() ? 'is-deleted' : '' }}">
                            <td>
                                <div class="who-cell">
                                    <x-avatar :name="$item->name" small />
                                    <div class="lines">
                                        <div>
                                            <a href="{{ route('admin.items.edit', $item) }}" class="strong">{{ $item->name }}</a>
                                            @if($item->trashed())<span class="pill deleted">Deleted</span>@endif
                                        </div>
                                        <div>
                                            {{ $item->business->name }}
                                            @if($item->trashed()) · removed {{ $item->deleted_at->diffForHumans() }}@endif
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="mono muted">{{ $item->hsn_code ?: '—' }}</td>
                            <td class="num">{{ $item->default_rate ? Money::rupees((float) $item->default_rate) : '—' }}</td>
                            <td class="num {{ $row['last_rate'] !== null && $item->default_rate && abs($row['last_rate'] - (float) $item->default_rate) > 0.001 ? 'strong' : 'muted' }}">
                                {{ $row['last_rate'] !== null ? Money::rupees($row['last_rate']) : '—' }}
                            </td>
                            <td class="num muted">{{ $row['times'] ?: '—' }}</td>
                            <td class="num strong">{{ $row['earned'] > 0 ? Money::rupees($row['earned']) : '—' }}</td>
                            <td class="num">
                                @if($item->trashed())
                                    <form method="POST" action="{{ route('admin.items.restore', $item) }}">
                                        @csrf
                                        <button type="submit" class="btn ghost small">Restore</button>
                                    </form>
                                @else
                                    <a href="{{ route('admin.items.edit', $item) }}" class="btn ghost small">Edit</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><div class="empty">
                            @if(($filters['records'] ?? 'live') === 'deleted')
                                <strong>Nothing deleted</strong>Catalogue entries removed on a handset show up here.
                            @else
                                <strong>Nothing in the catalogue</strong>Entries appear here the first time a line is typed onto a bill, or add one yourself.
                            @endif
                        </div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    @if($rows->isNotEmpty())
        <p class="muted" style="margin:14px 2px 0;font-size:12.5px">
            "Last billed at" is the rate on the most recent bill that used this line. Where it differs from the default,
            the default is probably out of date.
        </p>
    @endif
@endsection
