@extends('layouts.app')

@section('title', 'Jelajah Nusantara — WARNA BUDAYA')
@section('meta_description', 'Jelajahi warisan budaya Indonesia melalui provinsi dan cerita dari seluruh Nusantara.')

@section('content')
    <section class="region-intro">
        <div class="page-shell region-intro-inner">
            <p class="eyebrow"><span class="eyebrow-line"></span> Peta dan arsip digital warisan budaya Indonesia</p>
            <h1>Jelajah <em>Nusantara.</em></h1>
            <p class="region-intro-copy">Dari ujung barat hingga timur, setiap wilayah menyimpan kisah yang tumbuh bersama masyarakatnya. Pilih sebuah provinsi untuk mulai mengenal warisannya.</p>
            <form class="region-search" action="{{ route('regions.index') }}" method="GET" role="search">
                <label class="sr-only" for="region-search">Cari provinsi</label>
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.8" cy="10.8" r="6.8"></circle><path d="m16 16 4.5 4.5"></path></svg>
                <input id="region-search" type="search" name="search" placeholder="Cari nama provinsi..." value="{{ request('search') }}">
                <button type="submit">Temukan</button>
            </form>
            <span class="region-intro-note"><span aria-hidden="true">✳</span> Kenali kekayaan budaya dari {{ $provinceCount }} provinsi</span>
            <img class="region-intro-seal" src="{{ asset('images/brand-emblem.svg') }}" alt="">
        </div>
    </section>

    <section class="region-list-section section-space">
        <div class="page-shell">
            <div class="section-heading region-section-heading">
                <div>
                    <p class="eyebrow"><span class="eyebrow-line"></span> Ragam wilayah</p>
                    <h2 class="section-title">Kenali kekayaan budaya <em>dari {{ $provinceCount }} provinsi.</em></h2>
                </div>
                <span class="region-result-count">{{ $regions->count() }} wilayah</span>
            </div>

            @if ($regions->isNotEmpty())
                <div class="region-grid">
                    @foreach ($regions as $region)
                        <article class="region-card">
                            <div class="region-card-image region-card-image-{{ ($loop->index % 5) + 1 }}">
                                @if ($region->artwork && file_exists(public_path($region->artwork['path'])))
                                    <img
                                        src="{{ asset($region->artwork['path']) }}"
                                        alt="{{ $region->artwork['alt'] }}"
                                        loading="lazy"
                                    >
                                    <span class="region-image-caption">{{ $region->artwork['caption'] }}</span>
                                    <a class="region-image-credit" href="{{ $region->artwork['source'] }}" target="_blank" rel="noopener noreferrer">
                                        {{ $region->artwork['credit'] }} <span class="sr-only">(sumber gambar)</span>
                                    </a>
                                @else
                                    <span class="region-art-fallback" aria-label="Ilustrasi arsip dekoratif; foto daerah belum tersedia">
                                        <span class="region-fallback-rosette" aria-hidden="true"></span>
                                        <span class="region-fallback-code">{{ $region->code ?: str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                                        <span class="region-fallback-label">Ilustrasi arsip · foto belum tersedia</span>
                                    </span>
                                @endif
                            </div>
                            <div class="region-card-copy">
                                <span class="region-card-kicker">Provinsi · {{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                                <h3 class="region-card-name">{{ $region->name }}</h3>
                                <p class="region-card-description">{{ $region->description ?: 'Arsip budaya dan identitas wilayah dari '.$region->name.'.' }}</p>
                                <div class="region-card-meta">
                                    <span>{{ number_format($region->cultural_entries_count, 0, ',', '.') }} arsip budaya</span>
                                    <a class="region-card-link" href="{{ route('regions.show', $region->slug) }}">Jelajahi <span aria-hidden="true">↗</span></a>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            @else
                <div class="region-empty">
                    <span aria-hidden="true">✳</span>
                    <h3>{{ request('search') ? 'Provinsi belum ditemukan.' : 'Jelajah sedang dipersiapkan.' }}</h3>
                    <p>{{ request('search') ? 'Coba kata kunci lain untuk menemukan wilayah yang Anda cari.' : 'Daftar provinsi akan hadir setelah data wilayah tersedia.' }}</p>
                    @if (request('search'))
                        <a class="text-link" href="{{ route('regions.index') }}">Lihat semua provinsi <span aria-hidden="true">↗</span></a>
                    @endif
                </div>
            @endif
        </div>
    </section>
@endsection
