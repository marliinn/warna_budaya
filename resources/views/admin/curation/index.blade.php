@extends('admin.layouts.app')

@section('title', 'Dashboard Kurasi | WARNA BUDAYA')

@section('content')
    <section class="admin-page-heading" id="dashboard-overview">
        <div>
            <p class="admin-eyebrow"><span></span> Pusat pengelolaan arsip</p>
            <h1>Dashboard <em>Kurasi.</em></h1>
            <p>Periksa informasi, verifikasi sumber, dan kelola publikasi budaya satu per satu.</p>
        </div>
        <img class="admin-heading-stamp" src="{{ asset('images/brand-emblem.svg') }}" alt="">
    </section>

    <section class="admin-stat-grid" aria-label="Ringkasan kurasi">
        <article class="admin-stat-card admin-stat-featured">
            <span class="admin-stat-label">Seluruh entri</span>
            <strong>{{ number_format($stats['total'], 0, ',', '.') }}</strong>
            <small>Termasuk data draft dan pending</small>
        </article>
        <article class="admin-stat-card">
            <span class="admin-stat-label">Draft publikasi</span>
            <strong>{{ number_format($stats['draft'], 0, ',', '.') }}</strong>
            <small>Status publikasi draft</small>
        </article>
        <article class="admin-stat-card">
            <span class="admin-stat-label">Menunggu verifikasi</span>
            <strong>{{ number_format($stats['pending'], 0, ',', '.') }}</strong>
            <small>Status verifikasi pending</small>
        </article>
        <article class="admin-stat-card">
            <span class="admin-stat-label">Terverifikasi</span>
            <strong>{{ number_format($stats['verified'], 0, ',', '.') }}</strong>
            <small>Belum tentu sudah dipublikasikan</small>
        </article>
        <article class="admin-stat-card">
            <span class="admin-stat-label">Sudah dipublikasikan</span>
            <strong>{{ number_format($stats['published'], 0, ',', '.') }}</strong>
            <small>Entri dengan status published</small>
        </article>
        <article class="admin-stat-card admin-stat-media">
            <span class="admin-stat-label">Izin media pending</span>
            <strong>{{ number_format($stats['media_pending'], 0, ',', '.') }}</strong>
            <small>{{ number_format($stats['media_granted'], 0, ',', '.') }} media mendapat izin</small>
        </article>
        @if ($stats['deleted'] > 0)
            <article class="admin-stat-card admin-stat-deleted">
                <span class="admin-stat-label">Soft deleted</span>
                <strong>{{ number_format($stats['deleted'], 0, ',', '.') }}</strong>
                <small>Tetap tersimpan dan tidak tampil ke publik</small>
            </article>
        @endif
    </section>

    <section class="admin-workspace admin-pending-media-workspace" aria-labelledby="pending-media-title">
        <div class="admin-section-heading">
            <div>
                <p class="admin-eyebrow"><span></span> Tinjau izin dan atribusi</p>
                <h2 id="pending-media-title">Media menunggu persetujuan</h2>
                <p>Periksa pratinjau, pencipta, lisensi, dan halaman sumber sebelum menyetujui. Media hanya tampil kepada publik bila entrinya juga sudah diterbitkan.</p>
            </div>
            <span class="admin-total-label">{{ number_format($pendingMedia->count(), 0, ',', '.') }} pending</span>
        </div>

        @if (session()->has('media_bulk_skipped') && count(session('media_bulk_skipped')) > 0)
            <div class="admin-media-bulk-skipped" role="status">
                <strong>Media yang dilewati:</strong>
                <ul>
                    @foreach (session('media_bulk_skipped') as $skippedMedia)
                        <li><strong>{{ $skippedMedia['title'] }}</strong> — {{ $skippedMedia['reason'] }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @can('manage-media')
            @if ($bulkGrantableMediaCount > 0)
                <form class="admin-media-bulk-action" action="{{ route('admin.curation.media.permission.bulk') }}" method="POST">
                    @csrf
                    <p><strong>{{ number_format($bulkGrantableMediaCount, 0, ',', '.') }} media memenuhi syarat minimum</strong> (file tersedia, sumber URL, pencipta, caption, alt text, dan lisensi terbuka yang dikenali).</p>
                    <button class="admin-button admin-button-approve" type="submit">Setujui semua {{ number_format($bulkGrantableMediaCount, 0, ',', '.') }} media yang memenuhi syarat</button>
                </form>
            @else
                <p class="admin-media-bulk-note">Belum ada media pending yang memenuhi syarat untuk persetujuan massal.</p>
            @endif
        @endcan

        @if ($pendingMedia->isNotEmpty())
            <div class="admin-pending-media-grid">
                @foreach ($pendingMedia as $media)
                    <article class="admin-media-card">
                        @if ($media->preview_url && $media->media_type === 'image')
                            <a class="admin-media-preview" href="{{ $media->preview_url }}" target="_blank" rel="noopener noreferrer">
                                <img src="{{ $media->preview_url }}" alt="{{ $media->alt_text ?: $media->caption ?: 'Pratinjau media' }}" loading="lazy">
                            </a>
                        @else
                            <div class="admin-media-placeholder">
                                <span>Pratinjau tidak tersedia</span>
                            </div>
                        @endif
                        <div class="admin-media-info">
                            <p class="admin-pending-media-entry">
                                @if ($media->culturalEntry && ! $media->culturalEntry->trashed())
                                    <a href="{{ route('admin.curation.entries.show', $media->cultural_entry_id) }}">{{ $media->culturalEntry->title }}</a>
                                @else
                                    <span>{{ $media->culturalEntry?->title ?? 'Entri tidak tersedia' }}</span>
                                @endif
                                <span class="admin-status status-pending">pending</span>
                            </p>
                            <h3>{{ $media->caption ?: 'Media tanpa caption' }}</h3>
                            @if ($media->alt_text)
                                <p class="admin-media-alt">{{ $media->alt_text }}</p>
                            @endif
                            <dl class="admin-media-facts">
                                <div><dt>Kreator</dt><dd>{{ $media->creator ?: 'Belum dicantumkan' }}</dd></div>
                                <div><dt>Pemegang hak</dt><dd>{{ $media->copyright_holder ?: 'Tidak dicantumkan sumber' }}</dd></div>
                                <div>
                                    <dt>Lisensi</dt>
                                    <dd>
                                        @if ($media->license_url)
                                            <a href="{{ $media->license_url }}" target="_blank" rel="noopener noreferrer">{{ $media->license }}</a>
                                        @else
                                            {{ $media->license ?: 'Belum dicantumkan' }}
                                        @endif
                                    </dd>
                                </div>
                                <div>
                                    <dt>Halaman sumber foto</dt>
                                    <dd>
                                        @if ($media->source_url)
                                            <a href="{{ $media->source_url }}" target="_blank" rel="noopener noreferrer">Buka sumber foto ↗</a>
                                        @else
                                            Belum tersedia
                                        @endif
                                    </dd>
                                </div>
                            </dl>
                            @if ($media->culturalEntry && $media->culturalEntry->sources->isNotEmpty())
                                <div class="admin-pending-media-sources">
                                    <strong>Sumber referensi entri</strong>
                                    <ul>
                                        @foreach ($media->culturalEntry->sources as $source)
                                            <li>
                                                @if ($source->url && in_array(strtolower((string) parse_url($source->url, PHP_URL_SCHEME)), ['http', 'https'], true))
                                                    <a href="{{ $source->url }}" target="_blank" rel="noopener noreferrer">{{ $source->title }} ↗</a>
                                                @else
                                                    {{ $source->title }}
                                                @endif
                                                @if ($source->author) — {{ $source->author }}@endif
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                            @if ($media->bulk_grant_blocker)
                                <p class="admin-media-bulk-blocker">Tidak termasuk persetujuan massal: {{ $media->bulk_grant_blocker }}</p>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        @else
            <p class="admin-empty-note">Tidak ada media yang menunggu persetujuan izin.</p>
        @endif
    </section>

    <section class="admin-workspace" id="data-budaya" aria-labelledby="entry-list-title">
        <div class="admin-section-heading">
            <div>
                <p class="admin-eyebrow"><span></span> Daftar kerja</p>
                <h2 id="entry-list-title">Entri budaya</h2>
            </div>
            <span class="admin-total-label">{{ number_format($entries->total(), 0, ',', '.') }} hasil</span>
        </div>

        <form class="admin-filters" action="{{ route('admin.home') }}" method="GET">
            <label class="admin-filter-search">
                <span class="admin-sr-only">Cari nama budaya</span>
                <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Cari nama budaya...">
            </label>
            <label>
                <span class="admin-sr-only">Filter wilayah</span>
                <select name="region_id">
                    <option value="">Semua wilayah</option>
                    @foreach ($regions as $region)
                        <option value="{{ $region->id }}" @selected((string) ($filters['region_id'] ?? '') === (string) $region->id)>{{ $region->name }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span class="admin-sr-only">Filter kategori</span>
                <select name="category_id">
                    <option value="">Semua kategori</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) ($filters['category_id'] ?? '') === (string) $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span class="admin-sr-only">Filter verifikasi</span>
                <select name="verification_status">
                    <option value="">Semua verifikasi</option>
                    <option value="pending" @selected(($filters['verification_status'] ?? '') === 'pending')>Pending</option>
                    <option value="verified" @selected(($filters['verification_status'] ?? '') === 'verified')>Verified</option>
                </select>
            </label>
            <label>
                <span class="admin-sr-only">Filter publikasi</span>
                <select name="publication_status">
                    <option value="">Semua publikasi</option>
                    <option value="draft" @selected(($filters['publication_status'] ?? '') === 'draft')>Draft</option>
                    <option value="published" @selected(($filters['publication_status'] ?? '') === 'published')>Published</option>
                </select>
            </label>
            <button type="submit">Terapkan</button>
            @if ($filters !== [])
                <a class="admin-filter-reset" href="{{ route('admin.home') }}">Reset</a>
            @endif
        </form>

        <div class="admin-table-wrap">
            <table class="admin-entry-table">
                <thead>
                    <tr>
                        <th scope="col">Nama budaya</th>
                        <th scope="col">Wilayah asal</th>
                        <th scope="col">Kategori</th>
                        <th scope="col">Verifikasi</th>
                        <th scope="col">Publikasi</th>
                        <th scope="col">Media</th>
                        <th scope="col">Dibuat</th>
                        <th scope="col"><span class="admin-sr-only">Tindakan</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($entries as $entry)
                        <tr @class(['is-deleted' => $entry->trashed()])>
                            <td data-label="Nama budaya">
                                <a class="admin-entry-name" href="{{ route('admin.curation.entries.show', $entry->id) }}">{{ $entry->title }}</a>
                                @if ($entry->trashed())
                                    <span class="admin-deleted-label">Soft deleted</span>
                                @endif
                            </td>
                            <td data-label="Wilayah">{{ $entry->region?->name ?? 'Belum ditentukan' }}</td>
                            <td data-label="Kategori">
                                @forelse ($entry->categories as $category)
                                    <span class="admin-category-tag">{{ $category->name }}</span>
                                @empty
                                    <span class="admin-muted">Belum dikategorikan</span>
                                @endforelse
                            </td>
                            <td data-label="Verifikasi">
                                <span @class(['admin-status', 'status-verified' => $entry->verification_status === 'verified', 'status-pending' => $entry->verification_status === 'pending', 'status-other' => ! in_array($entry->verification_status, ['verified', 'pending'], true)])>
                                    {{ $entry->verification_status }}
                                </span>
                            </td>
                            <td data-label="Publikasi">
                                <span @class(['admin-status', 'status-published' => $entry->publication_status === 'published', 'status-draft' => $entry->publication_status === 'draft', 'status-other' => ! in_array($entry->publication_status, ['published', 'draft'], true)])>
                                    {{ $entry->publication_status }}
                                </span>
                            </td>
                            <td data-label="Media">{{ number_format($entry->media_count, 0, ',', '.') }}</td>
                            <td data-label="Dibuat">{{ $entry->created_at?->format('d M Y') ?? '—' }}</td>
                            <td data-label="Tindakan"><a class="admin-row-action" href="{{ route('admin.curation.entries.show', $entry->id) }}">Periksa <span aria-hidden="true">↗</span></a></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="admin-table-empty">Tidak ada entri yang cocok dengan filter ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($entries->hasPages())
            <nav class="admin-pagination" aria-label="Navigasi halaman entri">
                {{ $entries->links() }}
            </nav>
        @endif
    </section>
@endsection
