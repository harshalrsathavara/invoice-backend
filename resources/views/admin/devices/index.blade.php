@extends('admin.layout')
@section('title', 'Mobile devices')

@section('content')
    <div class="page-head">
        <div>
            <div class="eyebrow">Mobile app</div>
            <h1>Mobile devices</h1>
            <div class="sub">Which handsets hold data, and when each last spoke to the server.</div>
        </div>
    </div>

    <section class="card">
        <h2>Registered handsets</h2>
        <div class="body flush">
            <div class="scroller">
                <table>
                    <thead>
                    <tr><th>Device</th><th>Owner</th><th class="date">Last pushed</th><th class="date">Last pulled</th><th>State</th><th></th></tr>
                    </thead>
                    <tbody>
                    @forelse($devices as $device)
                        <tr>
                            <td>
                                <span class="strong">{{ $device->name }}</span>
                                <div class="muted" style="font-size:12px">{{ $device->platform }} · app v{{ $device->app_version ?? '—' }}</div>
                            </td>
                            <td>{{ $device->user->name }}</td>
                            <td class="date">{{ $device->last_pushed_at?->format('d M Y, H:i') ?? 'Never' }}</td>
                            <td class="date">{{ $device->last_pulled_at?->format('d M Y, H:i') ?? 'Never' }}</td>
                            <td>@if($device->is_stale)<span class="pill unpaid">Stale</span>@else<span class="pill paid">Current</span>@endif</td>
                            <td>
                                <form method="POST" action="{{ route('admin.devices.revoke', $device) }}"
                                      onsubmit="return confirm('Revoke {{ $device->name }}? Its data stays on the server — the phone simply has to sign in again.')">
                                    @csrf
                                    <button type="submit" class="btn danger small">Revoke</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><div class="empty"><strong>No handset has signed in</strong>A device registers itself the first time the app logs in.</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <section class="card" style="margin-top:18px">
        <h2>Sync activity</h2>
        <div class="body flush">
            <div class="scroller">
                <table>
                    <thead><tr><th class="date">When</th><th>Device</th><th>Direction</th><th>Rows</th><th class="num">Conflicts</th></tr></thead>
                    <tbody>
                    @forelse($logs as $log)
                        <tr>
                            <td class="date">{{ $log->created_at->format('d M Y, H:i') }}</td>
                            <td>{{ $log->device?->name ?? '—' }}</td>
                            <td><span class="pill {{ $log->direction === 'push' ? 'bill' : 'quotation' }}">{{ ucfirst($log->direction) }}</span></td>
                            <td class="muted" style="font-size:12.5px">
                                @forelse(collect($log->counts ?? [])->filter() as $table => $n)
                                    {{ $table }} {{ $n }}@if(!$loop->last) · @endif
                                @empty
                                    nothing changed
                                @endforelse
                            </td>
                            <td class="num">
                                @if($log->conflicts) <span class="pill unpaid">{{ $log->conflicts }}</span> @else <span class="muted">0</span> @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><div class="empty"><strong>Nothing has synced yet</strong></div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>
@endsection
