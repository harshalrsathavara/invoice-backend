@extends('admin.layout')
@section('title', $user->exists ? 'Edit '.$user->name : 'New owner')

@section('content')
    @php($isSelf = $user->exists && $user->is(auth()->user()))

    <div class="page-head">
        <div>
            <div class="eyebrow">Users &amp; logins</div>
            <h1>{{ $user->exists ? 'Edit '.$user->name : 'New owner' }}</h1>
            <div class="sub">
                @if($user->exists)
                    What they sign in with, and how far it gets them.
                @else
                    They sign in on the handset with this address and password, and their books sync under this account.
                @endif
            </div>
        </div>
        <a href="{{ route('admin.users.index') }}" class="btn ghost small">Cancel</a>
    </div>

    <form method="POST" action="{{ $user->exists ? route('admin.users.update', $user) : route('admin.users.store') }}">
        @csrf
        @if($user->exists) @method('PUT') @endif

        <section class="card form-card">
            <h2>Account</h2>
            <div class="body">
                <div class="form-grid">
                    <x-field name="name" label="Name" :value="$user->name" required span
                             help="Shown against their businesses, devices and bills in this panel." />
                    <x-field name="email" label="Email" :value="$user->email" type="email" required
                             help="What they sign in with, on the app and here." />
                    <x-field name="phone" label="Phone" :value="$user->phone" />

                    <x-field name="password" type="password" span
                             :label="$user->exists ? 'New password' : 'Password'"
                             :required="! $user->exists"
                             :help="$user->exists
                                ? 'Leave blank to keep the current one. Setting a new one signs their handsets out.'
                                : 'At least 8 characters. You will have to pass it on to them.'" />

                    <div class="fieldset span-2">
                        <label>Panel access</label>
                        <label class="check">
                            <input type="checkbox" name="is_admin" value="1"
                                   @checked(old('is_admin', $user->is_admin))
                                   @disabled($isSelf)>
                            <span>
                                Administrator
                                <span class="help">
                                    Can open this panel and sees every business on the server. Leave it off for an
                                    ordinary owner: they sign in from the app and sync only their own books.
                                    @if($isSelf) You cannot take this off your own account. @endif
                                </span>
                            </span>
                        </label>
                        @if($isSelf)
                            {{-- A disabled box submits nothing, and absent means false — so post it back. --}}
                            <input type="hidden" name="is_admin" value="1">
                        @endif
                        @error('is_admin')<div class="bad">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn">{{ $user->exists ? 'Save changes' : 'Add owner' }}</button>
                <a href="{{ route('admin.users.index') }}" class="btn ghost">Cancel</a>
            </div>
        </section>
    </form>

    @if($user->exists)
        <section class="card" style="margin-top:18px">
            <h2>
                What this account holds
                <span class="hint">{{ $user->businesses_count }} {{ Str::plural('business', $user->businesses_count) }} · {{ $user->devices_count }} {{ Str::plural('handset', $user->devices_count) }}</span>
            </h2>
            <div class="body">
                @error('user')<div class="notice warn" style="margin-bottom:14px">{{ $message }}</div>@enderror

                @if($isSelf)
                    <p class="muted" style="margin:0;font-size:13px">This is the account you are signed in as, so it cannot be removed from here.</p>
                @elseif($user->businesses_count > 0)
                    <p class="muted" style="margin:0;font-size:13px">
                        An account is deleted for real — there is no undo on it, and the businesses under it go with it at
                        the database level, outright, with none of the recovery the rest of the panel gives you. So while
                        {{ $user->name }} still holds
                        {{ $user->businesses_count }} {{ Str::plural('business', $user->businesses_count) }},
                        deleting is refused. Remove those first if you mean it.
                    </p>
                @else
                    <p class="muted" style="margin:0;font-size:13px">
                        They hold no businesses, so the account can be removed. Their
                        {{ $user->devices_count }} registered {{ Str::plural('handset', $user->devices_count) }} and
                        any tokens go with it.
                    </p>
                @endif
            </div>
            @unless($isSelf || $user->businesses_count > 0)
                <div class="form-actions">
                    <form method="POST" action="{{ route('admin.users.destroy', $user) }}" class="inline-form"
                          onsubmit="return confirm('Remove {{ $user->name }}\'s account? They will not be able to sign in again, on the app or here.')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn danger">Remove account</button>
                    </form>
                </div>
            @endunless
        </section>
    @endif
@endsection
