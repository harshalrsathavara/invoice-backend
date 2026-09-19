@extends('admin.layout')
@section('title', 'Users & logins')

@section('content')
    <div class="page-head">
        <div>
            <div class="eyebrow">Overview</div>
            <h1>Users &amp; logins</h1>
            <div class="sub">An account is what a handset signs in as. A business belongs to one, and syncs only to its phones.</div>
        </div>
        <div class="head-actions">
            <a href="{{ route('admin.users.create') }}" class="btn">
                <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                New owner
            </a>
        </div>
    </div>

    @error('user')<div class="notice warn">{{ $message }}</div>@enderror

    <section class="card">
        <div class="body flush">
            <div class="scroller">
                <table>
                    <thead>
                    <tr><th>Account</th><th>Access</th><th class="num">Businesses</th><th class="num">Handsets</th><th class="date">Last signed in</th><th></th></tr>
                    </thead>
                    <tbody>
                    @forelse($users as $user)
                        <tr>
                            <td>
                                <div class="who-cell">
                                    <x-avatar :name="$user->name" small />
                                    <div class="lines">
                                        <div>
                                            <a href="{{ route('admin.users.edit', $user) }}" class="strong">{{ $user->name }}</a>
                                            @if($user->is(auth()->user()))<span class="muted" style="font-size:12px"> · you</span>@endif
                                        </div>
                                        <div>{{ $user->email }}{{ $user->phone ? ' · '.$user->phone : '' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                @if($user->is_admin)
                                    <span class="pill paid">Panel &amp; app</span>
                                @else
                                    <span class="pill">App only</span>
                                @endif
                            </td>
                            <td class="num">{{ $user->businesses_count }}</td>
                            <td class="num">{{ $user->devices_count }}</td>
                            <td class="date">{{ $user->last_login_at?->format('d M Y, H:i') ?? 'Never' }}</td>
                            <td class="num"><a href="{{ route('admin.users.edit', $user) }}" class="btn ghost small">Edit</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><div class="empty"><strong>No accounts yet</strong>Add an owner, then file a business under them.</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <p class="muted" style="margin:16px 0 0;font-size:12.5px">
        <strong>App only</strong> is the ordinary case: they sign in on the handset, sync their own books, and cannot open
        this panel. <strong>Panel &amp; app</strong> adds administrator rights, which means seeing and editing every
        business on this server, not just their own.
    </p>
@endsection
