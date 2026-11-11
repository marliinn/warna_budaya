<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#f5efe0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="@yield('meta_description', 'Kenali, jelajahi, dan lestarikan warisan budaya Indonesia bersama WARNA BUDAYA.')">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <title>@yield('title', 'WARNA BUDAYA — Jaga Budaya, Lestarikan Negeri')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('css/ai-assistant.css') }}">
    @stack('styles')
</head>
<body class="@yield('body_class')">
    @include('partials.navbar')

    <main>
        @yield('content')
    </main>

    @include('partials.footer')
    @include('partials.ai-assistant')
    <script src="{{ asset('js/app.js') }}" defer></script>
    <script src="{{ asset('js/ai-assistant.js') }}" defer></script>
    @stack('scripts')
</body>
</html>
