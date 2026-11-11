<!DOCTYPE html>
<html lang="id">
<head>
    @php
        $isAdminDashboard = request()->routeIs('admin.home');
        $isAdminTaxonomy = request()->routeIs('admin.regions.*', 'admin.categories.*');
        $isAdminCulturalEntries = request()->routeIs('admin.cultural-entries.manage');
        $isAdminMediaSources = request()->routeIs('admin.media-sources.page');
        $isAdminUsers = request()->routeIs('admin.users.page');
        $isAdminAnalytics = request()->routeIs('admin.analytics.page');
        $usesAdminShell = $isAdminDashboard || $isAdminTaxonomy || $isAdminCulturalEntries || $isAdminMediaSources || $isAdminUsers || $isAdminAnalytics;
    @endphp
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#19382d">
    <title>@yield('title', 'Kurasi Admin | WARNA BUDAYA')</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ rtrim(request()->getBaseUrl(), '/') }}/css/admin-curation.css">
    @if ($usesAdminShell)
        <link rel="stylesheet" href="{{ rtrim(request()->getBaseUrl(), '/') }}/css/admin-dashboard.css">
    @endif
    @if ($isAdminTaxonomy)
        <link rel="stylesheet" href="{{ rtrim(request()->getBaseUrl(), '/') }}/css/admin-taxonomy.css">
    @endif
    @if ($isAdminCulturalEntries)
        <link rel="stylesheet" href="{{ rtrim(request()->getBaseUrl(), '/') }}/css/admin-cultural-entries.css">
    @endif
    @if ($isAdminMediaSources)
        <link rel="stylesheet" href="{{ rtrim(request()->getBaseUrl(), '/') }}/css/admin-media-sources.css">
    @endif
    @if ($isAdminUsers || $isAdminAnalytics)
        <link rel="stylesheet" href="{{ rtrim(request()->getBaseUrl(), '/') }}/css/admin-users-analytics.css">
    @endif
</head>
<body data-auth-user-id="{{ auth()->id() }}" class="admin-curation-page{{ $usesAdminShell ? ' admin-dashboard-page' : '' }}{{ $isAdminTaxonomy ? ' admin-taxonomy-page' : '' }}{{ $isAdminCulturalEntries ? ' admin-cultural-entries-page' : '' }}{{ $isAdminMediaSources ? ' admin-media-sources-page' : '' }}{{ $isAdminUsers ? ' admin-users-page' : '' }}{{ $isAdminAnalytics ? ' admin-analytics-page' : '' }}">
    @if ($usesAdminShell)
        <div class="admin-dashboard-shell">
            <aside class="admin-sidebar" aria-label="Navigasi admin">
                <a class="admin-brand" href="{{ route('admin.home') }}">
                    <img class="admin-brand-mark" src="{{ asset('images/brand-emblem.svg') }}" alt="">
                    <span class="admin-brand-copy">
                        <strong>WARNA BUDAYA</strong>
                        <small>Jaga Budaya, Lestarikan Negeri</small>
                    </span>
                </a>

                <p class="admin-sidebar-label">MENU UTAMA</p>
                <nav class="admin-sidebar-nav">
                    <a @class(['is-active' => $isAdminDashboard]) href="{{ route('admin.home') }}" @if ($isAdminDashboard) aria-current="page" @endif>
                        <span class="admin-nav-icon" aria-hidden="true">⌂</span>
                        <span>Dashboard</span>
                    </a>
                    @can('manage-cultural-content')
                        <a @class(['is-active' => $isAdminCulturalEntries]) href="{{ route('admin.cultural-entries.manage') }}" @if ($isAdminCulturalEntries) aria-current="page" @endif>
                            <span class="admin-nav-icon" aria-hidden="true">▤</span>
                            <span>Data Budaya</span>
                        </a>
                    @endcan
                    <a href="{{ route('admin.home', ['verification_status' => 'pending']) }}">
                        <span class="admin-nav-icon" aria-hidden="true">◷</span>
                        <span>Verifikasi</span>
                    </a>
                </nav>

                <p class="admin-sidebar-label">PENGELOLAAN</p>
                <nav class="admin-sidebar-nav" aria-label="Fitur pengelolaan lainnya">
                    @if (Route::has('admin.regions.page'))
                        <a @class(['is-active' => request()->routeIs('admin.regions.page')]) href="{{ route('admin.regions.page') }}" @if (request()->routeIs('admin.regions.page')) aria-current="page" @endif>
                            <span class="admin-nav-icon" aria-hidden="true">⌖</span>
                            <span>Wilayah</span>
                        </a>
                    @else
                        <span class="admin-nav-unavailable" aria-disabled="true">
                            <span class="admin-nav-icon" aria-hidden="true">⌖</span>
                            <span>Wilayah</span>
                            <small>Segera</small>
                        </span>
                    @endif
                    @if (Route::has('admin.categories.page'))
                        <a @class(['is-active' => request()->routeIs('admin.categories.page')]) href="{{ route('admin.categories.page') }}" @if (request()->routeIs('admin.categories.page')) aria-current="page" @endif>
                            <span class="admin-nav-icon" aria-hidden="true">◇</span>
                            <span>Kategori</span>
                        </a>
                    @else
                        <span class="admin-nav-unavailable" aria-disabled="true">
                            <span class="admin-nav-icon" aria-hidden="true">◇</span>
                            <span>Kategori</span>
                            <small>Segera</small>
                        </span>
                    @endif
                    @canany(['manage-media', 'manage-sources'])
                        <a @class(['is-active' => $isAdminMediaSources]) href="{{ route('admin.media-sources.page') }}" @if ($isAdminMediaSources) aria-current="page" @endif>
                            <span class="admin-nav-icon" aria-hidden="true">▧</span>
                            <span>Media dan Sumber</span>
                        </a>
                    @endcanany
                    @can('manage-admin-accounts')
                        <a @class(['is-active' => $isAdminUsers]) href="{{ route('admin.users.page') }}" @if ($isAdminUsers) aria-current="page" @endif>
                            <span class="admin-nav-icon" aria-hidden="true">♙</span>
                            <span>Manajemen Pengguna</span>
                        </a>
                        <a @class(['is-active' => $isAdminAnalytics]) href="{{ route('admin.analytics.page') }}" @if ($isAdminAnalytics) aria-current="page" @endif>
                            <span class="admin-nav-icon" aria-hidden="true">▥</span>
                            <span>Analitik Publik</span>
                        </a>
                    @endcan
                </nav>

                <a class="admin-sidebar-site-link" href="{{ route('home') }}" target="_blank" rel="noopener noreferrer">
                    <span aria-hidden="true">↗</span> Lihat situs publik
                </a>
                <div class="admin-sidebar-note">
                    <span class="admin-sidebar-note-mark" aria-hidden="true">✳</span>
                    <p>Merawat cerita, menjaga warisan.</p>
                </div>
            </aside>
            <div class="admin-dashboard-column">
                <header class="admin-topbar">
                    <div class="admin-topbar-context">
                        <span class="admin-topbar-kicker">WARNA BUDAYA / ADMIN</span>
                        <span class="admin-topbar-title">Ruang pengelola</span>
                    </div>
                    <div class="admin-topbar-account">
                        <span class="admin-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
                        <span class="admin-topbar-user">
                            <strong>{{ auth()->user()->name }}</strong>
                            <small>{{ str_replace('_', ' ', auth()->user()->role) }}</small>
                        </span>
                        <form action="{{ route('admin.logout') }}" method="POST">
                            @csrf
                            <button type="submit">Keluar</button>
                        </form>
                    </div>
                </header>
    @else
        <header class="admin-header">
            <div class="admin-header-inner">
                <a class="admin-brand" href="{{ route('admin.home') }}">
                    <img class="admin-brand-mark" src="{{ asset('images/brand-emblem.svg') }}" alt="">
                    <span class="admin-brand-copy">
                        <strong>WARNA BUDAYA</strong>
                        <small>Jaga Budaya, Lestarikan Negeri</small>
                    </span>
                </a>
                <nav class="admin-nav" aria-label="Navigasi administrasi">
                    <a href="{{ route('admin.home') }}">Kurasi budaya</a>
                    <a href="{{ route('home') }}" target="_blank" rel="noopener noreferrer">Lihat situs ↗</a>
                </nav>
                <div class="admin-account">
                    <span>{{ auth()->user()->name }}</span>
                    <small>{{ str_replace('_', ' ', auth()->user()->role) }}</small>
                    <form action="{{ route('admin.logout') }}" method="POST">
                        @csrf
                        <button type="submit">Keluar</button>
                    </form>
                </div>
            </div>
        </header>
    @endif

    <main class="admin-main">
        @if (session('status'))
            <div class="admin-flash admin-flash-success" role="status">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="admin-flash admin-flash-error" role="alert">
                <strong>Periksa kembali tindakan ini.</strong>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        @yield('content')
    </main>
    @if ($usesAdminShell)
            </div>
        </div>
    @endif
    @if ($isAdminTaxonomy)
        <script src="{{ rtrim(request()->getBaseUrl(), '/') }}/js/admin-taxonomy.js" defer></script>
    @endif
    @if ($isAdminCulturalEntries)
        <script src="{{ rtrim(request()->getBaseUrl(), '/') }}/js/admin-cultural-entries.js" defer></script>
    @endif
    @if ($isAdminMediaSources)
        <script src="{{ rtrim(request()->getBaseUrl(), '/') }}/js/admin-media-sources.js" defer></script>
    @endif
    @if ($isAdminUsers)
        <script src="{{ rtrim(request()->getBaseUrl(), '/') }}/js/admin-users.js?v={{ filemtime(public_path('js/admin-users.js')) }}" defer></script>
    @endif
    @if ($isAdminAnalytics)
        <script src="{{ rtrim(request()->getBaseUrl(), '/') }}/js/admin-analytics.js" defer></script>
    @endif
</body>
</html>
