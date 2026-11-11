@extends('layouts.app')

@section('title', $entry->title.' | Arsip Budaya WARNA BUDAYA')
@section('meta_description', $entry->short_description ?: 'Mengenal '.$entry->title.' melalui Arsip Budaya WARNA BUDAYA.')
@section('body_class', 'cultural-detail-page')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/cultural-detail.css') }}">
@endpush

@section('content')
    @php
        $imageMedia = $entry->media->filter(
            fn ($media) => $media->media_type === 'image' && ($media->external_url || $media->file_path)
        )->values();
        $cover = $imageMedia->first(fn ($media) => $media->is_cover) ?: $imageMedia->first();
        $editorialIllustration = match ($entry->slug) {
            'rumah-honai' => asset('images/cultural/editorial/rumah-honai.svg'),
            default => null,
        };
        $galleryMedia = $cover
            ? $imageMedia->reject(fn ($media) => $media->is($cover))->values()
            : $imageMedia;
        $sections = [
            'Sejarah' => $entry->history,
            'Makna dan fungsi' => $entry->meaning_and_function,
            'Teknik dan proses' => $entry->techniques,
            'Bahan' => $entry->materials,
            'Konteks komunitas' => $entry->community_context,
        ];
    @endphp

    <section class="cultural-detail-hero" aria-labelledby="cultural-detail-title">
        <div class="page-shell cultural-detail-hero-inner">
            <a class="cultural-detail-back" href="{{ route('cultural-entries.index') }}">
                <span aria-hidden="true">←</span> Kembali ke Arsip Budaya
            </a>
            <div class="cultural-detail-title-block">
                <p class="eyebrow eyebrow-light"><span class="eyebrow-line"></span> Arsip budaya terverifikasi</p>
                @if ($entry->region)
                    <p class="cultural-detail-region">{{ $entry->region->name }}</p>
                @endif
                <h1 id="cultural-detail-title">{{ $entry->title }}</h1>
                @if ($entry->local_name)
                    <p class="cultural-detail-local-name">{{ $entry->local_name }}</p>
                @endif
                @if ($entry->short_description)
                    <p class="cultural-detail-intro">{{ $entry->short_description }}</p>
                @endif
                @if ($entry->categories->isNotEmpty())
                    <ul class="cultural-detail-categories" aria-label="Kategori budaya">
                        @foreach ($entry->categories as $category)
                            <li>{{ $category->name }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
            <img class="cultural-detail-seal" src="{{ asset('images/brand-emblem.svg') }}" alt="">
        </div>
    </section>

    <div class="cultural-detail-main">
        <div class="page-shell cultural-detail-layout">
            <article class="cultural-detail-article">
                @if ($cover)
                    <figure class="cultural-detail-cover">
                        <img
                            src="{{ $cover->publicUrl() }}"
                            alt="{{ $cover->alt_text ?: $entry->title }}"
                            fetchpriority="high"
                        >
                        @if ($cover->caption || $cover->creator || $cover->copyright_holder || $cover->license)
                            <figcaption>
                                @if ($cover->caption)
                                    <span>{{ $cover->caption }}</span>
                                @endif
                                <span class="cultural-detail-cover-credit">
                                    @if ($cover->creator) {{ $cover->creator }}@endif
                                    @if ($cover->copyright_holder) · {{ $cover->copyright_holder }}@endif
                                    @if ($cover->license)
                                        ·
                                        @if ($cover->licenseUrl())
                                            <a href="{{ $cover->licenseUrl() }}" target="_blank" rel="noopener noreferrer">{{ $cover->license }}</a>
                                        @else
                                            {{ $cover->license }}
                                        @endif
                                    @endif
                                    @if ($cover->external_url)
                                        · <a href="{{ $cover->external_url }}" target="_blank" rel="noopener noreferrer">Sumber foto</a>
                                    @endif
                                </span>
                            </figcaption>
                        @endif
                    </figure>
                @else
                    @if ($editorialIllustration)
                        <figure class="cultural-detail-cover cultural-detail-cover-fallback has-editorial-illustration">
                            <img src="{{ $editorialIllustration }}" alt="Ilustrasi editorial {{ $entry->title }}; bukan foto dokumentasi autentik">
                            <figcaption>Ilustrasi editorial WARNA BUDAYA · bukan foto dokumentasi autentik</figcaption>
                        </figure>
                    @else
                        <div class="cultural-detail-cover cultural-detail-cover-fallback" aria-label="Tidak ada foto berizin untuk arsip ini">
                            <img class="cultural-detail-fallback-seal" src="{{ asset('images/brand-emblem.svg') }}" alt="">
                            <p>Dokumentasi visual belum tersedia</p>
                            <span>Materi hanya ditampilkan setelah izin penggunaannya dikonfirmasi.</span>
                        </div>
                    @endif
                @endif

                @if ($entry->description)
                    <section class="cultural-detail-section cultural-detail-lead">
                        <p class="cultural-detail-eyebrow">Mengenal warisan</p>
                        <h2>Di balik sebuah tradisi.</h2>
                        <div class="cultural-detail-prose">{!! nl2br(e($entry->description)) !!}</div>
                    </section>
                @endif

                @foreach ($sections as $heading => $content)
                    @if ($content)
                        <section class="cultural-detail-section">
                            <p class="cultural-detail-eyebrow">Catatan budaya</p>
                            <h2>{{ $heading }}</h2>
                            <div class="cultural-detail-prose">{!! nl2br(e($content)) !!}</div>
                        </section>
                    @endif
                @endforeach

                @if ($entry->keywords)
                    <section class="cultural-detail-section cultural-detail-keywords">
                        <p class="cultural-detail-eyebrow">Kata kunci</p>
                        <ul>
                            @foreach ((array) $entry->keywords as $keyword)
                                <li>{{ $keyword }}</li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                @if ($galleryMedia->isNotEmpty())
                    <section class="cultural-detail-section cultural-detail-gallery" aria-labelledby="cultural-detail-gallery-title">
                        <p class="cultural-detail-eyebrow">Dokumentasi berizin</p>
                        <h2 id="cultural-detail-gallery-title">Ruang visual</h2>
                        <div class="cultural-detail-gallery-grid">
                            @foreach ($galleryMedia as $media)
                                @php
                                    $mediaUrl = $media->publicUrl();
                                @endphp
                                <figure class="cultural-detail-gallery-item">
                                    <img src="{{ $mediaUrl }}" alt="{{ $media->alt_text ?: $entry->title }}" loading="lazy">
                                    @if ($media->caption || $media->creator || $media->copyright_holder || $media->license)
                                        <figcaption>
                                            @if ($media->caption)
                                                <span>{{ $media->caption }}</span>
                                            @endif
                                            <span>
                                                @if ($media->creator) {{ $media->creator }}@endif
                                                @if ($media->copyright_holder) · {{ $media->copyright_holder }}@endif
                                                @if ($media->license)
                                                    ·
                                                    @if ($media->licenseUrl())
                                                        <a href="{{ $media->licenseUrl() }}" target="_blank" rel="noopener noreferrer">{{ $media->license }}</a>
                                                    @else
                                                        {{ $media->license }}
                                                    @endif
                                                @endif
                                                @if ($media->external_url)
                                                    · <a href="{{ $media->external_url }}" target="_blank" rel="noopener noreferrer">Sumber foto</a>
                                                @endif
                                            </span>
                                        </figcaption>
                                    @endif
                                </figure>
                            @endforeach
                        </div>
                    </section>
                @endif

                <section class="cultural-detail-section cultural-detail-sources" aria-labelledby="cultural-detail-sources-title">
                    <p class="cultural-detail-eyebrow">Jejak pengetahuan</p>
                    <h2 id="cultural-detail-sources-title">Sumber dan rujukan</h2>
                    @forelse ($entry->sources as $source)
                        @php
                            $sourceScheme = strtolower((string) parse_url($source->url ?? '', PHP_URL_SCHEME));
                            $safeSourceUrl = in_array($sourceScheme, ['http', 'https'], true);
                        @endphp
                        <article class="cultural-detail-source">
                            <span class="cultural-detail-source-type">{{ $source->source_type }}</span>
                            <h3>{{ $source->title }}</h3>
                            <p>{{ collect([$source->author, $source->publisher, $source->publication_year])->filter()->join(' · ') }}</p>
                            @if ($source->identifier)
                                <p>Identitas: {{ $source->identifier }}</p>
                            @endif
                            @if ($source->accessed_at)
                                <p>Diakses {{ $source->accessed_at->format('d M Y') }}</p>
                            @endif
                            @if ($source->notes)
                                <p>{{ $source->notes }}</p>
                            @endif
                            @if ($safeSourceUrl)
                                <a href="{{ $source->url }}" target="_blank" rel="noopener noreferrer">Buka sumber <span aria-hidden="true">↗</span></a>
                            @elseif ($source->url)
                                <p class="cultural-detail-source-url">{{ $source->url }}</p>
                            @endif
                        </article>
                    @empty
                        <p class="cultural-detail-source-empty">Belum ada sumber yang dicantumkan pada arsip ini.</p>
                    @endforelse
                </section>
            </article>

            <aside class="cultural-detail-aside" aria-label="Informasi arsip">
                <div class="cultural-detail-facts">
                    <p class="cultural-detail-eyebrow">Sekilas arsip</p>
                    <h2>Identitas budaya</h2>
                    <dl>
                        <div>
                            <dt>Wilayah asal</dt>
                            <dd>{{ $entry->region?->name ?? 'Belum dicatat' }}</dd>
                        </div>
                        <div>
                            <dt>Kategori</dt>
                            <dd>{{ $entry->categories->pluck('name')->join(', ') ?: 'Belum dicatat' }}</dd>
                        </div>
                        @if ($entry->local_name)
                            <div>
                                <dt>Nama lokal</dt>
                                <dd>{{ $entry->local_name }}</dd>
                            </div>
                        @endif
                        <div>
                            <dt>Rujukan</dt>
                            <dd>{{ $entry->sources->count() }} sumber tercatat</dd>
                        </div>
                    </dl>
                    <a href="{{ route('cultural-entries.index') }}">Jelajahi seluruh arsip <span aria-hidden="true">↗</span></a>
                </div>
            </aside>
        </div>
    </div>

    @if ($relatedEntries->isNotEmpty())
        <section class="cultural-detail-related" aria-labelledby="cultural-detail-related-title">
            <div class="page-shell">
                <div class="cultural-detail-related-heading">
                    <div>
                        <p class="cultural-detail-eyebrow">Masih dalam jalinan cerita</p>
                        <h2 id="cultural-detail-related-title">Jelajahi kisah terkait.</h2>
                    </div>
                    <a href="{{ route('cultural-entries.index') }}">Kembali ke arsip <span aria-hidden="true">↗</span></a>
                </div>
                <div class="cultural-detail-related-grid">
                    @foreach ($relatedEntries as $relatedEntry)
                        @php
                            $relatedCover = $relatedEntry->media->first(
                                fn ($media) => $media->media_type === 'image' && ($media->external_url || $media->file_path)
                            );
                        @endphp
                        <article class="cultural-detail-related-card">
                            <a class="cultural-detail-related-image" href="{{ route('cultural-entries.show', $relatedEntry->slug) }}">
                                @if ($relatedCover)
                                    <img
                                        src="{{ $relatedCover->publicUrl() }}"
                                        alt="{{ $relatedCover->alt_text ?: $relatedEntry->title }}"
                                        loading="lazy"
                                    >
                                @else
                                    <img src="{{ asset('images/brand-emblem.svg') }}" alt="">
                                @endif
                            </a>
                            <div>
                                <p>{{ $relatedEntry->region?->name ?? 'Nusantara' }}</p>
                                <h3><a href="{{ route('cultural-entries.show', $relatedEntry->slug) }}">{{ $relatedEntry->title }}</a></h3>
                                @if ($relatedEntry->short_description)
                                    <span>{{ $relatedEntry->short_description }}</span>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>
    @endif
@endsection
