@extends('layouts.app')

@section('title', $destination['name'].' — Wisata Nusantara | WARNA BUDAYA')
@section('meta_description', $destination['description'])
@section('body_class', 'destination-detail-page')

@section('content')
    <section class="region-detail-hero">
        <div class="page-shell region-detail-inner">
            <a class="region-back-link destination-back-link" href="{{ route('home') }}#wisata-indonesia">
                <span aria-hidden="true">←</span> Kembali ke daftar destinasi
            </a>
            <div class="region-detail-heading">
                <div>
                    <p class="eyebrow eyebrow-light"><span class="eyebrow-line"></span> {{ $destination['region'] }}</p>
                    <h1>{{ $destination['name'] }}</h1>
                    <p class="region-detail-description">{{ $destination['description'] }}</p>
                </div>
            </div>
        </div>
    </section>

    <section class="destination-detail-section section-space" aria-labelledby="destination-about-title">
        <div class="page-shell destination-detail-grid">
            <figure class="destination-detail-photo">
                <button
                    class="destination-photo-open"
                    type="button"
                    aria-label="Perbesar foto {{ $destination['name'] }}"
                    data-destination-photo-open
                    aria-haspopup="dialog"
                >
                    <img src="{{ asset($destination['image']) }}" alt="{{ $destination['alt'] }}" fetchpriority="high">
                    <span class="destination-photo-hint" aria-hidden="true">Perbesar foto <span>↗</span></span>
                </button>
                <figcaption>
                    <a href="{{ $destination['source'] }}" target="_blank" rel="noopener noreferrer">
                        {{ $destination['credit'] }} · Wikimedia Commons
                    </a>
                </figcaption>
            </figure>

            <div class="destination-detail-copy">
                <p class="eyebrow"><span class="eyebrow-line"></span> Lokasi · {{ $destination['region'] }}</p>
                <h2 class="section-title" id="destination-about-title">{{ $destination['name'] }}</h2>
                <p>{{ $destination['description'] }}</p>
                <a class="destination-source-link" href="{{ $destination['source'] }}" target="_blank" rel="noopener noreferrer">
                    Lihat sumber foto <span aria-hidden="true">↗</span>
                </a>
            </div>
        </div>
    </section>

    <dialog class="destination-photo-dialog" aria-label="Foto {{ $destination['name'] }}" data-destination-photo-dialog>
        <button class="destination-photo-close" type="button" aria-label="Tutup foto" data-destination-photo-close>
            <span aria-hidden="true">×</span>
        </button>
        <img src="{{ asset($destination['image']) }}" alt="{{ $destination['alt'] }}">
        <p>{{ $destination['name'] }} · {{ $destination['region'] }}</p>
    </dialog>
@endsection
