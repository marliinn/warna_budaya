@extends('admin.layouts.app')

@section('title', $entry->title.' — Kurasi Admin | WARNA BUDAYA')

@section('content')
    <div class="admin-detail-topline">
        <a class="admin-back-link" href="{{ route('admin.home', request()->query()) }}">← Kembali ke daftar kurasi</a>
        @if ($entry->trashed())
            <span class="admin-status status-deleted">Soft deleted · {{ $entry->deleted_at->format('d M Y H:i') }}</span>
        @endif
    </div>

    <section class="admin-detail-heading">
        <div>
            <p class="admin-eyebrow"><span></span> Pemeriksaan arsip budaya</p>
            <h1>{{ $entry->title }}</h1>
            @if ($entry->local_name)
                <p class="admin-local-name">{{ $entry->local_name }}</p>
            @endif
            <div class="admin-detail-badges">
                <span @class(['admin-status', 'status-verified' => $entry->verification_status === 'verified', 'status-pending' => $entry->verification_status === 'pending', 'status-other' => ! in_array($entry->verification_status, ['verified', 'pending'], true)])>Verifikasi: {{ $entry->verification_status }}</span>
                <span @class(['admin-status', 'status-published' => $entry->publication_status === 'published', 'status-draft' => $entry->publication_status === 'draft', 'status-other' => ! in_array($entry->publication_status, ['published', 'draft'], true)])>Publikasi: {{ $entry->publication_status }}</span>
            </div>
        </div>
        <div class="admin-entry-dates">
            <span>Dibuat {{ $entry->created_at?->format('d M Y H:i') ?? '—' }}</span>
            <span>Diperbarui {{ $entry->updated_at?->format('d M Y H:i') ?? '—' }}</span>
            @if ($entry->published_at)
                <span>Terbit {{ $entry->published_at->format('d M Y H:i') }}</span>
            @endif
        </div>
    </section>

    @if (session('publication_blockers'))
        <aside class="admin-blocker-panel" role="status">
            <strong>Belum memenuhi syarat publikasi</strong>
            <ul>
                @foreach (session('publication_blockers') as $blocker)
                    <li>{{ $blocker }}</li>
                @endforeach
            </ul>
        </aside>
    @endif

    <div class="admin-detail-grid">
        <div class="admin-detail-main">
            <section class="admin-panel">
                <div class="admin-panel-heading">
                    <div>
                        <p class="admin-eyebrow"><span></span> Informasi budaya</p>
                        <h2>Catatan dan konteks</h2>
                    </div>
                </div>
                <dl class="admin-entry-facts">
                    <div><dt>Wilayah asal</dt><dd>{{ $entry->region?->name ?? 'Belum ditentukan' }} <small>({{ $entry->origin_region_id ?? '—' }})</small></dd></div>
                    <div><dt>Kategori</dt><dd>{{ $entry->categories->pluck('name')->join(', ') ?: 'Belum dikategorikan' }}</dd></div>
                    <div><dt>Kata kunci</dt><dd>{{ is_array($entry->keywords) ? implode(', ', $entry->keywords) : ($entry->keywords ?: '—') }}</dd></div>
                    <div><dt>Slug arsip</dt><dd>{{ $entry->slug }}</dd></div>
                    <div><dt>Dibuat oleh</dt><dd>{{ $entry->createdBy?->name ?? 'Tidak tercatat' }}</dd></div>
                    <div><dt>Diperbarui oleh</dt><dd>{{ $entry->updatedBy?->name ?? 'Belum diperbarui' }}</dd></div>
                </dl>
                @foreach ([
                    'Deskripsi singkat' => $entry->short_description,
                    'Deskripsi lengkap' => $entry->description,
                    'Sejarah' => $entry->history,
                    'Makna dan fungsi' => $entry->meaning_and_function,
                    'Teknik' => $entry->techniques,
                    'Bahan' => $entry->materials,
                    'Konteks komunitas' => $entry->community_context,
                ] as $label => $value)
                    <div class="admin-prose-block">
                        <h3>{{ $label }}</h3>
                        <p>{{ $value ?: 'Belum tersedia.' }}</p>
                    </div>
                @endforeach
            </section>

            <section class="admin-panel">
                <div class="admin-panel-heading">
                    <div>
                        <p class="admin-eyebrow"><span></span> Bukti dan rujukan</p>
                        <h2>Sumber referensi <span>({{ $entry->sources->count() }})</span></h2>
                    </div>
                </div>
                @forelse ($entry->sources as $source)
                    <article class="admin-source-card">
                        <div>
                            <span class="admin-source-type">{{ $source->source_type }}</span>
                            <h3>{{ $source->title }}</h3>
                            <p>{{ collect([$source->author, $source->publisher, $source->publication_year])->filter()->join(' · ') ?: 'Detail bibliografi belum lengkap' }}</p>
                            @if ($source->pivot->reference_note)
                                <p class="admin-source-note">{{ $source->pivot->reference_note }}</p>
                            @endif
                            @if ($source->identifier)
                                <p>Identitas: {{ $source->identifier }}</p>
                            @endif
                            @if ($source->accessed_at)
                                <p>Diakses: {{ $source->accessed_at->format('d M Y') }}</p>
                            @endif
                            @if ($source->notes)
                                <p>{{ $source->notes }}</p>
                            @endif
                            @if ($source->url)
                                <p>URL: {{ $source->url }}</p>
                            @endif
                        </div>
                        @if ($source->safe_url)
                            <a href="{{ $source->safe_url }}" target="_blank" rel="noopener noreferrer">Buka sumber ↗</a>
                        @endif
                    </article>
                @empty
                    <p class="admin-empty-note">Belum ada sumber yang ditautkan pada entri ini.</p>
                @endforelse
            </section>

            <section class="admin-panel">
                <div class="admin-panel-heading">
                    <div>
                        <p class="admin-eyebrow"><span></span> Rekam jejak</p>
                        <h2>Riwayat kurasi <span>({{ $entry->verificationLogs->count() }})</span></h2>
                    </div>
                </div>
                @forelse ($entry->verificationLogs->sortByDesc('reviewed_at') as $log)
                    <article class="admin-history-item">
                        <span class="admin-history-dot" aria-hidden="true"></span>
                        <div>
                            <div class="admin-history-title">
                                <strong>{{ $log->decision }}</strong>
                                <time>{{ $log->reviewed_at?->format('d M Y H:i') ?? $log->created_at?->format('d M Y H:i') ?? 'Waktu tidak tersedia' }}</time>
                            </div>
                            <p>{{ $log->notes ?: 'Tidak ada catatan.' }}</p>
                            <small>{{ $log->reviewer?->name ?? 'Akun pengelola tidak tersedia' }}</small>
                        </div>
                    </article>
                @empty
                    <p class="admin-empty-note">Belum ada riwayat tindakan kurasi.</p>
                @endforelse
            </section>
        </div>

        <aside class="admin-detail-sidebar">
            <section class="admin-panel admin-media-panel">
                <div class="admin-panel-heading">
                    <div>
                        <p class="admin-eyebrow"><span></span> Pemeriksaan hak</p>
                        <h2>Media <span>({{ $entry->media->count() }})</span></h2>
                    </div>
                </div>
                <p class="admin-panel-intro">Hanya media berizin <strong>granted</strong> yang dapat ditampilkan kepada publik.</p>

                @forelse ($entry->media as $media)
                    <article class="admin-media-card">
                        @if ($media->preview_url && $media->media_type === 'image')
                            <a class="admin-media-preview" href="{{ $media->preview_url }}" target="_blank" rel="noopener noreferrer">
                                <img src="{{ $media->preview_url }}" alt="{{ $media->alt_text ?: $media->caption ?: 'Pratinjau media' }}" loading="lazy">
                            </a>
                        @else
                            <div class="admin-media-placeholder">
                                <span>{{ strtoupper($media->media_type) }}</span>
                                @if ($media->preview_url)
                                    <a href="{{ $media->preview_url }}" target="_blank" rel="noopener noreferrer">Buka media ↗</a>
                                @endif
                            </div>
                        @endif
                        <div class="admin-media-info">
                            <div class="admin-media-title">
                                <strong>{{ $media->caption ?: $media->file_path ?: $media->external_url ?: 'Media tanpa nama' }}</strong>
                                <span @class(['admin-status', 'status-granted' => $media->permission_status === 'granted', 'status-pending' => $media->permission_status === 'pending', 'status-other' => ! in_array($media->permission_status, ['granted', 'pending'], true)])>{{ $media->permission_status }}</span>
                            </div>
                            <dl class="admin-media-facts">
                                <div><dt>Tipe</dt><dd>{{ $media->media_type }}</dd></div>
                                <div><dt>Kreator</dt><dd>{{ $media->creator ?: '—' }}</dd></div>
                                <div><dt>Pemegang hak</dt><dd>{{ $media->copyright_holder ?: '—' }}</dd></div>
                                <div><dt>Lisensi</dt><dd>{{ $media->license ?: 'Belum dicantumkan' }}</dd></div>
                                <div><dt>Sampul</dt><dd>{{ $media->is_cover ? 'Ya' : 'Tidak' }}</dd></div>
                            </dl>
                            @if ($media->permission_notes)
                                <p class="admin-media-notes">{{ $media->permission_notes }}</p>
                            @endif
                            @can('manage-media')
                                @unless ($entry->trashed())
                                    <form class="admin-action-form" action="{{ route('admin.curation.media.permission', [$entry->id, $media->id]) }}" method="POST">
                                        @csrf
                                        <label>
                                            <span>Catatan izin / alasan</span>
                                            <textarea name="notes" rows="2" maxlength="5000" placeholder="Cantumkan bukti lisensi atau alasan penolakan"></textarea>
                                        </label>
                                        <div class="admin-action-buttons">
                                            <button class="admin-button admin-button-approve" type="submit" name="decision" value="grant">Setujui izin</button>
                                            <button class="admin-button admin-button-reject" type="submit" name="decision" value="reject">Tolak</button>
                                            <button class="admin-button admin-button-muted" type="submit" name="decision" value="pending">Tetap pending</button>
                                        </div>
                                    </form>
                                @endunless
                            @endcan
                        </div>
                    </article>
                @empty
                    <p class="admin-empty-note">Tidak ada media yang terkait. Media tidak diwajibkan untuk publikasi.</p>
                @endforelse
            </section>
        </aside>
    </div>

    <div class="admin-review-grid">
        <section class="admin-panel admin-action-panel">
            <div class="admin-panel-heading">
                <div>
                    <p class="admin-eyebrow"><span></span> Keputusan kurator</p>
                    <h2>Verifikasi informasi</h2>
                </div>
            </div>
            @can('verify-content')
                @unless ($entry->trashed())
                    <form action="{{ route('admin.curation.entries.verification', $entry->id) }}" method="POST">
                        @csrf
                        <label class="admin-form-label" for="verification-notes">Catatan pemeriksaan / alasan penolakan</label>
                        <textarea id="verification-notes" name="notes" rows="4" maxlength="5000" placeholder="Tuliskan temuan, sumber yang perlu dilengkapi, atau alasan keputusan">{{ old('notes') }}</textarea>
                        <div class="admin-action-buttons">
                            <button class="admin-button admin-button-approve" type="submit" name="decision" value="approve">Setujui verifikasi</button>
                            <button class="admin-button admin-button-reject" type="submit" name="decision" value="reject">Tolak / minta perbaikan</button>
                        </div>
                        <p class="admin-action-footnote">Penolakan menyimpan status verifikasi sebagai pending dan dicatat di riwayat. Status publikasi tidak berubah.</p>
                    </form>
                @endunless
            @else
                <p class="admin-empty-note">Role Anda dapat membaca detail, tetapi tidak memiliki izin verifikasi.</p>
            @endcan
        </section>

        <section class="admin-panel admin-action-panel">
            <div class="admin-panel-heading">
                <div>
                    <p class="admin-eyebrow"><span></span> Persetujuan editorial</p>
                    <h2>Publikasi</h2>
                </div>
            </div>
            <p class="admin-warning-note">Persetujuan publikasi oleh Admin atau Super Admin menentukan apakah artikel tampil di Arsip. Status verifikasi tetap dicatat terpisah dan bukan syarat publikasi.</p>
            @if ($publicationBlockers !== [])
                <div class="admin-blocker-panel">
                    <strong>Syarat publikasi yang belum terpenuhi</strong>
                    <ul>
                        @foreach ($publicationBlockers as $blocker)
                            <li>{{ $blocker }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            @if ($entry->media->contains(fn ($media) => $media->permission_status !== 'granted'))
                <p class="admin-warning-note">Media yang belum granted akan tetap tersembunyi di halaman publik. Menyetujui publikasi entri tidak mengubah izin media.</p>
            @endif
            @can('manage-cultural-content')
                @unless ($entry->trashed())
                    <form action="{{ route('admin.curation.entries.publication', $entry->id) }}" method="POST">
                        @csrf
                        <label class="admin-form-label" for="publication-notes">Catatan publikasi (opsional)</label>
                        <textarea id="publication-notes" name="notes" rows="3" maxlength="5000" placeholder="Catatan keputusan publikasi">{{ old('notes') }}</textarea>
                        <div class="admin-action-buttons">
                            @if ($entry->publication_status === 'published')
                                <button class="admin-button admin-button-reject" type="submit" name="action" value="draft">Tarik dari publikasi</button>
                            @else
                                <button class="admin-button admin-button-publish" type="submit" name="action" value="publish" @disabled($publicationBlockers !== [])>Publikasikan</button>
                                <button class="admin-button admin-button-muted" type="submit" name="action" value="draft">Simpan sebagai draft</button>
                            @endif
                        </div>
                    </form>
                @endunless
            @else
                <p class="admin-empty-note">Role Anda tidak memiliki izin untuk mengubah status publikasi.</p>
            @endcan
        </section>
    </div>
@endsection
