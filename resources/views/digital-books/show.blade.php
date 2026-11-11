@extends('layouts.app')

@section('title', $book['title'] . ' — Cerita Nusantara')
@section('meta_description', $book['description'])
@section('body_class', 'digital-books-page digital-book-reader-page')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/digital-books.css') }}">
@endpush

@push('scripts')
    <script src="{{ asset('js/digital-books.js') }}" defer></script>
@endpush

@section('content')
    @php
        $coverUrl = \Illuminate\Support\Str::startsWith($book['cover'], ['https://', 'http://'])
            ? $book['cover']
            : asset($book['cover']);
        $readerPages = array_merge([['type' => 'cover']], $book['chapters']);
    @endphp

    <main class="knowledge-reader" data-book-reader data-book-slug="{{ $book['slug'] }}" data-page-count="{{ count($readerPages) }}">
        <header class="reader-masthead">
            <a class="reader-return" href="{{ route('digital-books.index') }}"><span aria-hidden="true">←</span> Perpustakaan</a>
            <a class="reader-wordmark" href="{{ route('home') }}">WARNA <span>BUDAYA</span></a>
            <span class="reader-edition">{{ $book['edition'] }}</span>
        </header>

        <div class="reader-progress-line" role="progressbar" aria-label="Kemajuan membaca" aria-valuemin="0" aria-valuemax="{{ count($book['chapters']) }}" aria-valuenow="0">
            <span data-progress-fill></span>
        </div>

        <div class="reader-toolbar">
            <div class="reader-location"><span data-page-current>Sampul</span><span aria-hidden="true"> / </span>{{ count($book['chapters']) }} bab</div>
            <div class="reader-actions">
                <button class="reader-tool" type="button" data-bookmark-toggle aria-pressed="false" aria-label="Simpan halaman sebagai penanda">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 4.8A1.8 1.8 0 0 1 7.8 3h8.4A1.8 1.8 0 0 1 18 4.8V21l-6-4-6 4V4.8Z"></path></svg>
                    <span data-bookmark-label>Simpan halaman</span>
                </button>
                <button class="reader-tool" type="button" data-comfort-toggle aria-pressed="false">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5.5h16M4 12h16M4 18.5h9"></path><path d="M17 16v5m-2.5-2.5h5"></path></svg>
                    <span data-comfort-label>Mode nyaman</span>
                </button>
                <button class="reader-tool reader-tool-theme" type="button" data-theme-toggle aria-pressed="false">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20.5 15.1A8.4 8.4 0 0 1 8.9 3.5 8.6 8.6 0 1 0 20.5 15.1Z"></path></svg>
                    <span data-theme-label>Mode malam</span>
                </button>
            </div>
        </div>

        <div class="reader-layout">
            <aside class="book-contents" aria-label="Daftar isi buku">
                <div class="contents-heading"><span>Di dalam buku</span><span>{{ str_pad((string) count($book['chapters']), 2, '0', STR_PAD_LEFT) }} BAB</span></div>
                <nav>
                    <button class="contents-link is-current" type="button" data-toc-link data-page-index="0" aria-current="page">
                        <span class="contents-number">00</span><span>Sampul buku</span>
                    </button>
                    @foreach ($book['chapters'] as $chapter)
                        <button class="contents-link" type="button" data-toc-link data-page-index="{{ $loop->iteration }}">
                            <span class="contents-number">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                            <span>{{ $chapter['heading'] }}</span>
                            <span class="contents-bookmark" data-bookmark-marker="{{ $loop->iteration }}" aria-label="Halaman ditandai" hidden>◆</span>
                        </button>
                    @endforeach
                </nav>
                <div class="contents-footer">
                    <span>{{ $book['reading_time'] }} membaca</span>
                    <a href="{{ route('home') }}#wisata-indonesia">Eksplorasi wisata <span aria-hidden="true">↗</span></a>
                </div>
            </aside>

            <article class="book-canvas" aria-label="Isi buku digital">
                @foreach ($readerPages as $page)
                    @if (($page['type'] ?? null) === 'cover')
                        <section class="book-spread book-spread-cover" data-book-page aria-label="Sampul {{ $book['title'] }}">
                            <div class="cover-copy">
                                <span class="cover-overline">{{ $book['edition'] }}</span>
                                <span class="cover-category">{{ $book['category'] }} <i>·</i> {{ $book['origin'] }}</span>
                                <h1 tabindex="-1">{{ $book['title'] }}</h1>
                                <p class="cover-subtitle">{{ $book['subtitle'] }}</p>
                                <p class="cover-description">{{ $book['description'] }}</p>
                                <div class="cover-meta"><span>{{ count($book['chapters']) }} bab pilihan</span><span>{{ $book['reading_time'] }}</span></div>
                                <button class="cover-open" type="button" data-open-book>Buka halaman pertama <span aria-hidden="true">→</span></button>
                                <p class="cover-byline">Sebuah atlas pengetahuan budaya dan alam Indonesia</p>
                            </div>
                            <figure class="cover-visual">
                                <img src="{{ $coverUrl }}" alt="{{ $book['cover_alt'] }}" fetchpriority="high">
                                <span class="cover-image-index">FIELD<br>JOURNAL<br>INDONESIA</span>
                                <figcaption>
                                    <span>{{ $book['cover_caption'] }}</span>
                                    <a href="{{ $book['cover_source'] }}" target="_blank" rel="noopener noreferrer">{{ $book['cover_credit'] }} · Sumber foto</a>
                                </figcaption>
                            </figure>
                            <span class="cover-folio" aria-hidden="true">0</span>
                        </section>
                    @else
                        @php
                            $chapterImage = \Illuminate\Support\Str::startsWith($page['image'], ['https://', 'http://'])
                                ? $page['image']
                                : asset($page['image']);
                        @endphp
                        <section class="book-spread book-chapter book-chapter--{{ $page['layout'] }}" data-book-page aria-label="Bab {{ $loop->iteration - 1 }}: {{ $page['heading'] }}" hidden>
                            <header class="chapter-heading">
                                <p><span>BAB {{ str_pad((string) ($loop->iteration - 1), 2, '0', STR_PAD_LEFT) }}</span><i>·</i>{{ $book['category'] }}</p>
                                <h2 tabindex="-1">{{ $page['heading'] }}</h2>
                                <p class="chapter-deck">{{ $page['deck'] }}</p>
                            </header>

                            <figure class="chapter-image">
                                <img src="{{ $chapterImage }}" alt="{{ $page['image_alt'] }}" @if ($loop->iteration === 2) fetchpriority="high" @else loading="lazy" @endif decoding="async">
                                <figcaption>
                                    <span>{{ $page['caption'] }}</span>
                                    <a href="{{ $page['source'] }}" target="_blank" rel="noopener noreferrer">{{ $page['credit'] }} · Lihat sumber</a>
                                </figcaption>
                            </figure>

                            <div class="chapter-body">
                                @foreach ($page['paragraphs'] as $paragraph)
                                    <p>{{ $paragraph }}</p>
                                @endforeach

                                @if (isset($page['fact']))
                                    <aside class="chapter-fact">
                                        <span class="fact-label">{{ $page['fact']['label'] }}</span>
                                        <p>{{ $page['fact']['text'] }}</p>
                                    </aside>
                                @endif

                                @if (isset($page['diagram']))
                                    <figure class="chapter-diagram">
                                        <figcaption>{{ $page['diagram']['title'] }} <span>· Diagram ringkas</span></figcaption>
                                        <ol>
                                            @foreach ($page['diagram']['steps'] as $step)
                                                <li><span>{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>{{ $step }}</li>
                                            @endforeach
                                        </ol>
                                    </figure>
                                @endif

                                @if ($page['timeline'])
                                    <div class="chapter-timeline">
                                        <h3>Garis waktu</h3>
                                        <ol>
                                            @foreach ($page['timeline'] as $event)
                                                <li><span>{{ $event[0] }}</span><p>{{ $event[1] }}</p></li>
                                            @endforeach
                                        </ol>
                                    </div>
                                @endif

                                @if (isset($page['geography']))
                                    <aside class="chapter-geography">
                                        <div class="geography-heading"><span aria-hidden="true">⌖</span><div><span>Atlas konteks</span><h3>Di mana pengetahuan ini hidup?</h3></div></div>
                                        <p>{{ $page['geography']['summary'] }}</p>
                                        <ul>
                                            @foreach ($page['geography']['places'] as $place)
                                                <li>{{ $place }}</li>
                                            @endforeach
                                        </ul>
                                        <span class="geography-note">Indeks wilayah editorial · Bukan peta skala</span>
                                    </aside>
                                @endif

                                @if ($loop->last)
                                    <details class="chapter-references">
                                        <summary>Bacaan dan sumber rujukan <span aria-hidden="true">＋</span></summary>
                                        <ol>
                                            @foreach ($book['references'] as $reference)
                                                <li><a href="{{ $reference['url'] }}" target="_blank" rel="noopener noreferrer">{{ $reference['title'] }}</a><span>{{ $reference['publisher'] }}</span></li>
                                            @endforeach
                                        </ol>
                                    </details>
                                    <div class="chapter-travel-link">
                                        <span>LANJUTKAN DI LAPANGAN</span>
                                        <p>Jelajahi bentang dan warisan terkait:</p>
                                        <div>
                                            @foreach ($book['destinations'] as $destination)
                                                <a href="{{ route('home') }}#wisata-indonesia">{{ $destination }} <span aria-hidden="true">↗</span></a>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </div>
                            <span class="chapter-folio" aria-hidden="true">{{ str_pad((string) ($loop->iteration - 1), 2, '0', STR_PAD_LEFT) }}</span>
                        </section>
                    @endif
                @endforeach

                <div class="reader-status" data-reader-status role="status" aria-live="polite"></div>
                <footer class="reader-pagination">
                    <button class="pagination-button" type="button" data-page-previous disabled><span aria-hidden="true">←</span> Sebelumnya</button>
                    <span class="pagination-count" aria-live="polite"><strong data-page-current-footer>Sampul</strong> <i>/</i> {{ count($book['chapters']) }} bab</span>
                    <button class="pagination-button pagination-next" type="button" data-page-next>Bab berikutnya <span aria-hidden="true">→</span></button>
                </footer>
            </article>
        </div>

        <section class="reader-afterword" aria-label="Buku lainnya">
            <div><span>TERUS MEMBACA</span><h2>Jelajahi pengetahuan lainnya.</h2></div>
            <nav>
                @foreach ($books as $otherBook)
                    @if ($otherBook['slug'] !== $book['slug'])
                        <a href="{{ route('digital-books.show', $otherBook['slug']) }}">{{ $otherBook['title'] }} <span aria-hidden="true">↗</span></a>
                    @endif
                @endforeach
            </nav>
        </section>
    </main>
@endsection
