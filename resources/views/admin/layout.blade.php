<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#1B1440">
    <title>@yield('title', 'Admin') · Invoice Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
    {{-- Settle the theme before first paint, so a dark reload never flashes light. --}}
    <script>
        (function () {
            try {
                var t = localStorage.getItem('admin-theme');
                if (t === 'dark' || t === 'light') document.documentElement.dataset.theme = t;
            } catch (e) {}
        })();
    </script>
</head>
<body>
@php($openConflicts = \App\Models\SyncConflict::unreviewed()->count())
<div class="shell">
    <aside class="sidebar" id="sidebar">
        <div class="brand">
            <div class="sigil">&#8377;</div>
            <div class="lines">
                <div class="mark">Invoice<span>.</span>Admin</div>
                <div class="who">{{ auth()->user()->name }}</div>
            </div>
        </div>

        <nav class="nav">
            <div class="group">Overview</div>
            <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'on' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="8" height="8" rx="2"/><rect x="13" y="3" width="8" height="5" rx="2"/><rect x="3" y="13" width="8" height="8" rx="2"/><rect x="13" y="10" width="8" height="11" rx="2"/></svg>
                <span class="lines">
                    <span class="label">Dashboard</span>
                    <span class="hint">Totals at a glance</span>
                </span>
            </a>
            <a href="{{ route('admin.businesses.index') }}" class="{{ request()->routeIs('admin.businesses.*') ? 'on' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M5 21V7l7-4 7 4v14"/><path d="M9.5 21v-5h5v5"/><path d="M9 9h.01M15 9h.01M9 12.5h.01M15 12.5h.01"/></svg>
                <span class="lines">
                    <span class="label">Businesses</span>
                    <span class="hint">Your firms &amp; GST details</span>
                </span>
            </a>
            <a href="{{ route('admin.users.index') }}" class="{{ request()->routeIs('admin.users.*') ? 'on' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="3.6"/><path d="M5 20a7 7 0 0 1 14 0"/></svg>
                <span class="lines">
                    <span class="label">Users &amp; logins</span>
                    <span class="hint">Who can sign in</span>
                </span>
            </a>

            <div class="group">Billing</div>
            <a href="{{ route('admin.invoices.index') }}" class="{{ request()->routeIs('admin.invoices.*') ? 'on' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2h9l5 5v15H6z"/><path d="M15 2v5h5"/><path d="M10 12h5M10 16h5"/></svg>
                <span class="lines">
                    <span class="label">Bills &amp; invoices</span>
                    <span class="hint">Also quotations &amp; challans</span>
                </span>
            </a>
            <a href="{{ route('admin.customers.index') }}" class="{{ request()->routeIs('admin.customers.*') ? 'on' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3.4"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M16 5.2a3.4 3.4 0 0 1 0 5.6"/><path d="M18 20a6.4 6.4 0 0 0-2-4.6"/></svg>
                <span class="lines">
                    <span class="label">Customers</span>
                    <span class="hint">Ledger &amp; who owes what</span>
                </span>
            </a>
            <a href="{{ route('admin.items.index') }}" class="{{ request()->routeIs('admin.items.*') ? 'on' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m12 2.8 8 4.2v10L12 21.2 4 17V7z"/><path d="M4 7l8 4.2L20 7"/><path d="M12 11.2V21"/></svg>
                <span class="lines">
                    <span class="label">Items &amp; rates</span>
                    <span class="hint">Rate list the app suggests</span>
                </span>
            </a>

            <div class="group">Reports</div>
            <a href="{{ route('admin.reports.overdue') }}" class="{{ request()->routeIs('admin.reports.overdue') ? 'on' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/></svg>
                <span class="lines">
                    <span class="label">Pending payments</span>
                    <span class="hint">Who to follow up, by age</span>
                </span>
            </a>
            <a href="{{ route('admin.reports.payments') }}" class="{{ request()->routeIs('admin.reports.payments') ? 'on' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2.5" y="5.5" width="19" height="13" rx="2.5"/><path d="M2.5 10h19"/></svg>
                <span class="lines">
                    <span class="label">Payments received</span>
                    <span class="hint">Cash, UPI, cheque</span>
                </span>
            </a>
            <a href="{{ route('admin.reports.gst') }}" class="{{ request()->routeIs('admin.reports.gst') ? 'on' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M19 5 5 19"/><circle cx="7.5" cy="7.5" r="2.5"/><circle cx="16.5" cy="16.5" r="2.5"/></svg>
                <span class="lines">
                    <span class="label">GST summary</span>
                    <span class="hint">Tax charged, for filing</span>
                </span>
            </a>

            <div class="group">Mobile app</div>
            <a href="{{ route('admin.devices.index') }}" class="{{ request()->routeIs('admin.devices.*') ? 'on' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="6.5" y="2.5" width="11" height="19" rx="2.5"/><path d="M10.5 18.5h3"/></svg>
                <span class="lines">
                    <span class="label">Mobile devices</span>
                    <span class="hint">Phones holding this data</span>
                </span>
            </a>
            <a href="{{ route('admin.conflicts.index') }}" class="{{ request()->routeIs('admin.conflicts.*') ? 'on' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3.5 21 19H3z"/><path d="M12 9.5v4.5M12 17h.01"/></svg>
                <span class="lines">
                    <span class="label">Sync conflicts</span>
                    <span class="hint">Changes that need a look</span>
                </span>
                @if($openConflicts)
                    <span class="count">{{ $openConflicts }}</span>
                @endif
            </a>
        </nav>

        <div class="side-foot">
            <form method="POST" action="{{ route('admin.logout') }}">
                @csrf
                <button type="submit" class="btn ghost small">
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M15 4h3.5A1.5 1.5 0 0 1 20 5.5v13a1.5 1.5 0 0 1-1.5 1.5H15"/><path d="M11 8 7 12l4 4"/><path d="M7 12h9"/></svg>
                    Sign out
                </button>
            </form>
            <button type="button" class="icon-btn" data-theme-toggle aria-label="Switch between light and dark" title="Switch between light and dark">
                <svg class="moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 14.5A8.5 8.5 0 0 1 9.5 4a8.5 8.5 0 1 0 10.5 10.5z"/></svg>
                <svg class="sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2.5v2M12 19.5v2M2.5 12h2M19.5 12h2M5.3 5.3l1.4 1.4M17.3 17.3l1.4 1.4M18.7 5.3l-1.4 1.4M6.7 17.3l-1.4 1.4"/></svg>
            </button>
        </div>
    </aside>

    <main>
        <div class="topbar">
            <button type="button" class="icon-btn burger" data-nav-toggle aria-label="Show navigation" aria-controls="sidebar">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
            </button>

            {{-- A real search: it runs the bill list's own filter. --}}
            <form method="GET" action="{{ route('admin.invoices.index') }}" class="searchbox" role="search">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"><circle cx="11" cy="11" r="6.5"/><path d="m16 16 4.5 4.5"/></svg>
                <input type="text" name="search" placeholder="Search a customer or bill no." aria-label="Search bills by customer or number">
            </form>

            <span class="spacer"></span>

            <a href="{{ route('admin.conflicts.index') }}" class="icon-btn bell" aria-label="{{ $openConflicts }} unreviewed {{ Str::plural('conflict', $openConflicts) }}" title="{{ $openConflicts }} unreviewed {{ Str::plural('conflict', $openConflicts) }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M18 9a6 6 0 1 0-12 0c0 5-2 6-2 6h16s-2-1-2-6"/><path d="M10.5 20a2 2 0 0 0 3 0"/></svg>
                @if($openConflicts)<span class="dot">{{ $openConflicts }}</span>@endif
            </a>

            <button type="button" class="icon-btn" data-theme-toggle aria-label="Switch between light and dark">
                <svg class="moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 14.5A8.5 8.5 0 0 1 9.5 4a8.5 8.5 0 1 0 10.5 10.5z"/></svg>
                <svg class="sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2.5v2M12 19.5v2M2.5 12h2M19.5 12h2M5.3 5.3l1.4 1.4M17.3 17.3l1.4 1.4M18.7 5.3l-1.4 1.4M6.7 17.3l-1.4 1.4"/></svg>
            </button>

            <x-avatar :name="auth()->user()->name" />
        </div>

        <div class="page-body">
            @if(session('status'))
                <div class="notice">{{ session('status') }}</div>
            @endif

            @yield('content')
        </div>
    </main>
</div>

<div class="viz-tip" id="viz-tip" role="status" aria-live="polite"></div>

<script>
    // Theme toggle. Flips the explicit choice, so it also overrides the OS preference.
    document.querySelectorAll('[data-theme-toggle]').forEach(function (button) {
        button.addEventListener('click', function () {
            var root = document.documentElement;
            var dark = root.dataset.theme
                ? root.dataset.theme === 'dark'
                : window.matchMedia('(prefers-color-scheme: dark)').matches;
            root.dataset.theme = dark ? 'light' : 'dark';
            try { localStorage.setItem('admin-theme', root.dataset.theme); } catch (e) {}
        });
    });

    // Off-canvas navigation, narrow screens only.
    var navToggle = document.querySelector('[data-nav-toggle]');
    if (navToggle) {
        navToggle.addEventListener('click', function (event) {
            event.stopPropagation();
            document.body.classList.toggle('nav-open');
        });
        document.addEventListener('click', function (event) {
            if (document.body.classList.contains('nav-open') && !event.target.closest('#sidebar')) {
                document.body.classList.remove('nav-open');
            }
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') document.body.classList.remove('nav-open');
        });
    }


    // ---- the document editor: add/remove rows, and a running total ----
    (function () {
        var form = document.querySelector('[data-doc-form]');
        if (!form) return;

        var linesBody = form.querySelector('[data-lines]');
        var taxesBox = form.querySelector('[data-taxes]');
        var lineTpl = document.querySelector('[data-line-template]');
        var taxTpl = document.querySelector('[data-tax-template]');

        // Indian grouping: the last three digits stay together, then pairs.
        function money(n) {
            var negative = n < 0;
            n = Math.abs(n);
            var whole = Math.floor(n).toString();
            var paise = Math.round((n - Math.floor(n)) * 100).toString().padStart(2, '0');
            if (whole.length > 3) {
                var head = whole.slice(0, -3), tail = whole.slice(-3);
                head = head.replace(/\B(?=(\d{2})+(?!\d))/g, ',');
                whole = head + ',' + tail;
            }
            return (negative ? '-' : '') + whole + '.' + paise;
        }
        function num(el) { var v = parseFloat(el && el.value); return isNaN(v) ? 0 : v; }
        function r2(n) { return Math.round(n * 100) / 100; }

        // Names carry their index, so every add or remove has to renumber.
        function reindex() {
            linesBody.querySelectorAll('[data-line-row]').forEach(function (row, i) {
                row.querySelectorAll('input').forEach(function (input) {
                    input.name = input.name.replace(/lines\[[^\]]*\]/, 'lines[' + i + ']');
                });
            });
            taxesBox.querySelectorAll('[data-tax-row]').forEach(function (row, i) {
                row.querySelectorAll('input').forEach(function (input) {
                    input.name = input.name.replace(/taxes\[[^\]]*\]/, 'taxes[' + i + ']');
                });
            });
        }

        function recalc() {
            var subtotal = 0;
            linesBody.querySelectorAll('[data-line-row]').forEach(function (row) {
                var amount = r2(num(row.querySelector('[data-qty]')) * num(row.querySelector('[data-rate]')));
                row.querySelector('[data-amount]').textContent = money(amount);
                subtotal += amount;
            });
            subtotal = r2(subtotal);

            var type = form.querySelector('[name="discount_type"]');
            var value = num(form.querySelector('[name="discount_value"]'));
            var raw = type && type.value === 'percent' ? subtotal * value / 100
                    : type && type.value === 'amount' ? value : 0;
            var discount = r2(Math.max(0, Math.min(raw, subtotal)));
            var taxable = r2(subtotal - discount);

            var tax = 0, labels = [];
            taxesBox.querySelectorAll('[data-tax-row]').forEach(function (row) {
                var percent = num(row.querySelector('[data-percent]'));
                var label = row.querySelector('input[type="text"]').value.trim();
                if (!label) return;
                tax += r2(taxable * percent / 100);
                labels.push(label + ' ' + percent + '%');
            });
            tax = r2(tax);

            var round = num(form.querySelector('[name="round_off"]'));
            var total = r2(taxable + tax + round);

            form.querySelector('[data-sum-subtotal]').textContent = money(subtotal);
            form.querySelector('[data-sum-discount]').textContent = '−' + money(discount);
            form.querySelector('[data-sum-discount-label]').textContent =
                type && type.value === 'percent' ? 'Discount (' + value + '%)' : 'Discount';
            form.querySelector('[data-sum-taxable]').textContent = money(taxable);
            form.querySelector('[data-sum-tax]').textContent = money(tax);
            form.querySelector('[data-sum-tax-label]').textContent = labels.length ? labels.join(' + ') : 'Tax';
            form.querySelector('[data-sum-round]').textContent = money(round);
            form.querySelector('[data-sum-total]').textContent = '₹' + money(total);
        }

        form.addEventListener('input', recalc);
        form.addEventListener('change', recalc);

        form.querySelector('[data-add-line]').addEventListener('click', function () {
            var count = linesBody.querySelectorAll('[data-line-row]').length;
            linesBody.insertAdjacentHTML('beforeend', lineTpl.innerHTML.replace(/__i__/g, count));
            reindex();
            recalc();
            var added = linesBody.querySelector('[data-line-row]:last-child input[type="text"]');
            if (added) added.focus();
        });

        form.querySelector('[data-add-tax]').addEventListener('click', function () {
            var count = taxesBox.querySelectorAll('[data-tax-row]').length;
            taxesBox.insertAdjacentHTML('beforeend', taxTpl.innerHTML.replace(/__i__/g, count));
            reindex();
            recalc();
        });

        form.addEventListener('click', function (event) {
            var dropLine = event.target.closest('[data-drop-line]');
            var dropTax = event.target.closest('[data-drop-tax]');
            if (dropLine) {
                // A document with no lines at all cannot be saved, so the last
                // row is emptied rather than removed.
                if (linesBody.querySelectorAll('[data-line-row]').length > 1) {
                    dropLine.closest('[data-line-row]').remove();
                } else {
                    dropLine.closest('[data-line-row]').querySelectorAll('input').forEach(function (i) {
                        if (i.type !== 'hidden') i.value = '';
                    });
                }
            }
            if (dropTax) dropTax.closest('[data-tax-row]').remove();
            if (dropLine || dropTax) { reindex(); recalc(); }
        });

        recalc();
    })();

    // Crosshair + tooltip for the trend chart. The hit bands are full-height,
    // so this never asks anyone to land on a 4px dot.
    (function () {
        var tip = document.getElementById('viz-tip');
        if (!tip) return;

        function show(html, x, y) {
            tip.innerHTML = html;
            tip.style.left = x + 'px';
            tip.style.top = y + 'px';
            tip.classList.add('on');
        }
        function hide() { tip.classList.remove('on'); }

        document.querySelectorAll('[data-areachart]').forEach(function (chart) {
            var svg = chart.querySelector('svg');
            var cross = chart.querySelector('.crosshair');
            var knot = chart.querySelector('.hover-knot');

            chart.querySelectorAll('.hit').forEach(function (band) {
                function enter() {
                    var px = band.dataset.x, py = band.dataset.y;
                    cross.setAttribute('x1', px); cross.setAttribute('x2', px);
                    knot.setAttribute('cx', px); knot.setAttribute('cy', py);
                    chart.classList.add('is-hovering');

                    // The knot's own position on screen, so the tip tracks the mark.
                    var pt = svg.createSVGPoint();
                    pt.x = +px; pt.y = +py;
                    var at = pt.matrixTransform(svg.getScreenCTM());
                    var note = band.dataset.note ? '<span class="t-label">' + band.dataset.note + '</span>' : '';
                    show('<span class="t-label">' + band.dataset.label + '</span><b>' + band.dataset.value + '</b>' + note, at.x, at.y);
                }
                band.addEventListener('mouseenter', enter);
                band.addEventListener('focus', enter);
                band.addEventListener('mouseleave', function () { chart.classList.remove('is-hovering'); hide(); });
                band.addEventListener('blur', function () { chart.classList.remove('is-hovering'); hide(); });
            });
        });
    })();
</script>
</body>
</html>
