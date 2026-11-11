<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#f5efe0">
    <title>Login Pengelola | WARNA BUDAYA</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <style>
        * { box-sizing: border-box; }
        body { min-height: 100vh; display: grid; place-items: center; margin: 0; padding: 24px; background: #f5efe0; color: #292820; font-family: Arial, sans-serif; }
        main { width: min(100%, 440px); padding: 36px; border: 1px solid rgba(37, 72, 56, .14); background: #fbf8f1; box-shadow: 0 20px 60px rgba(25, 56, 45, .09); }
        .login-brand { display: block; width: min(100%, 300px); height: auto; margin: 0 auto 20px; }
        h1 { margin: 0 0 24px; color: #19382d; font-family: Georgia, serif; font-size: 26px; text-align: center; }
        form { display: grid; gap: 12px; }
        label { margin-top: 6px; color: #4a4b41; font-size: 13px; font-weight: 600; }
        input { width: 100%; padding: 12px; border: 1px solid rgba(41, 40, 32, .2); background: white; font: inherit; }
        button { margin-top: 10px; padding: 13px; border: 0; background: #19382d; color: #fff5df; font: inherit; font-weight: 700; cursor: pointer; }
        [role="alert"] { margin: 0; color: #a33e37; font-size: 13px; }
        @media (max-width: 480px) { main { padding: 28px 22px; } .login-brand { width: 260px; } }
    </style>
</head>
<body>
    <main>
        <img class="login-brand" src="{{ asset('images/brand-horizontal.svg') }}" alt="WARNA BUDAYA — Jaga Budaya, Lestarikan Negeri">
        <h1>Login Pengelola</h1>
        <form method="POST" action="{{ route('admin.login.store') }}">
            @csrf
            <label for="email">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required maxlength="255" autocomplete="username">
            @error('email')
                <p role="alert">{{ $message }}</p>
            @enderror

            <label for="password">Password</label>
            <input id="password" name="password" type="password" required maxlength="255" autocomplete="current-password">
            @error('password')
                <p role="alert">{{ $message }}</p>
            @enderror

            <button type="submit">Masuk</button>
        </form>
    </main>
</body>
</html>
