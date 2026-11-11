@extends('admin.layouts.app')

@section('title', 'Manajemen Wilayah | WARNA BUDAYA')

@section('content')
    @php
        $regionApiReady = Route::has('admin.regions.index')
            && Route::has('admin.regions.store')
            && Route::has('admin.regions.update')
            && Route::has('admin.regions.status');
        $regionPageReady = Route::has('admin.regions.page');
    @endphp
    <div
        class="admin-taxonomy-config"
        data-taxonomy-config
        data-kind="region"
        data-csrf-token="{{ csrf_token() }}"
        data-api-ready="{{ $regionApiReady ? 'true' : 'false' }}"
        @if (Route::has('admin.regions.index')) data-index-url="{{ route('admin.regions.index') }}" @endif
        @if (Route::has('admin.regions.store')) data-store-url="{{ route('admin.regions.store') }}" @endif
        @if (Route::has('admin.regions.update')) data-update-template="{{ route('admin.regions.update', ['region' => '__id__']) }}" @endif
        @if (Route::has('admin.regions.status')) data-status-template="{{ route('admin.regions.status', ['region' => '__id__']) }}" @endif
    ></div>

    <section class="admin-page-heading admin-taxonomy-heading">
        <div>
            <p class="admin-eyebrow"><span></span> Pengelolaan wilayah</p>
            <h1>Wilayah <em>Nusantara.</em></h1>
            <p>Rawat referensi wilayah asal agar setiap kisah budaya berakar pada tempat yang tepat.</p>
        </div>
        <img class="admin-heading-stamp" src="{{ asset('images/brand-emblem.svg') }}" alt="">
    </section>

    @unless ($regionApiReady && $regionPageReady)
        <aside class="admin-contract-note" role="status">
            <span aria-hidden="true">◇</span>
            <div>
                <strong>{{ $regionApiReady ? 'API tersedia; halaman admin menunggu integrasi' : 'UI siap diintegrasikan dengan backend' }}</strong>
                <p>Kontrak API JSON: <code>admin.regions.index/store/update/status</code>. Daftar memakai properti <code>data</code>, <code>meta</code>, dan <code>links</code>; halaman Blade perlu route <code>admin.regions.page</code>. Tidak ada endpoint baru dibuat oleh UI ini.</p>
            </div>
        </aside>
    @endunless

    <section class="admin-taxonomy-workspace" aria-labelledby="regions-title">
        <div class="admin-taxonomy-toolbar">
            <div>
                <p class="admin-eyebrow"><span></span> Direktori administratif</p>
                <h2 id="regions-title">Daftar wilayah <span data-record-total>—</span></h2>
                <p>Daftar wilayah dimuat langsung dari endpoint backend yang sudah tersedia.</p>
            </div>
            <button class="admin-taxonomy-button admin-taxonomy-button-primary" type="button" data-dialog-open="region-create-dialog">
                <span aria-hidden="true">＋</span> Tambah wilayah
            </button>
        </div>

        <form class="admin-taxonomy-filters" data-filter-form>
            <label class="admin-taxonomy-search">
                <span class="admin-sr-only">Cari nama atau slug wilayah</span>
                <span aria-hidden="true">⌕</span>
                <input type="search" name="search" placeholder="Cari nama atau slug wilayah...">
            </label>
            <label class="admin-taxonomy-select">
                <span class="admin-sr-only">Filter status wilayah</span>
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
            <table class="admin-taxonomy-table">
                <thead>
                    <tr>
                        <th scope="col">Nama wilayah</th>
                        <th scope="col">Slug</th>
                        <th scope="col">Tipe</th>
                        <th scope="col">Entri budaya</th>
                        <th scope="col">Status</th>
                        <th scope="col"><span class="admin-sr-only">Tindakan</span></th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td class="admin-taxonomy-empty" colspan="6" data-record-empty>
                        <span aria-hidden="true">⌖</span>
                        <strong>Memuat data wilayah…</strong>
                        <small>Data ditampilkan dari endpoint admin yang terautentikasi.</small>
                    </td></tr>
                </tbody>
            </table>
        </div>
        <nav class="admin-taxonomy-pagination" aria-label="Navigasi halaman wilayah" data-pagination></nav>
    </section>

    <div class="admin-taxonomy-feedback" role="status" aria-live="polite" data-feedback hidden></div>

    <dialog class="admin-taxonomy-dialog" id="region-create-dialog" aria-labelledby="region-create-title">
        <form
            method="POST"
            data-confirm="Simpan wilayah baru?"
            data-record-form="create"
        >
            @csrf
            <div class="admin-taxonomy-dialog-heading">
                <div><p class="admin-eyebrow"><span></span> Referensi geografis</p><h2 id="region-create-title">Tambah wilayah</h2></div>
                <button class="admin-taxonomy-dialog-close" type="button" data-dialog-close aria-label="Tutup">×</button>
            </div>
            <div class="admin-taxonomy-form-grid">
                <label>Nama wilayah<input name="name" type="text" maxlength="255" required autocomplete="off"></label>
                <label>Slug<input name="slug" type="text" maxlength="255" required autocomplete="off" placeholder="contoh: kalimantan-timur"></label>
                <label class="is-wide">Tipe wilayah<input name="region_type" type="text" maxlength="30" required value="province"></label>
                <label class="admin-taxonomy-checkbox"><input type="checkbox" name="is_active" value="1" checked><span>Wilayah aktif</span></label>
            </div>
            <p class="admin-taxonomy-form-contract">Data akan dikirim ke endpoint admin setelah dikonfirmasi.</p>
            <div class="admin-taxonomy-dialog-actions">
                <button class="admin-taxonomy-button admin-taxonomy-button-muted" type="button" data-dialog-close>Batal</button>
                <button class="admin-taxonomy-button admin-taxonomy-button-primary" type="submit" @disabled(! $regionApiReady)>Simpan wilayah</button>
            </div>
        </form>
    </dialog>

    <dialog class="admin-taxonomy-dialog" id="region-edit-dialog" aria-labelledby="region-edit-title">
        <form method="POST" data-confirm="Simpan perubahan wilayah ini?" data-record-form="edit">
            @csrf
            <div class="admin-taxonomy-dialog-heading">
                <div><p class="admin-eyebrow"><span></span> Referensi geografis</p><h2 id="region-edit-title">Ubah wilayah</h2></div>
                <button class="admin-taxonomy-dialog-close" type="button" data-dialog-close aria-label="Tutup">×</button>
            </div>
            <div class="admin-taxonomy-form-grid">
                <label>Nama wilayah<input name="name" type="text" maxlength="255" required data-edit-field="name"></label>
                <label>Slug<input name="slug" type="text" maxlength="255" required data-edit-field="slug"></label>
                <label class="is-wide">Tipe wilayah<input name="region_type" type="text" maxlength="30" required data-edit-field="type"></label>
                <label class="admin-taxonomy-checkbox"><input type="checkbox" name="is_active" value="1" data-edit-field="active"><span>Wilayah aktif</span></label>
            </div>
            <p class="admin-taxonomy-form-contract">Perubahan dikirim ke endpoint admin setelah dikonfirmasi.</p>
            <div class="admin-taxonomy-dialog-actions">
                <button class="admin-taxonomy-button admin-taxonomy-button-muted" type="button" data-dialog-close>Batal</button>
                <button class="admin-taxonomy-button admin-taxonomy-button-primary" type="submit" @disabled(! $regionApiReady)>Simpan perubahan</button>
            </div>
        </form>
    </dialog>
@endsection
