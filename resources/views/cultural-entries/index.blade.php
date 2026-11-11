@extends('layouts.app')

@section('title', 'Arsip Budaya Nusantara | WARNA BUDAYA')
@section('meta_description', 'Jelajahi kisah budaya dari berbagai wilayah Indonesia yang telah disetujui untuk publikasi.')
@section('body_class', 'archive-page')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/archive.css') }}">
@endpush

@section('content')
    <section class="archive-hero" aria-labelledby="archive-title">
        <div class="page-shell archive-hero-inner">
            <p class="eyebrow eyebrow-light"><span class="eyebrow-line"></span> Galeri digital warisan budaya</p>
            <h1 id="archive-title">Arsip Budaya <em>Nusantara.</em></h1>
            <p class="archive-hero-copy">Sebuah ruang untuk mengenali tradisi, pengetahuan, dan karya yang menjaga cerita Indonesia tetap hidup.</p>
            <div class="archive-hero-meta">
                <span class="archive-hero-mark" aria-hidden="true">✳</span>
                <span>{{ number_format($entries->total(), 0, ',', '.') }} arsip terkurasi</span>
                <span class="archive-meta-divider" aria-hidden="true"></span>
                <span>Warisan dari berbagai penjuru Indonesia</span>
            </div>
        </div>
        <img class="archive-hero-seal" src="{{ asset('images/brand-emblem.svg') }}" alt="">
    </section>

    <section class="archive-catalog section-space" aria-labelledby="archive-catalog-title">
        <div class="page-shell">
            <div class="archive-heading">
                <div>
                    <p class="eyebrow"><span class="eyebrow-line"></span> Koleksi terkurasi</p>
                    <h2 id="archive-catalog-title" class="section-title">Temukan kisah <em>di balik warisan.</em></h2>
                </div>
                <p class="archive-heading-note">Jelajahi kisah budaya yang telah disetujui untuk hadir di ruang publik.</p>
            </div>

            <div class="archive-tools">
                <form class="archive-search" action="{{ route('cultural-entries.index') }}" method="GET" role="search">
                    <div class="archive-search-row">
                        <label class="sr-only" for="archive-search-input">Cari arsip budaya</label>
                        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.8" cy="10.8" r="6.8"></circle><path d="m16 16 4.5 4.5"></path></svg>
                        <input
                            id="archive-search-input"
                            type="search"
                            name="search"
                            value="{{ request('search') }}"
                            placeholder="Cari tarian, kain, tradisi, dan lainnya"
                        >
                    </div>
                    <div class="archive-filter-row">
                        <label class="archive-filter">
                            <span>Wilayah</span>
                            <select name="region_id" aria-label="Filter berdasarkan provinsi">
                                <option value="">Semua provinsi</option>
                                @foreach ($regions as $region)
                                    <option value="{{ $region->id }}" @selected((string) request('region_id') === (string) $region->id)>
                                        {{ $region->name }}
                                    </option>
                                @endforeach
                            </select>
                        </label>
                        <label class="archive-filter">
                            <span>Kategori</span>
                            <select name="category_id" aria-label="Filter berdasarkan kategori">
                                <option value="">Semua kategori</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}" @selected((string) request('category_id') === (string) $category->id)>
                                        {{ $category->name }}
                                    </option>
                                @endforeach
                            </select>
                        </label>
                        <button class="archive-filter-submit" type="submit">Terapkan</button>
                    </div>
                </form>
                <div class="archive-tools-summary">
                    <p class="archive-result-count">
                        Menampilkan {{ number_format($entries->total(), 0, ',', '.') }} arsip
                        @if (request()->filled('search'))
                            untuk “{{ request('search') }}”
                        @endif
                    </p>
                    @if (request()->filled('search') || request()->filled('region_id') || request()->filled('category_id'))
                        <a class="archive-clear-filters" href="{{ route('cultural-entries.index') }}">Hapus pencarian &amp; filter</a>
                    @endif
                </div>
            </div>

            @if ($entries->isNotEmpty())
                <div class="archive-grid">
                    @foreach ($entries as $entry)
                        @php
                            $archiveCover = $entry->media->first(
                                fn ($media) => $media->media_type === 'image' && ($media->external_url || $media->file_path)
                            );
                            $archiveCoverUrl = $archiveCover
                                ? $archiveCover->publicUrl()
                                : null;
                            $editorialIllustration = match ($entry->slug) {
                                'rumah-honai' => asset('images/cultural/editorial/rumah-honai.svg'),
                                default => null,
                            };
                        @endphp

                        <article class="archive-card">
                            <a class="archive-card-visual" href="{{ route('cultural-entries.show', $entry->slug) }}" aria-label="Baca detail {{ $entry->title }}">
                                @if ($archiveCoverUrl)
                                    <img
                                        src="{{ $archiveCoverUrl }}"
                                        alt="{{ $archiveCover->alt_text ?: $entry->title }}"
                                        loading="lazy"
                                    >
                                    <span class="archive-image-credit">{{ $archiveCover->caption ?: 'Dokumentasi arsip budaya' }}</span>
                                @else
                                    @if ($editorialIllustration)
                                        <span class="archive-art-fallback has-editorial-illustration">
                                            <img src="{{ $editorialIllustration }}" alt="Ilustrasi editorial {{ $entry->title }}; bukan foto dokumentasi autentik" loading="lazy">
                                            <span class="archive-image-credit">Ilustrasi editorial · bukan foto dokumentasi</span>
                                        </span>
                                    @else
                                        <span class="archive-art-fallback" aria-hidden="true">
                                            <img class="archive-fallback-mark" src="{{ asset('images/brand-emblem.svg') }}" alt="">
                                            <span>Arsip budaya Nusantara</span>
                                        </span>
                                    @endif
                                @endif
                                <span class="archive-card-index" aria-hidden="true">{{ str_pad((string) $loop->iteration + (($entries->currentPage() - 1) * $entries->perPage()), 2, '0', STR_PAD_LEFT) }}</span>
                            </a>

                            <div class="archive-card-content">
                                <p class="archive-card-region">
                                    <span aria-hidden="true">✳</span>
                                    {{ $entry->region?->name ?? 'Wilayah belum tersedia' }}
                                </p>
                                <h3>
                                    <a href="{{ route('cultural-entries.show', $entry->slug) }}">{{ $entry->title }}</a>
                                </h3>
                                @if ($entry->short_description)
                                    <p class="archive-card-description">{{ $entry->short_description }}</p>
                                @endif

                                @if ($entry->categories->isNotEmpty())
                                    <ul class="archive-card-categories" aria-label="Kategori">
                                        @foreach ($entry->categories as $category)
                                            <li>{{ $category->name }}</li>
                                        @endforeach
                                    </ul>
                                @endif

                                <a class="archive-card-link" href="{{ route('cultural-entries.show', $entry->slug) }}">
                                    Jelajahi arsip <span aria-hidden="true">↗</span>
                                </a>
                            </div>
                        </article>
                    @endforeach
                </div>

                @if ($entries->hasPages())
                    <nav class="archive-pagination" aria-label="Navigasi halaman arsip">
                        {{ $entries->links() }}
                    </nav>
                @endif
            @else
                <div class="archive-empty" role="status">
                    <div class="archive-empty-art" aria-hidden="true">
                        <span class="archive-empty-orbit archive-empty-orbit-one"></span>
                        <span class="archive-empty-orbit archive-empty-orbit-two"></span>
                        <img class="archive-empty-seal" src="{{ asset('images/brand-emblem.svg') }}" alt="">
                        <span class="archive-empty-mark">✳</span>
                    </div>
                    <p class="archive-empty-kicker">Ruang arsip sedang bertumbuh</p>
                    <h3>
                        @if (request()->filled('search') || request()->filled('region_id') || request()->filled('category_id'))
                            Belum ada kisah yang cocok.
                        @else
                            Kisah terkurasi segera hadir.
                        @endif
                    </h3>
                    <p>
                        @if (request()->filled('search') || request()->filled('region_id') || request()->filled('category_id'))
                            Coba kata kunci atau pilihan filter lain. Arsip hanya memuat budaya yang telah disetujui untuk dipublikasikan.
                        @else
                            Artikel tampil setelah disetujui Admin atau Super Admin. Materi visual hanya hadir jika izinnya granted dan file atau URL tersedia.
                        @endif
                    </p>
                    @if (request()->filled('search') || request()->filled('region_id') || request()->filled('category_id'))
                        <a href="{{ route('cultural-entries.index') }}">Lihat seluruh arsip</a>
                    @endif
                </div>
            @endif
        </div>
    </section>
@endsection
