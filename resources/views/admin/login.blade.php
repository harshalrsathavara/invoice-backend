<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#8C1D24">
    <title>Sign in · Invoice Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
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
<div class="login-wrap">
    <form method="POST" action="{{ route('admin.login') }}" class="login">
        @csrf
        <div class="sigil">&#8377;</div>
        <div class="eyebrow">Invoice Admin</div>
        <h1>Sign in</h1>
        <p class="sub">The books, the devices and everything they've synced.</p>

        @if($errors->any())
            <div class="notice errors">{{ $errors->first() }}</div>
        @endif

        <div class="field">
            <label for="email">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
        </div>

        <div class="field">
            <label for="password">Password</label>
            <input id="password" type="password" name="password" required autocomplete="current-password">
        </div>

        <label class="stay">
            <input type="checkbox" name="remember" value="1"> Stay signed in
        </label>

        <button type="submit" class="btn">Sign in</button>
    </form>
</div>
</body>
</html>
