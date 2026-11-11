@extends('admin.layouts.app')

@section('title', 'Media dan Sumber | WARNA BUDAYA')

@section('content')
    <div
        class="media-sources-config"
        data-media-sources-config
        data-csrf-token="{{ csrf_token() }}"
        data-media-index-url="{{ route('admin.api.media.index') }}"
        data-media-store-url="{{ route('admin.api.media.store') }}"
        data-media-show-template="{{ route('admin.api.media.show', ['media' => '__id__']) }}"
        data-media-update-template="{{ route('admin.api.media.update', ['media' => '__id__']) }}"
        data-media-delete-template="{{ route('admin.api.media.destroy', ['media' => '__id__']) }}"
        data-source-index-url="{{ route('admin.api.sources.index') }}"
        data-source-store-url="{{ route('admin.api.sources.store') }}"
        data-source-show-template="{{ route('admin.api.sources.show', ['source' => '__id__']) }}"
        data-source-update-template="{{ route('admin.api.sources.update', ['source' => '__id__']) }}"
        data-source-delete-template="{{ route('admin.api.sources.destroy', ['source' => '__id__']) }}"
        data-entry-index-url="{{ route('admin.cultural-entries.api.index', ['per_page' => 100]) }}"
        data-region-index-url="{{ route('admin.regions.index', ['per_page' => 100, 'status' => 'active']) }}"
        data-category-index-url="{{ route('admin.categories.index', ['per_page' => 100, 'status' => 'active']) }}"
        data-entry-with-media-url="{{ route('admin.cultural-entries.api.store-with-media') }}"
        data-can-media="{{ Gate::allows('manage-media') ? 'true' : 'false' }}"
        data-can-sources="{{ Gate::allows('manage-sources') ? 'true' : 'false' }}"
    ></div>

    <section class="admin-page-heading media-sources-heading">
        <div>
            <p class="admin-eyebrow"><span></span> Pengelolaan referensi arsip</p>
            <h1>Media dan <em>Sumber.</em></h1>
            <p>Rawat dokumentasi visual dan rujukan pengetahuan yang menopang setiap kisah budaya.</p>
        </div>
        <div class="media-sources-heading-actions">
            @can('manage-media')
                <form action="{{ route('admin.curation.media.permission.bulk') }}" method="POST" onsubmit="return confirm('Setujui semua media pending yang memenuhi syarat file, sumber, kreator, caption, alt text, dan lisensi? Artikel budaya tidak akan dipublikasikan oleh tindakan ini.');">
                    @csrf
                    <input type="hidden" name="return_to" value="media-sources">
                    <button class="media-sources-button is-bulk-approve" type="submit">Setujui semua media yang layak</button>
                </form>
                <button class="media-sources-button is-heading-action" type="button" data-media-create><span aria-hidden="true">＋</span> Tambah media</button>
            @endcan
            @can('manage-sources')
                <button class="media-sources-button is-heading-action" type="button" data-source-create><span aria-hidden="true">＋</span> Tambah sumber</button>
            @endcan
            <img class="admin-heading-stamp" src="{{ asset('images/brand-emblem.svg') }}" alt="">
        </div>
    </section>

    @if (session()->has('status'))
        <div class="admin-flash admin-flash-success" role="status" aria-live="polite">
            <strong>{{ session('status') }}</strong>
            @if (session()->has('media_bulk_granted_count'))
                <p>{{ number_format(session('media_bulk_granted_count'), 0, ',', '.') }} media disetujui. Entri budaya tidak mengalami perubahan status publikasi maupun verifikasi.</p>
            @endif
            @if (count(session('media_bulk_skipped', [])) > 0)
                <p>Media berikut dilewati:</p>
                <ul>
                    @foreach (session('media_bulk_skipped') as $skippedMedia)
                        <li><strong>{{ $skippedMedia['title'] }}</strong> — {{ $skippedMedia['reason'] }}</li>
                    @endforeach
                </ul>
            @elseif (session()->has('media_bulk_granted_count'))
                <p>Tidak ada media yang dilewati.</p>
            @endif
        </div>
    @endif

    <section class="media-sources-workspace" aria-label="Pengelolaan media dan sumber">
        <div class="media-sources-tabs" role="tablist" aria-label="Jenis referensi">
            @can('manage-media')
                <button class="media-sources-tab is-active" id="media-tab" type="button" role="tab" aria-selected="true" aria-controls="media-panel" data-media-tab="media">
                    <span aria-hidden="true">▧</span> Media
                </button>
            @endcan
            @can('manage-sources')
                <button class="media-sources-tab{{ Gate::allows('manage-media') ? '' : ' is-active' }}" id="sources-tab" type="button" role="tab" aria-selected="{{ Gate::allows('manage-media') ? 'false' : 'true' }}" aria-controls="sources-panel" data-media-tab="sources">
                    <span aria-hidden="true">⌑</span> Sumber Referensi
                </button>
            @endcan
        </div>

        @can('manage-media')
            <section class="media-sources-panel" id="media-panel" role="tabpanel" aria-labelledby="media-tab" data-media-panel="media">
                <div class="media-sources-toolbar">
                    <div>
                        <p class="admin-eyebrow"><span></span> Koleksi visual</p>
                        <h2>Daftar media <span data-media-total>—</span></h2>
                        <p>Status izin ditampilkan sebagai informasi dan hanya diubah melalui alur pemeriksaan izin.</p>
                    </div>
                </div>
                <form class="media-sources-filters" data-media-filter-form>
                    <label class="media-sources-search"><span class="admin-sr-only">Cari media atau entri budaya</span><span aria-hidden="true">⌕</span><input type="search" name="search" maxlength="100" placeholder="Cari keterangan, pencipta, atau budaya..."></label>
                    <label><span class="admin-sr-only">Filter tipe media</span><input type="search" name="media_type" maxlength="100" placeholder="Tipe media"></label>
                    <label><span class="admin-sr-only">Filter status izin</span><select name="permission_status"><option value="">Semua status izin</option><option value="pending">Pending</option><option value="granted">Granted</option></select></label>
                    <label><span class="admin-sr-only">Filter entri budaya</span><select name="cultural_entry_id" data-media-entry-filter><option value="">Semua entri budaya</option></select></label>
                    <button class="media-sources-button is-filter" type="submit">Terapkan</button>
                    <button class="media-sources-reset" type="reset">Atur ulang</button>
                </form>
                <div class="media-sources-table-wrap">
                    <table class="media-sources-table">
                        <thead><tr><th>Pratinjau</th><th>Keterangan</th><th>Entri budaya</th><th>Tipe</th><th>Izin</th><th><span class="admin-sr-only">Tindakan</span></th></tr></thead>
                        <tbody data-media-rows><tr><td class="media-sources-empty" colspan="6"><strong>Memuat koleksi media…</strong><small>Data dimuat dari API pengelola.</small></td></tr></tbody>
                    </table>
                </div>
                <nav class="media-sources-pagination" aria-label="Navigasi halaman media" data-media-pagination></nav>
            </section>
        @endcan

        @can('manage-sources')
            <section class="media-sources-panel" id="sources-panel" role="tabpanel" aria-labelledby="sources-tab" data-media-panel="sources" @if (Gate::allows('manage-media')) hidden @endif>
                <div class="media-sources-toolbar">
                    <div>
                        <p class="admin-eyebrow"><span></span> Bibliografi arsip</p>
                        <h2>Daftar sumber <span data-source-total>—</span></h2>
                        <p>Kelola rujukan dan keterhubungannya dengan entri budaya.</p>
                    </div>
                </div>
                <form class="media-sources-filters" data-source-filter-form>
                    <label class="media-sources-search"><span class="admin-sr-only">Cari sumber</span><span aria-hidden="true">⌕</span><input type="search" name="search" maxlength="100" placeholder="Cari judul, penulis, penerbit, atau ID..."></label>
                    <label><span class="admin-sr-only">Filter jenis sumber</span><input type="search" name="source_type" maxlength="100" placeholder="Jenis sumber"></label>
                    <label><span class="admin-sr-only">Filter entri budaya terkait</span><select name="cultural_entry_id" data-source-entry-filter><option value="">Semua entri budaya</option></select></label>
                    <button class="media-sources-button is-filter" type="submit">Terapkan</button>
                    <button class="media-sources-reset" type="reset">Atur ulang</button>
                </form>
                <div class="media-sources-table-wrap">
                    <table class="media-sources-table">
                        <thead><tr><th>Jenis</th><th>Judul sumber</th><th>Penulis / penerbit</th><th>URL</th><th>Terhubung dengan</th><th><span class="admin-sr-only">Tindakan</span></th></tr></thead>
                        <tbody data-source-rows><tr><td class="media-sources-empty" colspan="6"><strong>Memuat sumber referensi…</strong><small>Data dimuat dari API pengelola.</small></td></tr></tbody>
                    </table>
                </div>
                <nav class="media-sources-pagination" aria-label="Navigasi halaman sumber" data-source-pagination></nav>
            </section>
        @endcan
    </section>

    <div class="media-sources-feedback" role="status" aria-live="polite" data-media-sources-feedback hidden></div>

    @can('manage-media')
        <dialog class="media-sources-dialog" id="media-form-dialog" aria-labelledby="media-form-title">
            <form data-media-form>
                <div class="media-sources-dialog-heading"><div><p class="admin-eyebrow"><span></span> Dokumentasi visual</p><h2 id="media-form-title">Tambah media</h2></div><button type="button" class="media-sources-close" data-media-close aria-label="Tutup">×</button></div>
                <div class="media-sources-form-grid">
                    <label class="is-wide">Entri budaya
                        <select name="cultural_entry_id" required data-media-entry-select>
                            <option value="">Pilih entri budaya</option>
                        </select>
                    </label>
                    <section class="media-sources-custom-entry is-wide" data-custom-entry-panel hidden aria-labelledby="custom-entry-title">
                        <div class="media-sources-custom-entry-heading">
                            <p class="admin-eyebrow"><span></span> Entri arsip baru</p>
                            <h3 id="custom-entry-title">Informasi budaya</h3>
                            <p>Entri akan dibuat sebagai draft dan menunggu verifikasi. Status ini tidak dapat diubah dari form media.</p>
                        </div>
                        <div class="media-sources-form-grid">
                            <label>Nama budaya
                                <input name="entry[title]" type="text" maxlength="255" required disabled data-custom-entry-field>
                            </label>
                            <label>Slug
                                <input name="entry[slug]" type="text" maxlength="255" required autocomplete="off" placeholder="contoh: tari-saman" disabled data-custom-entry-field>
                            </label>
                            <label>Nama lokal
                                <input name="entry[local_name]" type="text" maxlength="255" disabled data-custom-entry-field>
                            </label>
                            <label>Wilayah asal
                                <select name="entry[origin_region_id]" disabled data-custom-entry-field data-custom-region-select>
                                    <option value="">Belum ditentukan</option>
                                </select>
                            </label>
                            <label class="is-wide">Kategori budaya
                                <select name="entry[category_ids][]" multiple size="5" aria-describedby="media-custom-category-help" disabled data-custom-entry-field data-custom-category-select></select>
                                <small id="media-custom-category-help">Pilih satu atau beberapa kategori dengan Ctrl/Command.</small>
                            </label>
                            <label class="is-wide">Deskripsi singkat
                                <textarea name="entry[short_description]" rows="3" maxlength="16000" disabled data-custom-entry-field></textarea>
                            </label>
                            <label class="is-wide">Deskripsi lengkap
                                <textarea name="entry[description]" rows="5" disabled data-custom-entry-field></textarea>
                            </label>
                            <label class="is-wide">Sejarah
                                <textarea name="entry[history]" rows="4" disabled data-custom-entry-field></textarea>
                            </label>
                            <label class="is-wide">Makna dan fungsi
                                <textarea name="entry[meaning_and_function]" rows="4" disabled data-custom-entry-field></textarea>
                            </label>
                            <label class="is-wide">Teknik atau proses
                                <textarea name="entry[techniques]" rows="4" disabled data-custom-entry-field></textarea>
                            </label>
                            <label class="is-wide">Bahan
                                <textarea name="entry[materials]" rows="3" maxlength="16000" disabled data-custom-entry-field></textarea>
                            </label>
                            <label class="is-wide">Konteks komunitas
                                <textarea name="entry[community_context]" rows="3" maxlength="16000" disabled data-custom-entry-field></textarea>
                            </label>
                            <label class="is-wide">Kata kunci
                                <textarea name="entry[keywords]" rows="2" placeholder="Satu kata kunci per baris" disabled data-custom-entry-field></textarea>
                            </label>
                        </div>
                    </section>
                    <label>Tipe media<input name="media_type" maxlength="30" placeholder="Contoh: image"></label>
                    <label>Path berkas<input name="file_path" maxlength="255" placeholder="media/arsip/foto.jpg"></label>
                    <label class="is-wide">URL eksternal<input name="external_url" type="url" maxlength="10000" placeholder="https://..."></label>
                    <p class="media-sources-form-hint is-wide">Isi path berkas atau URL eksternal. API mensyaratkan salah satunya.</p>
                    <label class="is-wide">Keterangan<input name="caption" maxlength="255"></label>
                    <label class="is-wide">Teks alternatif<input name="alt_text" maxlength="255"></label>
                    <label>Pencipta<input name="creator" maxlength="255"></label>
                    <label>Pemegang hak cipta<input name="copyright_holder" maxlength="255"></label>
                    <label>Lisensi<input name="license" maxlength="255"></label>
                    <label>Urutan tampil<input name="sort_order" type="number" min="0" max="4294967295"></label>
                    <label class="media-sources-checkbox"><input name="is_cover" type="checkbox" value="1"><span>Jadikan sampul entri</span></label>
                    <label class="is-wide" data-media-create-only>Catatan izin awal<textarea name="permission_notes" rows="3" maxlength="10000"></textarea><small>Catatan ini hanya dicatat saat media dibuat. Perubahan izin dilakukan melalui proses verifikasi.</small></label>
                </div>
                <p class="media-sources-form-error" data-media-form-error hidden></p>
                <div class="media-sources-dialog-actions"><button type="button" class="media-sources-button is-muted" data-media-close>Batal</button><button type="submit" class="media-sources-button is-primary" data-media-save>Simpan media</button></div>
            </form>
        </dialog>
        <dialog class="media-sources-dialog" id="media-detail-dialog" aria-labelledby="media-detail-title">
            <div class="media-sources-dialog-heading"><div><p class="admin-eyebrow"><span></span> Rincian koleksi</p><h2 id="media-detail-title">Detail media</h2></div><button type="button" class="media-sources-close" data-media-close aria-label="Tutup">×</button></div>
            <div class="media-sources-detail" data-media-detail></div>
            <div class="media-sources-dialog-actions"><button type="button" class="media-sources-button is-muted" data-media-close>Tutup</button></div>
        </dialog>
    @endcan

    @can('manage-sources')
        <dialog class="media-sources-dialog" id="source-form-dialog" aria-labelledby="source-form-title">
            <form data-source-form>
                <div class="media-sources-dialog-heading"><div><p class="admin-eyebrow"><span></span> Rujukan pengetahuan</p><h2 id="source-form-title">Tambah sumber</h2></div><button type="button" class="media-sources-close" data-source-close aria-label="Tutup">×</button></div>
                <div class="media-sources-form-grid">
                    <label>Jenis sumber<input name="source_type" maxlength="30" placeholder="Contoh: book"></label>
                    <label>Judul sumber<input name="title" maxlength="255" required></label>
                    <label>Penulis<input name="author" maxlength="255"></label>
                    <label>Penerbit<input name="publisher" maxlength="255"></label>
                    <label>Tahun terbit<input name="publication_year" type="number" min="1000" max="9999"></label>
                    <label>URL<input name="url" type="url" maxlength="10000"></label>
                    <label>Identifier<input name="identifier" maxlength="255"></label>
                    <label>Tanggal diakses<input name="accessed_at" type="date"></label>
                    <label class="is-wide">Catatan<textarea name="notes" rows="3" maxlength="10000"></textarea></label>
                    <label class="is-wide">Entri budaya terkait<select name="cultural_entry_ids" multiple size="6" data-source-entry-select aria-describedby="source-entry-help"></select><small id="source-entry-help">Pilih entri aktif yang dirujuk. Gunakan Ctrl/Command untuk memilih beberapa.</small></label>
                </div>
                <p class="media-sources-form-error" data-source-form-error hidden></p>
                <div class="media-sources-dialog-actions"><button type="button" class="media-sources-button is-muted" data-source-close>Batal</button><button type="submit" class="media-sources-button is-primary" data-source-save>Simpan sumber</button></div>
            </form>
        </dialog>
        <dialog class="media-sources-dialog" id="source-detail-dialog" aria-labelledby="source-detail-title">
            <div class="media-sources-dialog-heading"><div><p class="admin-eyebrow"><span></span> Rincian bibliografi</p><h2 id="source-detail-title">Detail sumber</h2></div><button type="button" class="media-sources-close" data-source-close aria-label="Tutup">×</button></div>
            <div class="media-sources-detail" data-source-detail></div>
            <div class="media-sources-dialog-actions"><button type="button" class="media-sources-button is-muted" data-source-close>Tutup</button></div>
        </dialog>
    @endcan
@endsection
