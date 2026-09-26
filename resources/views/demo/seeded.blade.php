<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Demo data · Invoice Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
    <style>
        /* Stands on its own: this page is reachable without signing in, so it
           cannot use the panel's shell, which assumes an account. */
        body { padding: 40px 20px; }
        .sheet { max-width: 760px; margin: 0 auto; }
        .sheet h1 { margin: 6px 0 4px; font-size: 24px; letter-spacing: -0.4px; }
        .sheet .lede { color: var(--ink-3); margin: 0 0 22px; }
        .firm { display: flex; align-items: center; gap: 12px; padding: 12px 0; border-bottom: 1px solid var(--rule); }
        .firm:last-child { border-bottom: 0; }
        .firm .lines { min-width: 0; }
        .firm .name { font-weight: 600; }
        .firm .meta { font-size: 12.5px; color: var(--ink-3); }
        .firm .ref { margin-left: auto; font-size: 12.5px; color: var(--ink-3); white-space: nowrap; }
        .tally { display: flex; flex-wrap: wrap; gap: 18px; margin: 4px 0 0; padding: 0; list-style: none; }
        .tally li { font-size: 13px; color: var(--ink-3); }
        .tally b { display: block; font-size: 19px; font-weight: 700; color: var(--ink); }
    </style>
</head>
<body>
<div class="sheet">

    <div class="eyebrow">Demo data</div>
    <h1>{{ $seededNow ? 'Seeded.' : 'Already seeded.' }}</h1>
    <p class="lede">
        @if($seededNow)
            Three businesses have been created, each with a few months of bills behind it.
        @else
            Nothing was created this time — these businesses already have documents against them,
            so they were left exactly as they are. Reloading this page is safe.
        @endif
    </p>

    <section class="card" style="margin-bottom:18px">
        <div class="body">
            <ul class="tally">
                <li><b>{{ $totals['businesses'] }}</b> businesses</li>
                <li><b>{{ $totals['documents'] }}</b> documents</li>
                <li><b>{{ $totals['customers'] }}</b> customers</li>
                <li><b>{{ $totals['items'] }}</b> catalogue items</li>
                <li><b>{{ $totals['payments'] }}</b> receipts</li>
            </ul>
        </div>
    </section>

    <section class="card" style="margin-bottom:18px">
        <h2>What is on the server</h2>
        <div class="body">
            @forelse($businesses as $business)
                <div class="firm">
                    <x-avatar :name="$business->name" small />
                    <div class="lines">
                        <div class="name">{{ $business->name }}</div>
                        <div class="meta">
                            {{ $business->invoices_count }} documents ·
                            {{ $business->customers_count }} customers ·
                            {{ $business->items_count }} items
                        </div>
                    </div>
                    <div class="ref mono">{{ $business->bill_prefix ?: '—' }}</div>
                </div>
            @empty
                <div class="empty"><strong>Nothing here</strong>The seeder ran but created nothing — check the logs.</div>
            @endforelse
        </div>
    </section>

    <section class="card">
        <h2>Signing in</h2>
        <div class="body">
            @if($owner)
                <p style="margin:0 0 10px">
                    The data belongs to <strong>{{ $owner->name }}</strong>
                    @if($owner->realEmail())
                        · <span class="mono">{{ $owner->realEmail() }}</span>
                    @endif
                    @if($owner->phone)
                        · <span class="mono">{{ $owner->phone }}</span>
                    @endif
                </p>
                <p class="muted" style="margin:0 0 14px;font-size:12.5px">
                    The password is whatever this instance was deployed with — it is not shown here.
                </p>
            @endif
            <a href="{{ route('admin.dashboard') }}" class="btn">Open the panel</a>
        </div>
    </section>

</div>
</body>
</html>
