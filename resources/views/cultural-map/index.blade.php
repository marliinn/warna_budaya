@extends('layouts.app')

@section('title', 'Peta Interaktif Nusantara — WARNA BUDAYA')
@section('meta_description', 'Jelajahi lokasi budaya Indonesia lewat peta interaktif ber-relief. Temukan rumah adat, kain, tari, musik, dan tradisi dari Sumatra hingga Papua.')
@section('body_class', 'cultural-map-page')

@push('styles')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
    <link rel="stylesheet" href="{{ asset('css/cultural-map.css') }}">
@endpush

@section('content')
    <section class="culture-map-hero">
        <div class="page-shell culture-map-hero-inner">
            <div class="culture-map-hero-copy">
                <p class="culture-map-overline">✳ &nbsp; Atlas budaya digital Nusantara &nbsp; ✳</p>
                <p class="culture-map-wonderful">WONDERFUL</p>
                <h1>INDONESIA</h1>
                <p class="culture-map-tagline">Unity in diversity · Beauty in heritage</p>
                <div class="culture-map-hero-meta"><span>{{ collect($entries)->pluck('province')->unique()->count() }} provinsi</span><i></i><span>{{ count($entries) }} kisah budaya</span><i></i><span>8 kawasan kepulauan</span></div>
            </div>
            <div class="culture-map-hero-art" aria-hidden="true">
                <img src="{{ asset('images/maps/nusantara-isometric.svg') }}" alt="" decoding="async" fetchpriority="low">
                <span class="culture-map-art-caption">Dari Sumatra hingga Papua<br><em>Ragam pulau, satu Nusantara.</em></span>
            </div>
        </div>
    </section>

    <section class="culture-atlas-section" aria-labelledby="culture-atlas-heading">
        <div class="page-shell">
            <div class="culture-atlas-heading">
                <div>
                    <p class="eyebrow"><span class="eyebrow-line"></span> Peta interaktif Nusantara</p>
                    <h2 id="culture-atlas-heading">Cerita dari setiap <em>penjuru.</em></h2>
                </div>
                <p>38 provinsi terwakili, dengan titik budaya yang tersebar dari Sumatra hingga Papua. Geser atau perbesar peta untuk menjelajah.</p>
            </div>

            <div class="culture-map-toolbar" role="search">
                <label class="culture-map-search">
                    <span class="sr-only">Cari nama budaya atau daerah</span>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.8" cy="10.8" r="6.8"></circle><path d="m16 16 4.5 4.5"></path></svg>
                    <input id="culture-map-search" type="search" placeholder="Cari budaya atau daerah..." autocomplete="off">
                    <kbd>/</kbd>
                </label>
                <label class="culture-map-filter">
                    <span>Pulau</span>
                    <select id="culture-map-island" aria-label="Filter berdasarkan pulau">
                        <option value="">Semua pulau</option>
                        @foreach (collect($entries)->pluck('island')->unique()->sort() as $island)
                            <option value="{{ $island }}">{{ $island }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="culture-map-filter">
                    <span>Provinsi</span>
                    <select id="culture-map-province" aria-label="Filter berdasarkan provinsi">
                        <option value="">Semua provinsi</option>
                        @foreach (collect($entries)->pluck('province')->unique()->sort() as $province)
                            <option value="{{ $province }}">{{ $province }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="culture-map-filter">
                    <span>Kategori</span>
                    <select id="culture-map-category" aria-label="Filter berdasarkan kategori">
                        <option value="">Semua kategori</option>
                        @foreach (collect($entries)->pluck('category')->unique()->sort() as $category)
                            <option value="{{ $category }}">{{ $category }}</option>
                        @endforeach
                    </select>
                </label>
                <button class="culture-map-reset" id="culture-map-reset" type="button">Atur ulang</button>
            </div>

            <div class="culture-map-workspace">
                <div class="culture-map-canvas-wrap">
                    <div class="culture-map-map-topline">
                        <span><i></i> {{ collect($entries)->pluck('province')->unique()->count() }} provinsi · {{ count($entries) }} catatan budaya</span>
                        <button id="culture-map-fit" type="button" aria-label="Tampilkan seluruh kepulauan Indonesia">Jelajahi seluruh peta <span aria-hidden="true">↗</span></button>
                    </div>
                    <div id="culture-map" data-map-image="{{ asset('images/maps/nusantara-isometric.svg') }}" role="application" aria-label="Ilustrasi relief interaktif Indonesia dengan penanda lokasi budaya"></div>
                    <div class="culture-map-map-legend"><span><i></i> Titik budaya</span><span><i class="legend-active"></i> Pilihanmu</span></div>
                    <p id="culture-map-status" class="culture-map-status" role="status" aria-live="polite"></p>
                </div>

                <aside class="culture-map-side" aria-label="Informasi dan daftar budaya">
                    <div class="culture-map-detail" id="culture-map-detail" aria-live="polite">
                        <div class="culture-map-detail-empty">
                            <span class="detail-empty-mark" aria-hidden="true">✳</span>
                            <p class="culture-map-detail-kicker">Catatan lapangan</p>
                            <h3>Pilih sebuah penanda.</h3>
                            <p>Sentuh titik di peta atau pilih nama budaya pada daftar untuk membuka cerita dan keunikannya.</p>
                        </div>
                    </div>
                    <div class="culture-map-list-heading">
                        <div><span class="culture-map-detail-kicker">Indeks budaya</span><h3>Temukan cerita</h3></div>
                        <span id="culture-map-count">{{ count($entries) }} lokasi</span>
                    </div>
                    <div class="culture-map-list" id="culture-map-list" aria-label="Daftar lokasi budaya">
                        @foreach ($entries as $entry)
                            <button class="culture-map-list-item" type="button" data-map-id="{{ $entry['id'] }}">
                                <span class="culture-map-list-icon" aria-hidden="true">{{ $entry['icon'] }}</span>
                                <span class="culture-map-list-copy"><strong>{{ $entry['name'] }}</strong><small>{{ $entry['region'] }}</small></span>
                                <span class="culture-map-list-arrow" aria-hidden="true">↗</span>
                            </button>
                        @endforeach
                        <p class="culture-map-no-results" id="culture-map-no-results" hidden>Belum ada budaya yang cocok. Coba kata kunci atau filter lain.</p>
                    </div>
                </aside>
            </div>

            <p class="culture-map-source-note">Siluet kepulauan merupakan ilustrasi yang disederhanakan dari <a href="https://github.com/superpikar/indonesia-geojson" target="_blank" rel="noopener noreferrer">data geoJSON Indonesia</a> (geometri dasar BAKOSURTANAL). Bukan peta batas administratif resmi. Lokasi titik menunjukkan kawasan budaya, bukan batas wilayah adat.</p>
        </div>
    </section>
    @php
        $mapEntries = array_map(function (array $entry): array {
            $entry['image_url'] = $entry['image'] ? asset($entry['image']) : null;

            return $entry;
        }, $entries);
    @endphp
    <script id="culture-map-data" type="application/json">{!! json_encode($mapEntries, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
@endsection

@push('scripts')
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script src="{{ asset('js/cultural-map.js') }}" defer></script>
@endpush
