document.addEventListener('DOMContentLoaded', () => {
    const config = document.querySelector('[data-media-sources-config]');
    if (!config) {
        return;
    }

    const feedback = document.querySelector('[data-media-sources-feedback]');
    const entryOptions = [];
    const pageSize = 12;
    const CUSTOM_ENTRY = '__custom_entry__';
    const customEntryPanel = document.querySelector('[data-custom-entry-panel]');
    const mediaEntrySelect = document.querySelector('[data-media-entry-select]');
    const state = {
        mediaPage: null,
        sourcePage: null,
        media: { search: '', media_type: '', permission_status: '', cultural_entry_id: '' },
        sources: { search: '', source_type: '', cultural_entry_id: '' },
    };

    const showFeedback = (message, isError = false) => {
        feedback.textContent = message;
        feedback.hidden = false;
        feedback.classList.toggle('is-error', isError);
        feedback.classList.toggle('is-success', !isError);
    };

    const safeUrl = (value) => {
        const url = new URL(value, window.location.origin);
        if (!['http:', 'https:'].includes(url.protocol)) {
            throw new Error('URL tidak menggunakan protokol yang diizinkan.');
        }

        return url;
    };

    const safeApiUrl = (value) => {
        const url = safeUrl(value);
        if (url.origin !== window.location.origin) {
            throw new Error('Endpoint API mengarah ke host yang tidak diizinkan.');
        }

        return url;
    };

    const apiUrl = (template, id = null) => safeUrl(id === null ? template : template.replace('__id__', encodeURIComponent(id)));

    const readJson = async (response) => {
        const contentType = response.headers.get('content-type') ?? '';
        if (!contentType.includes('application/json')) {
            throw new Error(response.redirected
                ? 'Sesi pengelola berakhir. Silakan masuk kembali.'
                : 'Server tidak mengembalikan respons JSON yang diharapkan.');
        }

        const body = await response.json();
        if (!response.ok) {
            const validationErrors = Object.entries(body.errors ?? {}).flatMap(([field, messages]) =>
                messages.map((message) => `${field}: ${message}`));
            throw new Error(validationErrors.length
                ? validationErrors.join(' ')
                : body.message ?? `Permintaan gagal (${response.status}).`);
        }

        return body;
    };

    const request = async (url, { method = 'GET', body = null } = {}) => {
        const headers = { Accept: 'application/json' };
        const options = { method, headers, credentials: 'same-origin' };
        if (body !== null) {
            headers['Content-Type'] = 'application/json';
            headers['X-CSRF-TOKEN'] = config.dataset.csrfToken;
            options.body = JSON.stringify(body);
        }

        return readJson(await fetch(url, options));
    };

    const setEmpty = (tbody, columns, title, note = '') => {
        const row = document.createElement('tr');
        const cell = document.createElement('td');
        const heading = document.createElement('strong');
        const detail = document.createElement('small');
        cell.className = 'media-sources-empty';
        cell.colSpan = columns;
        heading.textContent = title;
        detail.textContent = note;
        cell.append(heading);
        if (note) {
            cell.append(detail);
        }
        row.append(cell);
        tbody.replaceChildren(row);
    };

    const cell = (label, className = '') => {
        const result = document.createElement('td');
        result.dataset.label = label;
        if (className) {
            result.className = className;
        }

        return result;
    };

    const makeButton = (label, action, id, type) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'media-sources-row-action';
        button.textContent = label;
        button.dataset.recordAction = action;
        button.dataset.recordId = String(id);
        button.dataset.recordType = type;

        return button;
    };

    const makeActions = (id, type) => {
        const wrapper = document.createElement('div');
        wrapper.className = 'media-sources-row-actions';
        wrapper.append(
            makeButton('Detail', 'detail', id, type),
            makeButton('Ubah', 'edit', id, type),
            makeButton('Hapus', 'delete', id, type),
        );

        return wrapper;
    };

    const setStatusBadge = (value) => {
        const badge = document.createElement('span');
        const status = String(value ?? 'unknown').toLowerCase();
        badge.className = `media-sources-status is-${status}`;
        badge.textContent = status.replaceAll('_', ' ');

        return badge;
    };

    const previewUrl = (record) => {
        const value = record.external_url || record.file_path;
        if (!value) {
            return null;
        }

        try {
            return safeUrl(value).href;
        } catch {
            return null;
        }
    };

    const renderMedia = (record) => {
        const row = document.createElement('tr');
        const previewCell = cell('Pratinjau', 'media-sources-preview-cell');
        const preview = previewUrl(record);
        if (preview) {
            const image = document.createElement('img');
            image.src = preview;
            image.alt = record.alt_text || record.caption || 'Pratinjau media';
            image.loading = 'lazy';
            image.onerror = () => {
                image.remove();
                previewCell.textContent = 'Tidak dapat dimuat';
            };
            previewCell.append(image);
        } else {
            previewCell.textContent = 'Tidak tersedia';
        }

        const captionCell = cell('Keterangan');
        const caption = document.createElement('strong');
        const captionNote = document.createElement('small');
        caption.textContent = record.caption || record.alt_text || 'Tanpa keterangan';
        captionNote.textContent = record.creator ? `Pencipta: ${record.creator}` : record.file_path || record.external_url || '';
        captionCell.append(caption);
        if (captionNote.textContent) {
            captionCell.append(captionNote);
        }

        const entryCell = cell('Entri budaya');
        const entry = record.cultural_entry;
        entryCell.textContent = entry?.title ?? 'Entri tidak tersedia';
        if (entry?.is_deleted) {
            const deleted = document.createElement('small');
            deleted.textContent = 'Entri telah dihapus';
            entryCell.append(deleted);
        }

        const typeCell = cell('Tipe');
        typeCell.textContent = record.media_type || 'Tidak ditentukan';
        const statusCell = cell('Izin');
        statusCell.append(setStatusBadge(record.permission_status));
        if (record.is_cover) {
            const cover = document.createElement('small');
            cover.className = 'media-sources-cover-mark';
            cover.textContent = 'Media sampul';
            statusCell.append(cover);
        }

        const actionsCell = cell('Tindakan');
        actionsCell.append(makeActions(record.id, 'media'));
        row.append(previewCell, captionCell, entryCell, typeCell, statusCell, actionsCell);

        return row;
    };

    const renderSource = (record) => {
        const row = document.createElement('tr');
        const typeCell = cell('Jenis');
        typeCell.textContent = record.source_type || 'Tidak ditentukan';

        const titleCell = cell('Judul sumber');
        const title = document.createElement('strong');
        const year = document.createElement('small');
        title.textContent = record.title || 'Tanpa judul';
        year.textContent = record.publication_year ? `Tahun ${record.publication_year}` : '';
        titleCell.append(title);
        if (year.textContent) {
            titleCell.append(year);
        }

        const authorCell = cell('Penulis / penerbit');
        authorCell.textContent = [record.author, record.publisher].filter(Boolean).join(' · ') || 'Tidak dicantumkan';
        const urlCell = cell('URL');
        if (record.url) {
            try {
                const link = document.createElement('a');
                link.href = safeUrl(record.url).href;
                link.target = '_blank';
                link.rel = 'noopener noreferrer';
                link.textContent = record.url;
                urlCell.append(link);
            } catch {
                urlCell.textContent = record.url;
            }
        } else {
            urlCell.textContent = 'Tidak tersedia';
        }

        const entriesCell = cell('Terhubung dengan', 'media-sources-linked-entries');
        const entries = record.cultural_entries ?? [];
        entriesCell.textContent = entries.length
            ? entries.map((entry) => entry.title).filter(Boolean).join(', ')
            : 'Belum terhubung';

        const actionsCell = cell('Tindakan');
        actionsCell.append(makeActions(record.id, 'source'));
        row.append(typeCell, titleCell, authorCell, urlCell, entriesCell, actionsCell);

        return row;
    };

    const renderPagination = (container, meta, links, type) => {
        container.replaceChildren();
        if (!meta || meta.last_page <= 1) {
            return;
        }

        const summary = document.createElement('span');
        summary.className = 'media-sources-page-summary';
        summary.textContent = `${meta.from ?? 0}–${meta.to ?? 0} dari ${new Intl.NumberFormat('id-ID').format(meta.total ?? 0)}`;
        container.append(summary);
        [['first', '«'], ['prev', '‹'], ['next', '›'], ['last', '»']].forEach(([key, text]) => {
            const link = links?.[key];
            const control = document.createElement(link ? 'button' : 'span');
            control.className = 'media-sources-page-link';
            control.textContent = text;
            control.setAttribute('aria-label', { first: 'Halaman pertama', prev: 'Halaman sebelumnya', next: 'Halaman berikutnya', last: 'Halaman terakhir' }[key]);
            if (link) {
                control.type = 'button';
                control.dataset.pageUrl = link;
                control.dataset.pageType = type;
            } else {
                control.setAttribute('aria-disabled', 'true');
            }
            container.append(control);
        });
        const current = document.createElement('span');
        current.className = 'media-sources-page-current';
        current.textContent = `Halaman ${meta.current_page} / ${meta.last_page}`;
        container.append(current);
    };

    const loadList = async (type, pageUrl = null) => {
        const media = type === 'media';
        const filters = state[media ? 'media' : 'sources'];
        const indexUrl = media ? config.dataset.mediaIndexUrl : config.dataset.sourceIndexUrl;
        const tbody = document.querySelector(media ? '[data-media-rows]' : '[data-source-rows]');
        const total = document.querySelector(media ? '[data-media-total]' : '[data-source-total]');
        const pagination = document.querySelector(media ? '[data-media-pagination]' : '[data-source-pagination]');
        const columns = media ? 6 : 6;

        try {
            const url = pageUrl ? safeApiUrl(pageUrl) : safeApiUrl(indexUrl);
            if (!pageUrl) {
                Object.entries(filters).forEach(([key, value]) => {
                    if (value) {
                        url.searchParams.set(key, value);
                    }
                });
                url.searchParams.set('per_page', String(pageSize));
            }
            const result = await request(url);
            const records = Array.isArray(result.data) ? result.data : [];
            total.textContent = new Intl.NumberFormat('id-ID').format(result.meta?.total ?? records.length);
            if (records.length) {
                tbody.replaceChildren(...records.map(media ? renderMedia : renderSource));
            } else {
                setEmpty(tbody, columns, 'Belum ada data untuk ditampilkan.', 'Ubah pencarian atau filter, atau tambahkan data baru.');
            }
            renderPagination(pagination, result.meta, result.links, type);
            state[media ? 'mediaPage' : 'sourcePage'] = result.meta?.current_page ?? 1;
        } catch (error) {
            setEmpty(tbody, columns, 'Data belum dapat dimuat.', 'Periksa koneksi atau hak akses API, lalu coba lagi.');
            showFeedback(error.message, true);
        }
    };

    const fillEntryOptions = async () => {
        const [entriesResponse, regionsResponse, categoriesResponse] = await Promise.all([
            request(apiUrl(config.dataset.entryIndexUrl)),
            request(apiUrl(config.dataset.regionIndexUrl)),
            request(apiUrl(config.dataset.categoryIndexUrl)),
        ]);
        const entries = Array.isArray(entriesResponse.data) ? entriesResponse.data : [];
        entryOptions.splice(0, entryOptions.length, ...entries.filter((entry) => !entry.deleted_at));
        document.querySelectorAll('[data-media-entry-filter], [data-source-entry-filter], [data-media-entry-select], [data-source-entry-select]').forEach((select) => {
            const includeEmpty = select.matches('[data-media-entry-filter], [data-source-entry-filter], [data-media-entry-select]');
            select.replaceChildren();
            if (includeEmpty) {
                const placeholder = document.createElement('option');
                placeholder.value = '';
                placeholder.textContent = select.matches('[data-media-entry-select]') ? 'Pilih entri budaya' : 'Semua entri budaya';
                select.append(placeholder);
            }
            entryOptions.forEach((entry) => {
                const option = document.createElement('option');
                option.value = String(entry.id);
                option.textContent = entry.title ?? `Entri #${entry.id}`;
                select.append(option);
            });
        });
        if (mediaEntrySelect) {
            const customOption = document.createElement('option');
            customOption.value = CUSTOM_ENTRY;
            customOption.textContent = '+ Tambah Entri Budaya Baru (Custom)';
            customOption.dataset.customEntry = 'true';
            mediaEntrySelect.append(customOption);
        }

        const regionSelect = document.querySelector('[data-custom-region-select]');
        const categorySelect = document.querySelector('[data-custom-category-select]');
        (regionsResponse.data ?? []).forEach((region) => {
            const option = document.createElement('option');
            option.value = String(region.id);
            option.textContent = region.name;
            regionSelect.append(option);
        });
        (categoriesResponse.data ?? []).forEach((category) => {
            const option = document.createElement('option');
            option.value = String(category.id);
            option.textContent = category.name;
            categorySelect.append(option);
        });
    };

    const showFormError = (kind, message = '') => {
        const element = document.querySelector(`[data-${kind}-form-error]`);
        if (element) {
            element.textContent = message;
            element.hidden = !message;
        }
    };

    const appendDetail = (container, label, value, href = null) => {
        const item = document.createElement('div');
        item.className = 'media-sources-detail-item';
        const term = document.createElement('span');
        const description = document.createElement('strong');
        term.textContent = label;
        if (href) {
            try {
                const link = document.createElement('a');
                link.href = safeUrl(href).href;
                link.target = '_blank';
                link.rel = 'noopener noreferrer';
                link.textContent = value || href;
                description.append(link);
            } catch {
                description.textContent = value || href;
            }
        } else {
            description.textContent = value === null || value === undefined || value === '' ? '—' : String(value);
        }
        item.append(term, description);
        container.append(item);
    };

    const showMediaDetail = (record) => {
        const container = document.querySelector('[data-media-detail]');
        container.replaceChildren();
        const preview = previewUrl(record);
        if (preview) {
            const image = document.createElement('img');
            image.className = 'media-sources-detail-image';
            image.src = preview;
            image.alt = record.alt_text || record.caption || 'Pratinjau media';
            container.append(image);
        }
        [
            ['Keterangan', record.caption],
            ['Teks alternatif', record.alt_text],
            ['Entri budaya', record.cultural_entry?.title],
            ['Tipe media', record.media_type],
            ['Path berkas', record.file_path],
            ['URL eksternal', record.external_url],
            ['Pencipta', record.creator],
            ['Pemegang hak cipta', record.copyright_holder],
            ['Lisensi', record.license],
            ['Status izin', record.permission_status],
            ['Catatan izin', record.permission_notes],
            ['Media sampul', record.is_cover ? 'Ya' : 'Tidak'],
        ].forEach(([label, value]) => appendDetail(container, label, value, label === 'URL eksternal' ? value : null));
        document.querySelector('#media-detail-title').textContent = record.caption || `Detail media #${record.id}`;
        document.querySelector('#media-detail-dialog').showModal();
    };

    const showSourceDetail = (record) => {
        const container = document.querySelector('[data-source-detail]');
        container.replaceChildren();
        [
            ['Jenis sumber', record.source_type],
            ['Judul', record.title],
            ['Penulis', record.author],
            ['Penerbit', record.publisher],
            ['Tahun terbit', record.publication_year],
            ['URL', record.url],
            ['Identifier', record.identifier],
            ['Tanggal diakses', record.accessed_at],
            ['Catatan', record.notes],
            ['Entri terhubung', (record.cultural_entries ?? []).map((entry) => entry.title).join(', ') || 'Belum terhubung'],
        ].forEach(([label, value]) => appendDetail(container, label, value, label === 'URL' ? value : null));
        document.querySelector('#source-detail-title').textContent = record.title || `Detail sumber #${record.id}`;
        document.querySelector('#source-detail-dialog').showModal();
    };

    const fillForm = (form, record, kind) => {
        form.reset();
        form.dataset.mode = record ? 'edit' : 'create';
        form.dataset.recordId = record?.id ?? '';
        form.dataset.customEntry = 'false';
        showFormError(kind);
        form.querySelectorAll('[name]').forEach((input) => {
            const name = input.name;
            if (input.matches('[data-source-entry-select]')) {
                const ids = new Set((record?.cultural_entries ?? []).map((entry) => String(entry.id)));
                Array.from(input.options).forEach((option) => { option.selected = ids.has(option.value); });
            } else if (input.type === 'checkbox') {
                input.checked = Boolean(record?.[name]);
            } else {
                const value = record?.[name] ?? '';
                input.value = input.type === 'date' && value ? String(value).slice(0, 10) : value;
            }
        });
        if (kind === 'media') {
            const entrySelect = form.querySelector('[data-media-entry-select]');
            entrySelect.value = record ? String(record.cultural_entry_id) : '';
            entrySelect.dataset.mode = record ? 'edit' : 'create';
            entrySelect.disabled = Boolean(record);
            entrySelect.querySelector(`[value="${CUSTOM_ENTRY}"]`).disabled = Boolean(record);
            document.querySelectorAll('[data-media-create-only]').forEach((field) => {
                field.hidden = Boolean(record);
            });
            setCustomEntryMode(false);
            document.querySelector('#media-form-title').textContent = record ? 'Ubah media' : 'Tambah media';
            document.querySelector('[data-media-save]').textContent = record ? 'Simpan perubahan' : 'Simpan media';
            document.querySelector('#media-form-dialog').showModal();
        } else {
            document.querySelector('#source-form-title').textContent = record ? 'Ubah sumber' : 'Tambah sumber';
            document.querySelector('[data-source-save]').textContent = record ? 'Simpan perubahan' : 'Simpan sumber';
            document.querySelector('#source-form-dialog').showModal();
        }
    };

    const mediaPayload = (form, mode, includeEntryId = true) => {
        const data = new FormData(form);
        const value = (key) => data.get(key)?.toString().trim() || null;
        const payload = {
            media_type: value('media_type'),
            file_path: value('file_path'),
            external_url: value('external_url'),
            caption: value('caption'),
            alt_text: value('alt_text'),
            creator: value('creator'),
            copyright_holder: value('copyright_holder'),
            license: value('license'),
            is_cover: data.has('is_cover'),
            sort_order: value('sort_order') === null ? null : Number(value('sort_order')),
        };
        if (mode === 'create') {
            payload.permission_notes = value('permission_notes');
        }
        if (includeEntryId) {
            payload.cultural_entry_id = Number(form.querySelector('[data-media-entry-select]').value);
        }

        return payload;
    };

    const customEntryPayload = (form) => {
        const value = (name) => form.querySelector(`[name="entry[${name}]"]`)?.value.trim() || null;
        const keywords = String(value('keywords') ?? '')
            .split(/\r?\n/)
            .map((keyword) => keyword.trim())
            .filter(Boolean);

        return {
            title: value('title'),
            slug: value('slug'),
            local_name: value('local_name'),
            origin_region_id: value('origin_region_id'),
            category_ids: Array.from(
                form.querySelector('[data-custom-category-select]').selectedOptions,
                (option) => Number(option.value)
            ),
            short_description: value('short_description'),
            description: value('description'),
            history: value('history'),
            meaning_and_function: value('meaning_and_function'),
            techniques: value('techniques'),
            materials: value('materials'),
            community_context: value('community_context'),
            keywords,
        };
    };

    const setCustomEntryMode = (enabled) => {
        if (!customEntryPanel || !mediaEntrySelect) {
            return;
        }

        customEntryPanel.hidden = !enabled;
        mediaEntrySelect.required = true;
        mediaEntrySelect.querySelector(`[value="${CUSTOM_ENTRY}"]`).disabled = !enabled && mediaEntrySelect.dataset.mode === 'edit';
        mediaEntrySelect.dataset.customEntry = String(enabled);
        const form = document.querySelector('[data-media-form]');
        if (form) {
            form.dataset.customEntry = String(enabled);
        }
        customEntryPanel.querySelectorAll('[data-custom-entry-field]').forEach((field) => {
            field.disabled = !enabled;
            field.required = enabled && ['entry[title]', 'entry[slug]'].includes(field.name);
        });
    };

    const sourcePayload = (form) => {
        const data = new FormData(form);
        const value = (key) => data.get(key)?.toString().trim() || null;

        return {
            source_type: value('source_type'),
            title: value('title'),
            author: value('author'),
            publisher: value('publisher'),
            publication_year: value('publication_year') ? Number(value('publication_year')) : null,
            url: value('url'),
            identifier: value('identifier'),
            accessed_at: value('accessed_at'),
            notes: value('notes'),
            cultural_entry_ids: data.getAll('cultural_entry_ids').map(Number),
        };
    };

    const closeDialogs = (kind) => {
        document.querySelectorAll(`#${kind}-form-dialog, #${kind}-detail-dialog`).forEach((dialog) => dialog?.close());
    };

    document.querySelectorAll('[data-media-close]').forEach((button) => button.addEventListener('click', () => closeDialogs('media')));
    document.querySelectorAll('[data-source-close]').forEach((button) => button.addEventListener('click', () => closeDialogs('source')));

    document.querySelectorAll('[data-media-tab]').forEach((tab) => tab.addEventListener('click', () => {
        document.querySelectorAll('[data-media-tab]').forEach((item) => {
            const active = item === tab;
            item.classList.toggle('is-active', active);
            item.setAttribute('aria-selected', String(active));
        });
        document.querySelectorAll('[data-media-panel]').forEach((panel) => { panel.hidden = panel.dataset.mediaPanel !== tab.dataset.mediaTab; });
    }));

    const bindFilters = (form, kind) => {
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            state[kind] = Object.fromEntries(new FormData(form).entries());
            loadList(kind === 'media' ? 'media' : 'sources');
        });
        form.addEventListener('reset', () => {
            window.setTimeout(() => {
                state[kind] = Object.fromEntries(new FormData(form).entries());
                loadList(kind === 'media' ? 'media' : 'sources');
            });
        });
    };
    const mediaFilterForm = document.querySelector('[data-media-filter-form]');
    const sourceFilterForm = document.querySelector('[data-source-filter-form]');
    if (mediaFilterForm) bindFilters(mediaFilterForm, 'media');
    if (sourceFilterForm) bindFilters(sourceFilterForm, 'sources');

    document.querySelector('[data-media-create]')?.addEventListener('click', () => fillForm(document.querySelector('[data-media-form]'), null, 'media'));
    document.querySelector('[data-source-create]')?.addEventListener('click', () => fillForm(document.querySelector('[data-source-form]'), null, 'source'));
    mediaEntrySelect?.addEventListener('change', () => {
        setCustomEntryMode(mediaEntrySelect.value === CUSTOM_ENTRY);
        showFormError('media');
    });

    document.addEventListener('click', async (event) => {
        const pageControl = event.target.closest('[data-page-url]');
        if (pageControl) {
            try {
                await loadList(pageControl.dataset.pageType, safeApiUrl(pageControl.dataset.pageUrl));
            } catch (error) {
                showFeedback(error.message, true);
            }
            return;
        }

        const action = event.target.closest('[data-record-action]');
        if (!action) {
            return;
        }
        const { recordType, recordId } = action.dataset;
        const isMedia = recordType === 'media';
        const endpoint = isMedia ? config.dataset.mediaShowTemplate : config.dataset.sourceShowTemplate;
        try {
            if (action.dataset.recordAction === 'delete') {
                const warning = isMedia
                    ? 'Hapus media ini? Metadata media akan dihapus dari arsip. Berkas fisik tidak ikut dihapus.'
                    : 'Hapus sumber ini? Sumber yang masih terhubung dengan entri budaya tidak dapat dihapus.';
                if (!window.confirm(warning)) {
                    return;
                }
                const deleteUrl = isMedia ? config.dataset.mediaDeleteTemplate : config.dataset.sourceDeleteTemplate;
                const result = await request(apiUrl(deleteUrl, recordId), { method: 'DELETE', body: {} });
                showFeedback(result.message || 'Data berhasil dihapus.');
                await loadList(recordType);
                return;
            }

            const result = await request(apiUrl(endpoint, recordId));
            const record = result.data;
            if (action.dataset.recordAction === 'detail') {
                if (isMedia) showMediaDetail(record);
                else showSourceDetail(record);
                return;
            }
            fillForm(document.querySelector(isMedia ? '[data-media-form]' : '[data-source-form]'), record, recordType);
        } catch (error) {
            showFeedback(error.message, true);
        }
    });

    const bindForm = (form, kind) => {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const mode = form.dataset.mode || 'create';
            const isMedia = kind === 'media';
            const isCustomEntry = isMedia && mode === 'create' && form.dataset.customEntry === 'true';
            const template = isCustomEntry
                ? config.dataset.entryWithMediaUrl
                : mode === 'edit'
                    ? (isMedia ? config.dataset.mediaUpdateTemplate : config.dataset.sourceUpdateTemplate)
                    : (isMedia ? config.dataset.mediaStoreUrl : config.dataset.sourceStoreUrl);
            const url = mode === 'edit' && !isCustomEntry ? apiUrl(template, form.dataset.recordId) : apiUrl(template);
            const save = form.querySelector('[type="submit"]');
            save.disabled = true;
            showFormError(kind);
            try {
                const result = await request(url, {
                    method: mode === 'edit' ? 'PUT' : 'POST',
                    body: isCustomEntry
                        ? {
                            entry: customEntryPayload(form),
                            media: mediaPayload(form, mode, false),
                        }
                        : isMedia
                            ? mediaPayload(form, mode)
                            : sourcePayload(form),
                });
                closeDialogs(kind);
                const customEntryTitle = result.data?.cultural_entry?.title;
                showFeedback(isCustomEntry && customEntryTitle
                    ? `${result.message || 'Entri dan media berhasil dibuat.'} Entri baru: ${customEntryTitle} (draft, pending).`
                    : result.message || (mode === 'edit' ? 'Perubahan berhasil disimpan.' : 'Data berhasil ditambahkan.'));
                await loadList(isMedia ? 'media' : 'sources');
            } catch (error) {
                showFormError(kind, error.message);
            } finally {
                save.disabled = false;
            }
        });
    };
    const mediaForm = document.querySelector('[data-media-form]');
    const sourceForm = document.querySelector('[data-source-form]');
    if (mediaForm) bindForm(mediaForm, 'media');
    if (sourceForm) bindForm(sourceForm, 'source');

    document.querySelectorAll('.media-sources-dialog').forEach((dialog) => dialog.addEventListener('click', (event) => {
        if (event.target === dialog) {
            dialog.close();
        }
    }));

    const startup = async () => {
        const jobs = [];
        if (mediaFilterForm || sourceFilterForm) {
            try {
                await fillEntryOptions();
            } catch (error) {
                showFeedback(`Daftar entri budaya untuk relasi belum dapat dimuat: ${error.message}`, true);
                document.querySelectorAll('[data-media-entry-select], [data-source-entry-select]').forEach((select) => { select.disabled = true; });
                document.querySelectorAll('[data-media-create], [data-source-create]').forEach((button) => { button.disabled = true; });
            }
        }
        if (mediaFilterForm) jobs.push(loadList('media'));
        if (sourceFilterForm) jobs.push(loadList('sources'));
        await Promise.all(jobs);
    };
    startup();
});
