@extends('layouts.app')

@section('title', 'Cerita Nusantara — Digital Knowledge Book')
@section('meta_description', 'Jelajahi buku digital editorial tentang batik, arsitektur, rempah, teknologi tradisional, dan ekologi Nusantara.')
@section('body_class', 'digital-books-page')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/digital-books.css') }}">
@endpush

@push('scripts')
    <script src="{{ asset('js/digital-books.js') }}" defer></script>
@endpush

@section('content')
    @php
        $featuredBook = $books[0];
        $featuredCover = \Illuminate\Support\Str::startsWith($featuredBook['cover'], ['https://', 'http://'])
            ? $featuredBook['cover']
            : asset($featuredBook['cover']);
    @endphp

    <section class="library-masthead">
        <div class="page-shell library-masthead-inner">
            <p class="eyebrow eyebrow-light"><span class="eyebrow-line"></span> WARNA BUDAYA · Knowledge Books</p>
            <h1>Pengetahuan Nusantara,<br><em>dibaca lebih dekat.</em></h1>
            <p>Esai bergambar tentang warisan yang nyata: siapa yang membangunnya, pengetahuan di baliknya, dan bagaimana ia bertahan hari ini.</p>
            <a class="masthead-scroll" href="#daftar-buku">Jelajahi koleksi <span aria-hidden="true">↓</span></a>
            <span class="masthead-index" aria-hidden="true">01 / 05</span>
        </div>
    </section>

    <section class="library-feature page-shell" aria-label="Buku pilihan">
        <div class="library-feature-copy">
            <p class="eyebrow"><span class="eyebrow-line"></span> Edisi perdana · {{ $featuredBook['category'] }}</p>
            <h2>{{ $featuredBook['title'] }}<span>{{ $featuredBook['subtitle'] }}</span></h2>
            <p>{{ $featuredBook['description'] }}</p>
            <div class="feature-meta"><span>{{ $featuredBook['reading_time'] }} membaca</span><span>{{ count($featuredBook['chapters']) }} bab</span></div>
            <a class="feature-read-link" href="{{ route('digital-books.show', $featuredBook['slug']) }}">Buka buku digital <span aria-hidden="true">↗</span></a>
        </div>
        <a class="library-feature-art" href="{{ route('digital-books.show', $featuredBook['slug']) }}" aria-label="Buka buku {{ $featuredBook['title'] }}">
            <img src="{{ $featuredCover }}" alt="{{ $featuredBook['cover_alt'] }}" fetchpriority="high">
            <span class="feature-art-index">FIELD NOTES<br>INDONESIA</span>
            <span class="feature-art-caption">{{ $featuredBook['cover_caption'] }}</span>
        </a>
    </section>

    <section class="library-collection" id="daftar-buku" aria-labelledby="collection-title">
        <div class="page-shell">
            <div class="collection-heading">
                <div>
                    <p class="eyebrow"><span class="eyebrow-line"></span> Perpustakaan tematik</p>
                    <h2 id="collection-title">Pilih sebuah <em>penjelajahan.</em></h2>
                </div>
                <p>Lima sudut pandang. Puluhan cerita tentang lingkungan, keterampilan, dan ingatan masyarakat Indonesia.</p>
            </div>

            <div class="library-tools" data-library-tools>
                <label class="library-search">
                    <span class="sr-only">Cari buku berdasarkan judul atau topik</span>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.8" cy="10.8" r="6.8"></circle><path d="m16 16 4.5 4.5"></path></svg>
                    <input type="search" data-book-search placeholder="Cari topik, tempat, pengetahuan…">
                </label>
                <div class="library-filters" role="group" aria-label="Filter kategori buku">
                    <button class="library-filter is-active" type="button" data-book-filter="all" aria-pressed="true">Semua edisi</button>
                    @foreach (collect($books)->pluck('category')->unique() as $category)
                        <button class="library-filter" type="button" data-book-filter="{{ $category }}" aria-pressed="false">{{ $category }}</button>
                    @endforeach
                </div>
            </div>

            <div class="book-index-list" data-book-list>
                @foreach (array_slice($books, 1) as $book)
                    @php
                        $coverUrl = \Illuminate\Support\Str::startsWith($book['cover'], ['https://', 'http://'])
                            ? $book['cover']
                            : asset($book['cover']);
                        $searchText = \Illuminate\Support\Str::lower($book['title'].' '.$book['subtitle'].' '.$book['category'].' '.$book['origin'].' '.$book['description']);
                    @endphp
                    <article class="book-index-entry" data-book-entry data-category="{{ $book['category'] }}" data-search="{{ $searchText }}">
                        <span class="book-index-number" aria-hidden="true">{{ str_pad((string) ($loop->iteration + 1), 2, '0', STR_PAD_LEFT) }}</span>
                        <a class="book-index-cover" href="{{ route('digital-books.show', $book['slug']) }}" tabindex="-1" aria-hidden="true">
                            <img src="{{ $coverUrl }}" alt="" loading="lazy" decoding="async">
                        </a>
                        <div class="book-index-copy">
                            <span class="book-index-category">{{ $book['category'] }} <i>·</i> {{ $book['origin'] }}</span>
                            <h3><a href="{{ route('digital-books.show', $book['slug']) }}">{{ $book['title'] }}</a></h3>
                            <p class="book-index-subtitle">{{ $book['subtitle'] }}</p>
                            <p class="book-index-description">{{ $book['description'] }}</p>
                            <span class="book-index-meta">{{ count($book['chapters']) }} bab <i>·</i> {{ $book['reading_time'] }} membaca</span>
                        </div>
                        <a class="book-index-open" href="{{ route('digital-books.show', $book['slug']) }}" aria-label="Baca {{ $book['title'] }}">Baca buku <span aria-hidden="true">↗</span></a>
                    </article>
                @endforeach
            </div>
            <p class="library-empty" data-library-empty hidden>Tidak ada buku yang cocok. Coba kata kunci atau kategori lain.</p>
        </div>
    </section>

    <section class="library-note">
        <div class="page-shell library-note-inner">
            <span class="library-note-mark" aria-hidden="true">✳</span>
            <p>Disusun sebagai pengantar berbasis sumber terbuka. Tiap buku menyertakan bacaan rujukan; pengetahuan budaya tetap milik masyarakat yang merawatnya.</p>
            <a href="{{ route('home') }}#wisata-indonesia">Temukan tempat yang menghidupkan pengetahuan <span aria-hidden="true">↗</span></a>
        </div>
    </section>
@endsection
