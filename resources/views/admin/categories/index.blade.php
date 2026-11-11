@extends('admin.layouts.app')

@section('title', 'Manajemen Kategori | WARNA BUDAYA')

@section('content')
    @php
        $categoryApiReady = Route::has('admin.categories.index')
            && Route::has('admin.categories.store')
            && Route::has('admin.categories.update')
            && Route::has('admin.categories.status');
        $categoryPageReady = Route::has('admin.categories.page');
    @endphp
    <div
        class="admin-taxonomy-config"
        data-taxonomy-config
        data-kind="category"
        data-csrf-token="{{ csrf_token() }}"
        data-api-ready="{{ $categoryApiReady ? 'true' : 'false' }}"
        @if (Route::has('admin.categories.index')) data-index-url="{{ route('admin.categories.index') }}" @endif
        @if (Route::has('admin.categories.store')) data-store-url="{{ route('admin.categories.store') }}" @endif
        @if (Route::has('admin.categories.update')) data-update-template="{{ route('admin.categories.update', ['category' => '__id__']) }}" @endif
        @if (Route::has('admin.categories.status')) data-status-template="{{ route('admin.categories.status', ['category' => '__id__']) }}" @endif
    ></div>

    <section class="admin-page-heading admin-taxonomy-heading">
        <div>
            <p class="admin-eyebrow"><span></span> Pengelolaan taksonomi budaya</p>
            <h1>Kategori <em>Budaya.</em></h1>
            <p>Susun ragam ekspresi budaya dalam katalog yang tertata, jelas, dan mudah dijelajahi.</p>
        </div>
        <img class="admin-heading-stamp" src="{{ asset('images/brand-emblem.svg') }}" alt="">
    </section>

    @unless ($categoryApiReady && $categoryPageReady)
        <aside class="admin-contract-note" role="status">
            <span aria-hidden="true">◇</span>
            <div>
                <strong>{{ $categoryApiReady ? 'API tersedia; halaman admin menunggu integrasi' : 'UI siap diintegrasikan dengan backend' }}</strong>
                <p>Kontrak API JSON: <code>admin.categories.index/store/update/status</code>. Daftar memakai properti <code>data</code>, <code>meta</code>, dan <code>links</code>; halaman Blade perlu route <code>admin.categories.page</code>. Tidak ada endpoint baru dibuat oleh UI ini.</p>
            </div>
        </aside>
    @endunless

    <section class="admin-taxonomy-workspace" aria-labelledby="categories-title">
        <div class="admin-taxonomy-toolbar">
            <div>
                <p class="admin-eyebrow"><span></span> Indeks pengetahuan</p>
                <h2 id="categories-title">Daftar kategori <span data-record-total>—</span></h2>
                <p>Daftar kategori dimuat langsung dari endpoint backend yang sudah tersedia.</p>
            </div>
            <button class="admin-taxonomy-button admin-taxonomy-button-primary" type="button" data-dialog-open="category-create-dialog">
                <span aria-hidden="true">＋</span> Tambah kategori
            </button>
        </div>

        <form class="admin-taxonomy-filters" data-filter-form>
            <label class="admin-taxonomy-search">
                <span class="admin-sr-only">Cari nama atau slug kategori</span>
                <span aria-hidden="true">⌕</span>
                <input type="search" name="search" placeholder="Cari nama atau slug kategori...">
            </label>
            <label class="admin-taxonomy-select">
                <span class="admin-sr-only">Filter status kategori</span>
                <select name="status">
                    <option value="">Semua status</option>
                    <option value="active">Aktif</option>
                    <option value="inactive">Nonaktif</option>
                </select>
            </label>
            <button class="admin-taxonomy-button admin-taxonomy-button-filter" type="submit">Terapkan filter</button>
            <button class="admin-taxonomy-reset" type="reset">Atur ulang</button>
        </form>

        <div class="admin-taxonomy-table-wrap">
            <table class="admin-taxonomy-table admin-category-table">
                <thead>
                    <tr>
                        <th scope="col">Nama kategori</th>
                        <th scope="col">Slug</th>
                        <th scope="col">Deskripsi</th>
                        <th scope="col">Entri budaya</th>
                        <th scope="col">Status</th>
                        <th scope="col"><span class="admin-sr-only">Tindakan</span></th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td class="admin-taxonomy-empty" colspan="6" data-record-empty>
                        <span aria-hidden="true">◇</span>
                        <strong>Memuat data kategori…</strong>
                        <small>Data ditampilkan dari endpoint admin yang terautentikasi.</small>
                    </td></tr>
                </tbody>
            </table>
        </div>
        <nav class="admin-taxonomy-pagination" aria-label="Navigasi halaman kategori" data-pagination></nav>
    </section>

    <div class="admin-taxonomy-feedback" role="status" aria-live="polite" data-feedback hidden></div>

    <dialog class="admin-taxonomy-dialog" id="category-create-dialog" aria-labelledby="category-create-title">
        <form
            method="POST"
            data-confirm="Simpan kategori baru?"
            data-record-form="create"
        >
            @csrf
            <div class="admin-taxonomy-dialog-heading">
                <div><p class="admin-eyebrow"><span></span> Taksonomi budaya</p><h2 id="category-create-title">Tambah kategori</h2></div>
                <button class="admin-taxonomy-dialog-close" type="button" data-dialog-close aria-label="Tutup">×</button>
            </div>
            <div class="admin-taxonomy-form-grid">
                <label>Nama kategori<input name="name" type="text" maxlength="255" required autocomplete="off"></label>
                <label>Slug<input name="slug" type="text" maxlength="255" required autocomplete="off" placeholder="contoh: seni-pertunjukan"></label>
                <label class="is-wide">Deskripsi<textarea name="description" rows="4" maxlength="16000"></textarea></label>
                <label class="admin-taxonomy-checkbox"><input type="checkbox" name="is_active" value="1" checked><span>Kategori aktif</span></label>
            </div>
            <p class="admin-taxonomy-form-contract">Data akan dikirim ke endpoint admin setelah dikonfirmasi.</p>
            <div class="admin-taxonomy-dialog-actions">
                <button class="admin-taxonomy-button admin-taxonomy-button-muted" type="button" data-dialog-close>Batal</button>
                <button class="admin-taxonomy-button admin-taxonomy-button-primary" type="submit" @disabled(! $categoryApiReady)>Simpan kategori</button>
            </div>
        </form>
    </dialog>

    <dialog class="admin-taxonomy-dialog" id="category-edit-dialog" aria-labelledby="category-edit-title">
        <form method="POST" data-confirm="Simpan perubahan kategori ini?" data-record-form="edit">
            @csrf
            <div class="admin-taxonomy-dialog-heading">
                <div><p class="admin-eyebrow"><span></span> Taksonomi budaya</p><h2 id="category-edit-title">Ubah kategori</h2></div>
                <button class="admin-taxonomy-dialog-close" type="button" data-dialog-close aria-label="Tutup">×</button>
            </div>
            <div class="admin-taxonomy-form-grid">
                <label>Nama kategori<input name="name" type="text" maxlength="255" required data-edit-field="name"></label>
                <label>Slug<input name="slug" type="text" maxlength="255" required data-edit-field="slug"></label>
                <label class="is-wide">Deskripsi<textarea name="description" rows="4" maxlength="16000" data-edit-field="description"></textarea></label>
                <label class="admin-taxonomy-checkbox"><input type="checkbox" name="is_active" value="1" data-edit-field="active"><span>Kategori aktif</span></label>
            </div>
            <p class="admin-taxonomy-form-contract">Perubahan dikirim ke endpoint admin setelah dikonfirmasi.</p>
            <div class="admin-taxonomy-dialog-actions">
                <button class="admin-taxonomy-button admin-taxonomy-button-muted" type="button" data-dialog-close>Batal</button>
                <button class="admin-taxonomy-button admin-taxonomy-button-primary" type="submit" @disabled(! $categoryApiReady)>Simpan perubahan</button>
            </div>
        </form>
    </dialog>
@endsection
