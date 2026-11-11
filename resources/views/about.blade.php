@extends('layouts.app')

@section('title', 'Tentang Kami — WARNA BUDAYA')
@section('meta_description', 'Kenali WARNA BUDAYA, ruang belajar digital untuk merayakan dan melestarikan kekayaan budaya Nusantara.')
@section('body_class', 'about-page')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/about.css') }}">
@endpush

@section('content')
    <section class="about-hero" aria-labelledby="about-title">
        <div class="page-shell about-hero-grid">
            <div class="about-hero-copy">
                <p class="about-eyebrow"><span aria-hidden="true"></span> Tentang WARNA BUDAYA</p>
                <h1 id="about-title">Merawat warisan,<br>menyalakan <em>masa depan.</em></h1>
                <p class="about-hero-lead">Ruang belajar digital untuk mengenal cerita, nilai, dan pengetahuan yang hidup di setiap sudut Nusantara.</p>
                <a class="about-button" href="#cerita-kami">Kenali cerita kami <span aria-hidden="true">↓</span></a>
            </div>
            <div class="about-hero-art" aria-label="Ragam budaya Nusantara">
                <figure class="about-hero-photo about-hero-photo-main">
                    <img src="{{ asset('images/hero/wayang-kulit.jpg') }}" alt="Pertunjukan wayang kulit, salah satu seni tradisi Nusantara">
                    <figcaption>Warisan yang terus bercerita</figcaption>
                </figure>
                <figure class="about-hero-photo about-hero-photo-detail">
                    <img src="{{ asset('images/culture/batik-tulis.jpg') }}" alt="Proses membatik sebagai salah satu tradisi kriya Indonesia" loading="lazy" decoding="async">
                </figure>
                <span class="about-art-seal" aria-hidden="true">BHINNEKA<br>TUNGGAL IKA</span>
            </div>
        </div>
        <span class="about-hero-pattern" aria-hidden="true"></span>
    </section>

    <section class="about-story about-section" id="cerita-kami" aria-labelledby="story-title">
        <div class="page-shell about-story-grid">
            <div class="about-story-heading">
                <p class="about-eyebrow"><span aria-hidden="true"></span> Cerita kami</p>
                <h2 id="story-title">Budaya bukan hanya tentang <em>masa lalu.</em></h2>
            </div>
            <div class="about-story-copy">
                <p class="about-story-intro">Budaya Indonesia adalah identitas yang kita hidupi dan kekayaan ilmu yang terus tumbuh.</p>
                <p>Di dalamnya tersimpan sejarah, filosofi, tradisi, dan kearifan yang membentuk cara kita melihat dunia. WARNA BUDAYA hadir sebagai jembatan agar pengetahuan itu lebih dekat, mudah dijelajahi, dan dapat dinikmati siapa saja—terutama generasi muda.</p>
                <p>Kami percaya, semakin banyak orang mengenal cerita di balik sebuah warisan, semakin kuat pula rasa ingin menjaga dan meneruskannya.</p>
                <div class="about-story-note">
                    <span class="about-note-mark" aria-hidden="true">“</span>
                    <p>Warisan budaya akan terus hidup ketika dipelajari, dibagikan, dan dirayakan bersama.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="about-purpose about-section" id="visi-misi" aria-labelledby="purpose-title">
        <div class="page-shell">
            <div class="about-section-heading">
                <p class="about-eyebrow"><span aria-hidden="true"></span> Arah langkah kami</p>
                <h2 id="purpose-title">Belajar hari ini,<br><em>menjaga esok hari.</em></h2>
            </div>
            <div class="about-purpose-grid">
                <article class="about-vision-card">
                    <span class="about-card-index">01 / VISI</span>
                    <span class="about-card-symbol" aria-hidden="true">✳</span>
                    <h3>Budaya yang dikenal, dihargai, dan diwariskan.</h3>
                    <p>Menjadi sumber pengetahuan digital tentang budaya Nusantara yang inspiratif, tepercaya, dan relevan bagi generasi masa kini.</p>
                    <span class="about-vision-ornament" aria-hidden="true"></span>
                </article>
                <div class="about-mission-card">
                    <span class="about-card-index">02 / MISI</span>
                    <h3>Membuka jalan untuk mengenal lebih dekat.</h3>
                    <ul class="about-mission-list">
                        <li><span>01</span><p>Menghadirkan pengetahuan tentang sejarah, filosofi, tradisi, dan keunikan budaya Indonesia.</p></li>
                        <li><span>02</span><p>Menyajikan bahan edukasi yang menarik, informatif, dan mudah dipahami.</p></li>
                        <li><span>03</span><p>Mengajak generasi muda untuk mengenal serta menghargai warisan budaya.</p></li>
                        <li><span>04</span><p>Membantu masyarakat menjelajahi ragam budaya dari seluruh penjuru Nusantara.</p></li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <section class="about-team about-section" aria-labelledby="team-title">
        <div class="page-shell">
            <div class="about-team-heading">
                <div class="about-section-heading">
                    <p class="about-eyebrow"><span aria-hidden="true"></span> Di balik WARNA BUDAYA</p>
                    <h2 id="team-title">Dua langkah, satu <em>semangat.</em></h2>
                </div>
                <p>Berkenalan dengan para pengembang yang percaya bahwa cerita budaya layak mendapat ruang di dunia digital.</p>
            </div>
            <div class="about-team-grid">
                <article class="about-person-card">
                    <div class="about-person-portrait portrait-cornelius" role="img" aria-label="Placeholder foto Cornelius Filemon Julianto">
                        <span class="about-portrait-initials" aria-hidden="true">CFJ</span>
                        <svg viewBox="0 0 120 120" aria-hidden="true"><circle cx="60" cy="43" r="20"></circle><path d="M22 112c2-25 16-39 38-39s36 14 38 39"></path></svg>
                        <span class="about-portrait-label">Foto profil</span>
                    </div>
                    <div class="about-person-copy">
                        <span class="about-person-role">Pengembang</span>
                        <h3>Cornelius Filemon Julianto</h3>
                        <p class="about-person-nickname">“Cornelius”</p>
                        <p class="about-person-origin"><svg viewBox="0 0 20 20" aria-hidden="true"><path d="M16 8.3c0 4.1-6 9.2-6 9.2S4 12.4 4 8.3a6 6 0 1 1 12 0Z"></path><circle cx="10" cy="8" r="2"></circle></svg>Surabaya, Jawa Timur</p>
                    </div>
                </article>
                <article class="about-person-card">
                    <div class="about-person-portrait portrait-icis" role="img" aria-label="Placeholder foto Istiqomah Tri Utami">
                        <span class="about-portrait-initials" aria-hidden="true">ITU</span>
                        <svg viewBox="0 0 120 120" aria-hidden="true"><circle cx="60" cy="43" r="20"></circle><path d="M22 112c2-25 16-39 38-39s36 14 38 39"></path></svg>
                        <span class="about-portrait-label">Foto profil</span>
                    </div>
                    <div class="about-person-copy">
                        <span class="about-person-role">Pengembang</span>
                        <h3>Istiqomah Tri Utami</h3>
                        <p class="about-person-nickname">“Icis”</p>
                        <p class="about-person-origin"><svg viewBox="0 0 20 20" aria-hidden="true"><path d="M16 8.3c0 4.1-6 9.2-6 9.2S4 12.4 4 8.3a6 6 0 1 1 12 0Z"></path><circle cx="10" cy="8" r="2"></circle></svg>Surabaya, Jawa Timur</p>
                    </div>
                </article>
            </div>
            <p class="about-team-note">Ilustrasi pada kartu profil adalah placeholder dan dapat diganti dengan foto masing-masing pengembang.</p>
        </div>
    </section>

    <section class="about-invitation" aria-labelledby="invitation-title">
        <div class="page-shell about-invitation-inner">
            <p class="about-eyebrow about-eyebrow-light"><span aria-hidden="true"></span> Mari menjelajah</p>
            <h2 id="invitation-title">Setiap cerita adalah pintu<br>menuju <em>Nusantara.</em></h2>
            <p>Mulai perjalananmu mengenal ragam warisan dan pengetahuan budaya Indonesia.</p>
            <a class="about-button about-button-light" href="{{ route('regions.index') }}">Jelajahi Nusantara <span aria-hidden="true">↗</span></a>
            <span class="about-invitation-pattern" aria-hidden="true"></span>
        </div>
    </section>
@endsection
