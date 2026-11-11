@extends('admin.layouts.app')

@section('title', 'Data Budaya | WARNA BUDAYA')

@section('content')
    <div
        class="cultural-entry-config"
        data-cultural-entry-config
        data-index-url="{{ route('admin.cultural-entries.api.index') }}"
        data-store-url="{{ route('admin.cultural-entries.api.store') }}"
        data-show-template="{{ route('admin.cultural-entries.api.show', ['entry' => '__id__']) }}"
        data-update-template="{{ route('admin.cultural-entries.api.update', ['entry' => '__id__']) }}"
        data-delete-template="{{ route('admin.cultural-entries.api.destroy', ['entry' => '__id__']) }}"
        data-publication-template="{{ route('admin.curation.entries.publication', ['entry' => '__id__']) }}"
        data-csrf-token="{{ csrf_token() }}"
        data-regions-url="{{ route('admin.regions.index', ['per_page' => 100, 'status' => 'active']) }}"
        data-categories-url="{{ route('admin.categories.index', ['per_page' => 100, 'status' => 'active']) }}"
    ></div>

    <section class="admin-page-heading cultural-entry-heading">
        <div>
            <p class="admin-eyebrow"><span></span> Pengelolaan arsip budaya</p>
            <h1>Data <em>Budaya.</em></h1>
            <p>Rawat informasi budaya, wilayah asal, dan kategorinya. Admin dan Super Admin dapat menyetujui publikasi langsung dari daftar ini.</p>
        </div>
        <img class="admin-heading-stamp" src="{{ asset('images/brand-emblem.svg') }}" alt="">
    </section>

    <section class="cultural-entry-workspace" aria-labelledby="cultural-entry-list-title">
        <div class="cultural-entry-toolbar">
            <div>
                <p class="admin-eyebrow"><span></span> Direktori pengetahuan</p>
                <h2 id="cultural-entry-list-title">Daftar entri <span data-entry-total>—</span></h2>
                <p>Data dimuat dari API administrasi yang terautentikasi.</p>
            </div>
            <div class="cultural-entry-toolbar-actions">
                <form
                    class="cultural-entry-bulk-publish-form"
                    method="POST"
                    action="{{ route('admin.curation.entries.publication.bulk') }}"
                    data-bulk-publication-form
                >
                    @csrf
                    <input type="hidden" name="confirm_bulk_publish" value="1">
                    <button class="cultural-entry-button cultural-entry-button-publish-all" type="submit">
                        Publikasikan semua yang layak
                    </button>
                </form>
                <button class="cultural-entry-button cultural-entry-button-primary" type="button" data-entry-create>
                    <span aria-hidden="true">＋</span> Tambah entri
                </button>
            </div>
        </div>

        <form class="cultural-entry-filters" data-entry-filter-form>
            <label class="cultural-entry-search">
                <span class="admin-sr-only">Cari entri budaya</span>
                <span aria-hidden="true">⌕</span>
                <input type="search" name="search" maxlength="100" placeholder="Cari nama atau uraian budaya...">
            </label>
            <label>
                <span class="admin-sr-only">Filter wilayah</span>
                <select name="region_id" data-region-filter>
                    <option value="">Semua wilayah</option>
                </select>
            </label>
            <label>
                <span class="admin-sr-only">Filter kategori</span>
                <select name="category_id" data-category-filter>
                    <option value="">Semua kategori</option>
                </select>
            </label>
            <label>
                <span class="admin-sr-only">Filter status publikasi</span>
                <select name="publication_status">
                    <option value="">Semua publikasi</option>
                    <option value="draft">Draft</option>
                    <option value="published">Published</option>
                </select>
            </label>
            <label>
                <span class="admin-sr-only">Filter status verifikasi</span>
                <select name="verification_status">
                    <option value="">Semua verifikasi</option>
                    <option value="pending">Pending</option>
                    <option value="verified">Verified</option>
                </select>
            </label>
            <label>
                <span class="admin-sr-only">Filter arsip terhapus</span>
                <select name="trashed">
                    <option value="">Data aktif</option>
                    <option value="only">Hanya soft deleted</option>
                    <option value="with">Termasuk soft deleted</option>
                </select>
            </label>
            <button class="cultural-entry-button cultural-entry-button-filter" type="submit">Terapkan</button>
            <button class="cultural-entry-reset" type="reset">Atur ulang</button>
        </form>

        <div class="cultural-entry-table-wrap">
            <table class="cultural-entry-table">
                <thead>
                    <tr>
                        <th scope="col">Nama budaya</th>
                        <th scope="col">Wilayah asal</th>
                        <th scope="col">Kategori</th>
                        <th scope="col">Verifikasi</th>
                        <th scope="col">Publikasi</th>
                        <th scope="col">Media</th>
                        <th scope="col"><span class="admin-sr-only">Tindakan</span></th>
                    </tr>
                </thead>
                <tbody data-entry-rows>
                    <tr>
                        <td class="cultural-entry-empty" colspan="7">
                            <strong>Memuat arsip budaya…</strong>
                            <small>Data ditampilkan dari database melalui API.</small>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <nav class="cultural-entry-pagination" aria-label="Navigasi halaman entri budaya" data-entry-pagination></nav>
    </section>

    <div class="cultural-entry-feedback" role="status" aria-live="polite" data-entry-feedback hidden></div>
    @if (session('status'))
        <div class="cultural-entry-feedback is-success" role="status">{{ session('status') }}</div>
    @endif
    @if (session('publication_bulk_skipped'))
        <section class="cultural-entry-bulk-results" role="status">
            <h2>Entri yang belum dipublikasikan</h2>
            <ul>
                @foreach (session('publication_bulk_skipped') as $skipped)
                    <li><strong>{{ $skipped['title'] }}</strong>: {{ implode(' ', $skipped['reasons']) }}</li>
                @endforeach
            </ul>
        </section>
    @endif

    <dialog class="cultural-entry-dialog" id="cultural-entry-form-dialog" aria-labelledby="cultural-entry-form-title">
        <form data-entry-form>
            <div class="cultural-entry-dialog-heading">
                <div>
                    <p class="admin-eyebrow"><span></span> Entri arsip</p>
                    <h2 id="cultural-entry-form-title">Tambah data budaya</h2>
                </div>
                <button class="cultural-entry-dialog-close" type="button" data-entry-close aria-label="Tutup">×</button>
            </div>
            <div class="cultural-entry-form-grid">
                <label>Nama budaya
                    <input name="title" type="text" maxlength="255" required>
                </label>
                <label>Slug
                    <input name="slug" type="text" maxlength="255" required autocomplete="off" placeholder="contoh: tari-saman">
                </label>
                <label>Nama lokal
                    <input name="local_name" type="text" maxlength="255">
                </label>
                <label>Wilayah asal
                    <select name="origin_region_id" data-region-select>
                        <option value="">Belum ditentukan</option>
                    </select>
                </label>
                <label class="is-wide">Kategori budaya
                    <select name="category_ids" multiple size="5" data-category-select aria-describedby="cultural-entry-category-help"></select>
                    <small id="cultural-entry-category-help">Pilih satu atau beberapa kategori dengan Ctrl/Command.</small>
                </label>
                <label class="is-wide">Deskripsi singkat
                    <textarea name="short_description" rows="3" maxlength="16000"></textarea>
                </label>
                <label class="is-wide">Deskripsi lengkap
                    <textarea name="description" rows="5"></textarea>
                </label>
                <label class="is-wide">Sejarah
                    <textarea name="history" rows="4"></textarea>
                </label>
                <label class="is-wide">Makna dan fungsi
                    <textarea name="meaning_and_function" rows="4"></textarea>
                </label>
                <label class="is-wide">Teknik atau proses
                    <textarea name="techniques" rows="4"></textarea>
                </label>
                <label class="is-wide">Bahan
                    <textarea name="materials" rows="3" maxlength="16000"></textarea>
                </label>
                <label class="is-wide">Konteks komunitas
                    <textarea name="community_context" rows="3" maxlength="16000"></textarea>
                </label>
                <label class="is-wide">Kata kunci
                    <textarea name="keywords" rows="2" placeholder="Satu kata kunci per baris"></textarea>
                </label>
            </div>
            <p class="cultural-entry-form-note">Status verifikasi dan publikasi tidak dapat diubah dari form ini.</p>
            <div class="cultural-entry-dialog-actions">
                <button class="cultural-entry-button cultural-entry-button-muted" type="button" data-entry-close>Batal</button>
                <button class="cultural-entry-button cultural-entry-button-primary" type="submit" data-entry-save>Simpan entri</button>
            </div>
        </form>
    </dialog>

    <dialog class="cultural-entry-dialog cultural-entry-detail-dialog" id="cultural-entry-detail-dialog" aria-labelledby="cultural-entry-detail-title">
        <div class="cultural-entry-dialog-heading">
            <div>
                <p class="admin-eyebrow"><span></span> Rincian arsip</p>
                <h2 id="cultural-entry-detail-title">Detail data budaya</h2>
            </div>
            <button class="cultural-entry-dialog-close" type="button" data-entry-close aria-label="Tutup">×</button>
        </div>
        <div class="cultural-entry-detail-content" data-entry-detail></div>
        <div class="cultural-entry-dialog-actions">
            <button class="cultural-entry-button cultural-entry-button-muted" type="button" data-entry-close>Tutup</button>
            <button class="cultural-entry-button cultural-entry-button-primary" type="button" data-entry-detail-edit hidden>Ubah entri</button>
        </div>
    </dialog>
@endsection
