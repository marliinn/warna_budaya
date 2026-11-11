@extends('admin.layouts.app')

@section('title', 'Analitik Publik | WARNA BUDAYA')

@section('content')
    <div
        class="ua-config"
        data-analytics-config
        data-summary-url="{{ route('admin.api.analytics.summary') }}"
        data-traffic-url="{{ route('admin.api.analytics.traffic') }}"
        data-devices-url="{{ route('admin.api.analytics.devices') }}"
        data-pages-url="{{ route('admin.api.analytics.pages') }}"
        data-geography-url="{{ route('admin.api.analytics.geography') }}"
    ></div>

    <section class="admin-page-heading ua-heading">
        <div>
            <p class="admin-eyebrow"><span></span> Pengunjung situs publik</p>
            <h1>Analitik <em>Kunjungan.</em></h1>
            <p>Ringkasan anonim kunjungan halaman publik WARNA BUDAYA. Data lokasi hanya ditampilkan bila tersedia.</p>
        </div>
        <img class="admin-heading-stamp" src="{{ asset('images/brand-emblem.svg') }}" alt="">
    </section>

    <section class="ua-panel ua-analytics-filters" aria-label="Filter analitik">
        <form data-analytics-filter-form>
            <label>Dari tanggal<input type="date" name="date_from" required></label>
            <label>Sampai tanggal<input type="date" name="date_to" required></label>
            <label>Pengelompokan
                <select name="period"><option value="daily">Harian</option><option value="weekly">Mingguan</option><option value="monthly">Bulanan</option></select>
            </label>
            <button type="submit" class="ua-button ua-button-primary">Terapkan filter</button>
        </form>
        <p class="ua-feedback" data-analytics-feedback role="status" aria-live="polite" hidden></p>
        <p class="ua-analytics-note" data-analytics-status></p>
    </section>

    <section class="ua-stat-grid" aria-label="Ringkasan statistik">
        <article class="ua-stat-card"><span>Pengunjung unik</span><strong data-stat="unique_visitors">—</strong><small>Perkiraan anonim dalam rentang terpilih</small></article>
        <article class="ua-stat-card"><span>Sesi kunjungan</span><strong data-stat="sessions">—</strong><small>Sesi anonim yang teramati</small></article>
        <article class="ua-stat-card"><span>Page views</span><strong data-stat="page_views">—</strong><small>Halaman publik yang dimuat</small></article>
        <article class="ua-stat-card"><span>Pengunjung hari ini</span><strong data-stat="today_visitors">—</strong><small>Pengunjung unik pada hari ini</small></article>
        <article class="ua-stat-card"><span>Pengunjung baru</span><strong data-stat="new_visitors">—</strong><small>Pertama kali tercatat pada rentang ini</small></article>
        <article class="ua-stat-card"><span>Pengunjung berulang</span><strong data-stat="returning_visitors">—</strong><small>Pernah tercatat sebelum rentang ini</small></article>
    </section>

    <section class="ua-panel ua-chart-panel">
        <div class="ua-panel-heading"><div><p class="admin-eyebrow"><span></span> Tren kunjungan</p><h2>Traffic publik</h2></div><span class="ua-chart-legend"><i></i> Pengunjung unik</span></div>
        <div class="ua-chart-wrap" data-traffic-chart>
            <div class="ua-state"><strong>Memuat grafik…</strong><small>Data kunjungan dirangkum sesuai periode.</small></div>
        </div>
    </section>

    <div class="ua-analytics-grid">
        <section class="ua-panel">
            <div class="ua-panel-heading"><div><p class="admin-eyebrow"><span></span> Perangkat</p><h2>Jenis perangkat</h2></div></div>
            <div data-device-chart class="ua-breakdown"><div class="ua-state"><strong>Memuat data…</strong></div></div>
        </section>
        <section class="ua-panel">
            <div class="ua-panel-heading"><div><p class="admin-eyebrow"><span></span> Teknologi</p><h2>Browser dan sistem operasi</h2></div></div>
            <div class="ua-tech-columns">
                <div><h3>Browser</h3><div data-browser-list class="ua-breakdown"><div class="ua-state"><strong>Memuat data…</strong></div></div></div>
                <div><h3>Sistem operasi</h3><div data-os-list class="ua-breakdown"><div class="ua-state"><strong>Memuat data…</strong></div></div></div>
            </div>
        </section>
        <section class="ua-panel">
            <div class="ua-panel-heading"><div><p class="admin-eyebrow"><span></span> Konten populer</p><h2>Halaman teratas</h2></div></div>
            <div class="ua-table-wrap"><table class="ua-table ua-compact-table"><thead><tr><th>Halaman</th><th>Page views</th><th>Unik</th></tr></thead><tbody data-popular-pages><tr><td colspan="3" class="ua-state">Memuat halaman…</td></tr></tbody></table></div>
        </section>
        <section class="ua-panel">
            <div class="ua-panel-heading"><div><p class="admin-eyebrow"><span></span> Persebaran</p><h2>Lokasi perkiraan</h2></div></div>
            <p class="ua-location-note" data-location-note>Memeriksa ketersediaan data lokasi…</p>
            <div class="ua-tech-columns">
                <div><h3>Negara</h3><div data-countries-list class="ua-breakdown"></div></div>
                <div><h3>Provinsi dan kota</h3><div data-provinces-list class="ua-breakdown"></div><div data-cities-list class="ua-breakdown ua-city-list"></div></div>
            </div>
        </section>
    </div>
@endsection
