@extends('layouts.app')

@section('title', $region->name.' — Jelajah Nusantara | WARNA BUDAYA')
@section('meta_description', 'Kenali warisan budaya dan arsip terkurasi dari '.$region->name.'.')

@section('content')
    <section class="region-detail-hero">
        <div class="page-shell region-detail-inner">
            <a class="region-back-link" href="{{ route('regions.index') }}"><span aria-hidden="true">←</span> Kembali ke Jelajah Nusantara</a>
            <div class="region-detail-heading">
                <div>
                    <p class="eyebrow eyebrow-light"><span class="eyebrow-line"></span> Cerita dari Nusantara</p>
                    <h1>{{ $region->name }}</h1>
                    <p class="region-detail-description">{{ $region->description ?: 'Mengenal warisan, tradisi, dan pengetahuan yang tumbuh di '.$region->name.'.' }}</p>
                </div>
                <div class="region-detail-seal" aria-hidden="true">
                    <span>{{ $region->code ?: 'WB' }}</span>
                    <small>WILAYAH<br>BUDAYA</small>
                </div>
            </div>
            <div class="region-detail-stats">
                <span class="region-stat-number">{{ number_format($region->cultural_entries_count, 0, ',', '.') }}</span>
                <span class="region-stat-label">arsip budaya terkurasi</span>
                @if ($region->latitude !== null && $region->longitude !== null)
                    <span class="region-coordinates">{{ number_format((float) $region->latitude, 4, ',', '.') }}° · {{ number_format((float) $region->longitude, 4, ',', '.') }}°</span>
                @endif
            </div>
        </div>
    </section>

    <section class="region-entries-section section-space">
        <div class="page-shell">
            <div class="section-heading region-section-heading">
                <div>
                    <p class="eyebrow"><span class="eyebrow-line"></span> Dari {{ $region->name }}</p>
                    <h2 class="section-title">Warisan yang <em>terus hidup.</em></h2>
                </div>
                <a class="text-link section-heading-link" href="{{ route('regions.index') }}">Jelajahi wilayah lain <span aria-hidden="true">↗</span></a>
            </div>

            @if ($entries->isNotEmpty())
                <div class="region-entry-grid">
                    @foreach ($entries as $entry)
                        <article class="region-entry-card">
                            <div class="region-entry-index" aria-hidden="true">{{ str_pad((string) ($entries->firstItem() + $loop->index), 2, '0', STR_PAD_LEFT) }}</div>
                            <div class="region-entry-copy">
                                @if ($entry->categories->isNotEmpty())
                                    <span class="region-entry-category">{{ $entry->categories->first()->name }}</span>
                                @endif
                                <h3><a href="{{ route('cultural-entries.index', ['search' => $entry->title]) }}">{{ $entry->title }}</a></h3>
                                <p>{{ $entry->short_description ?: 'Sebuah warisan budaya dari '.$region->name.'.' }}</p>
                                <a class="region-entry-link" href="{{ route('cultural-entries.index', ['search' => $entry->title]) }}">Baca di arsip <span aria-hidden="true">↗</span></a>
                            </div>
                            <span class="region-entry-ornament" aria-hidden="true">✳</span>
                        </article>
                    @endforeach
                </div>
                <div class="pagination">
                    {{ $entries->links() }}
                </div>
            @else
                <div class="region-empty">
                    <span aria-hidden="true">✳</span>
                    <h3>Arsip sedang dikurasi.</h3>
                    <p>Belum ada arsip terverifikasi yang diterbitkan untuk {{ $region->name }}.</p>
                    <a class="text-link" href="{{ route('regions.index') }}">Jelajahi provinsi lain <span aria-hidden="true">↗</span></a>
                </div>
            @endif
        </div>
    </section>
@endsection
