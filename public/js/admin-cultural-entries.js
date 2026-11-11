document.addEventListener('DOMContentLoaded', () => {
    const config = document.querySelector('[data-cultural-entry-config]');
    const filterForm = document.querySelector('[data-entry-filter-form]');
    const rows = document.querySelector('[data-entry-rows]');
    const total = document.querySelector('[data-entry-total]');
    const pagination = document.querySelector('[data-entry-pagination]');
    const feedback = document.querySelector('[data-entry-feedback]');
    const formDialog = document.querySelector('#cultural-entry-form-dialog');
    const detailDialog = document.querySelector('#cultural-entry-detail-dialog');
    const form = document.querySelector('[data-entry-form]');
    const detail = document.querySelector('[data-entry-detail]');
    const detailTitle = document.querySelector('#cultural-entry-detail-title');
    const editDetailButton = document.querySelector('[data-entry-detail-edit]');
    const saveButton = document.querySelector('[data-entry-save]');
    const regionFilter = document.querySelector('[data-region-filter]');
    const categoryFilter = document.querySelector('[data-category-filter]');
    const regionSelect = document.querySelector('[data-region-select]');
    const categorySelect = document.querySelector('[data-category-select]');
    let activePageUrl = null;
    let currentEntry = null;
    let regions = [];
    let categories = [];
    let isSaving = false;

    if (!config || !filterForm || !rows || !form || !formDialog || !detailDialog) {
        return;
    }

    const showFeedback = (message, isError = false) => {
        feedback.textContent = message;
        feedback.hidden = false;
        feedback.classList.toggle('is-error', isError);
        feedback.classList.toggle('is-success', !isError);
    };

    const clearFeedback = () => {
        feedback.hidden = true;
        feedback.textContent = '';
        feedback.classList.remove('is-error', 'is-success');
    };

    document.querySelector('[data-bulk-publication-form]')?.addEventListener('submit', (event) => {
        if (!window.confirm('Publikasikan semua entri aktif yang memenuhi syarat sekarang? Entri yang belum memenuhi syarat akan dilewati.')) {
            event.preventDefault();
        }
    });

    const readJson = async (response) => {
        const contentType = response.headers.get('content-type') ?? '';
        if (!contentType.includes('application/json')) {
            throw new Error(response.redirected
                ? 'Sesi pengelola berakhir. Silakan masuk kembali.'
                : 'Server tidak mengembalikan respons JSON yang diharapkan.');
        }

        const body = await response.json();
        if (!response.ok) {
            const validationErrors = Object.values(body.errors ?? {}).flat();
            throw new Error(validationErrors.length
                ? validationErrors.join(' ')
                : body.message ?? `Permintaan gagal (${response.status}).`);
        }

        return body;
    };

    const safeApiUrl = (value) => {
        const url = new URL(value, window.location.origin);
        if (url.origin !== window.location.origin) {
            throw new Error('URL API mengarah ke host yang tidak diizinkan.');
        }

        return url;
    };

    const addOptions = (select, records, emptyLabel = null) => {
        if (!select) {
            return;
        }

        select.replaceChildren();
        if (emptyLabel !== null) {
            const empty = document.createElement('option');
            empty.value = '';
            empty.textContent = emptyLabel;
            select.append(empty);
        }

        records.forEach((record) => {
            const option = document.createElement('option');
            option.value = String(record.id);
            option.textContent = record.name;
            select.append(option);
        });
    };

    const loadReferenceOptions = async () => {
        const [regionsResponse, categoriesResponse] = await Promise.all([
            fetch(safeApiUrl(config.dataset.regionsUrl), {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            }),
            fetch(safeApiUrl(config.dataset.categoriesUrl), {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            }),
        ]);
        const [regionsData, categoriesData] = await Promise.all([
            readJson(regionsResponse),
            readJson(categoriesResponse),
        ]);

        regions = regionsData.data ?? [];
        categories = categoriesData.data ?? [];
        addOptions(regionFilter, regions, 'Semua wilayah');
        addOptions(categoryFilter, categories, 'Semua kategori');
        addOptions(regionSelect, regions, 'Belum ditentukan');
        addOptions(categorySelect, categories);
    };

    const createCell = (label, className = '') => {
        const cell = document.createElement('td');
        cell.dataset.label = label;
        if (className) {
            cell.className = className;
        }

        return cell;
    };

    const createActionButton = (label, action, id, className = '') => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = `cultural-entry-action ${className}`.trim();
        button.dataset.entryAction = action;
        button.dataset.entryId = String(id);
        button.textContent = label;

        return button;
    };

    const createPublicationForm = (entry) => {
        const form = document.createElement('form');
        form.className = 'cultural-entry-publication-form';
        form.method = 'POST';
        form.action = safeApiUrl(config.dataset.publicationTemplate.replace('__id__', encodeURIComponent(entry.id))).href;

        const token = document.createElement('input');
        token.type = 'hidden';
        token.name = '_token';
        token.value = config.dataset.csrfToken ?? '';

        const action = document.createElement('input');
        action.type = 'hidden';
        action.name = 'action';
        action.value = entry.publication_status === 'published' ? 'draft' : 'publish';

        const returnTo = document.createElement('input');
        returnTo.type = 'hidden';
        returnTo.name = 'return_to';
        returnTo.value = 'manage';

        const button = document.createElement('button');
        button.type = 'submit';
        button.className = `cultural-entry-action ${entry.publication_status === 'published' ? 'is-danger' : 'is-publish'}`;
        button.textContent = entry.publication_status === 'published' ? 'Tarik publikasi' : 'Publikasikan';
        button.addEventListener('click', (event) => {
            const message = entry.publication_status === 'published'
                ? `Tarik "${entry.title}" dari Arsip Budaya?`
                : `Setujui publikasi "${entry.title}" sekarang?`;
            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });

        form.append(token, action, returnTo, button);

        return form;
    };

    const renderEntry = (entry) => {
        const row = document.createElement('tr');
        if (entry.deleted_at) {
            row.classList.add('is-deleted');
        }

        const nameCell = createCell('Nama budaya');
        const name = document.createElement('strong');
        name.className = 'cultural-entry-record-name';
        name.textContent = entry.title ?? 'Tanpa judul';
        const slug = document.createElement('small');
        slug.className = 'cultural-entry-record-slug';
        slug.textContent = entry.slug ?? '';
        nameCell.append(name, slug);
        if (entry.deleted_at) {
            const deleted = document.createElement('span');
            deleted.className = 'cultural-entry-deleted';
            deleted.textContent = 'Soft deleted';
            nameCell.append(deleted);
        }

        const regionCell = createCell('Wilayah asal');
        regionCell.textContent = entry.region?.name ?? 'Belum ditentukan';

        const categoryCell = createCell('Kategori');
        const categoryList = document.createElement('div');
        categoryList.className = 'cultural-entry-tags';
        (entry.categories ?? []).forEach((category) => {
            const tag = document.createElement('span');
            tag.textContent = category.name;
            categoryList.append(tag);
        });
        if (!entry.categories?.length) {
            categoryList.textContent = 'Belum dikategorikan';
        }
        categoryCell.append(categoryList);

        const verificationCell = createCell('Verifikasi');
        verificationCell.append(createStatus(entry.verification_status, 'verification'));
        const publicationCell = createCell('Publikasi');
        publicationCell.append(createStatus(entry.publication_status, 'publication'));

        const mediaCell = createCell('Media');
        mediaCell.textContent = new Intl.NumberFormat('id-ID').format(entry.media_count ?? 0);

        const actionCell = createCell('Tindakan');
        const actions = document.createElement('div');
        actions.className = 'cultural-entry-row-actions';
        actions.append(createActionButton('Detail', 'detail', entry.id));
        if (!entry.deleted_at) {
            actions.append(createPublicationForm(entry));
            actions.append(
                createActionButton('Ubah', 'edit', entry.id),
                createActionButton('Hapus', 'delete', entry.id, 'is-danger'),
            );
        }
        actionCell.append(actions);
        row.append(nameCell, regionCell, categoryCell, verificationCell, publicationCell, mediaCell, actionCell);

        return row;
    };

    const createStatus = (value, type) => {
        const status = document.createElement('span');
        const normalized = String(value ?? '—').toLowerCase();
        status.className = `cultural-entry-status is-${type}-${normalized}`;
        status.textContent = normalized.replaceAll('_', ' ');

        return status;
    };

    const renderEmpty = (message, note = '') => {
        const row = document.createElement('tr');
        const cell = document.createElement('td');
        const title = document.createElement('strong');
        title.textContent = message;
        cell.className = 'cultural-entry-empty';
        cell.colSpan = 7;
        cell.append(title);
        if (note) {
            const small = document.createElement('small');
            small.textContent = note;
            cell.append(small);
        }
        row.append(cell);
        rows.replaceChildren(row);
    };

    const renderPagination = (meta, links) => {
        pagination.replaceChildren();
        if (!meta || meta.last_page <= 1) {
            return;
        }

        const summary = document.createElement('span');
        summary.className = 'cultural-entry-page-summary';
        summary.textContent = `${new Intl.NumberFormat('id-ID').format(meta.from ?? 0)}–${new Intl.NumberFormat('id-ID').format(meta.to ?? 0)} dari ${new Intl.NumberFormat('id-ID').format(meta.total ?? 0)} entri`;
        pagination.append(summary);

        [
            ['first', '«', 'Halaman pertama'],
            ['prev', '‹', 'Halaman sebelumnya'],
            ['next', '›', 'Halaman berikutnya'],
            ['last', '»', 'Halaman terakhir'],
        ].forEach(([key, label, ariaLabel]) => {
            const url = links?.[key];
            const control = document.createElement(url ? 'a' : 'span');
            control.className = 'cultural-entry-page-link';
            control.textContent = label;
            control.setAttribute('aria-label', ariaLabel);
            if (url) {
                control.href = url;
                control.dataset.pageUrl = url;
            } else {
                control.setAttribute('aria-disabled', 'true');
            }
            pagination.append(control);
        });

        const current = document.createElement('span');
        current.className = 'cultural-entry-page-current';
        current.textContent = `Halaman ${meta.current_page} / ${meta.last_page}`;
        pagination.append(current);
    };

    const filtersUrl = () => {
        const url = safeApiUrl(config.dataset.indexUrl);
        const params = new FormData(filterForm);
        params.forEach((value, key) => {
            if (typeof value === 'string' && value.trim() !== '') {
                url.searchParams.set(key, value.trim());
            }
        });

        return url;
    };

    const loadEntries = async (url = null) => {
        activePageUrl = url ? safeApiUrl(url) : filtersUrl();
        rows.setAttribute('aria-busy', 'true');
        try {
            const response = await fetch(activePageUrl, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            const body = await readJson(response);
            const entries = body.data ?? [];
            total.textContent = new Intl.NumberFormat('id-ID').format(body.meta?.total ?? 0);
            if (entries.length === 0) {
                renderEmpty('Tidak ada entri yang sesuai.', 'Coba ubah pencarian atau filter yang dipilih.');
            } else {
                rows.replaceChildren(...entries.map(renderEntry));
            }
            renderPagination(body.meta, body.links);
        } catch (error) {
            renderEmpty('Data budaya tidak dapat dimuat.', error.message);
            renderPagination(null, null);
            showFeedback(error.message, true);
        } finally {
            rows.removeAttribute('aria-busy');
        }
    };

    const detailField = (container, label, value) => {
        const wrapper = document.createElement('section');
        wrapper.className = 'cultural-entry-detail-field';
        const heading = document.createElement('h3');
        heading.textContent = label;
        const content = document.createElement('p');
        content.textContent = value === null || value === undefined || value === ''
            ? 'Belum diisi'
            : String(value);
        wrapper.append(heading, content);
        container.append(wrapper);
    };

    const renderDetail = (entry) => {
        detail.replaceChildren();
        detailTitle.textContent = entry.title ?? 'Detail data budaya';
        detailField(detail, 'Slug', entry.slug);
        detailField(detail, 'Nama lokal', entry.local_name);
        detailField(detail, 'Wilayah asal', entry.region?.name);
        detailField(detail, 'Kategori', (entry.categories ?? []).map((category) => category.name).join(', '));
        detailField(detail, 'Deskripsi singkat', entry.short_description);
        detailField(detail, 'Deskripsi lengkap', entry.description);
        detailField(detail, 'Sejarah', entry.history);
        detailField(detail, 'Makna dan fungsi', entry.meaning_and_function);
        detailField(detail, 'Teknik atau proses', entry.techniques);
        detailField(detail, 'Bahan', entry.materials);
        detailField(detail, 'Konteks komunitas', entry.community_context);
        detailField(detail, 'Kata kunci', (entry.keywords ?? []).join(', '));
        detailField(detail, 'Status verifikasi', entry.verification_status);
        detailField(detail, 'Status publikasi', entry.publication_status);
        detailField(detail, 'Jumlah media', entry.media_count ?? 0);
        detailField(detail, 'Status arsip', entry.deleted_at ? 'Soft deleted' : 'Aktif');
        editDetailButton.hidden = Boolean(entry.deleted_at);
        editDetailButton.dataset.entryId = String(entry.id);
        detailDialog.showModal();
    };

    const getEntry = async (id) => {
        const url = safeApiUrl(config.dataset.showTemplate.replace('__id__', encodeURIComponent(id)));
        const response = await fetch(url, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        const body = await readJson(response);

        return body.data;
    };

    const setFormValues = (entry = null) => {
        form.reset();
        form.dataset.entryId = entry ? String(entry.id) : '';
        form.querySelector('[name="title"]').value = entry?.title ?? '';
        form.querySelector('[name="slug"]').value = entry?.slug ?? '';
        form.querySelector('[name="local_name"]').value = entry?.local_name ?? '';
        form.querySelector('[name="origin_region_id"]').value = entry?.origin_region_id ?? '';
        form.querySelector('[name="short_description"]').value = entry?.short_description ?? '';
        form.querySelector('[name="description"]').value = entry?.description ?? '';
        form.querySelector('[name="history"]').value = entry?.history ?? '';
        form.querySelector('[name="meaning_and_function"]').value = entry?.meaning_and_function ?? '';
        form.querySelector('[name="techniques"]').value = entry?.techniques ?? '';
        form.querySelector('[name="materials"]').value = entry?.materials ?? '';
        form.querySelector('[name="community_context"]').value = entry?.community_context ?? '';
        form.querySelector('[name="keywords"]').value = (entry?.keywords ?? []).join('\n');
        const selectedCategoryIds = new Set((entry?.categories ?? []).map((category) => String(category.id)));
        Array.from(categorySelect.options).forEach((option) => {
            option.selected = selectedCategoryIds.has(option.value);
        });
        document.querySelector('#cultural-entry-form-title').textContent = entry
            ? 'Ubah data budaya'
            : 'Tambah data budaya';
        saveButton.textContent = entry ? 'Simpan perubahan' : 'Simpan entri';
        currentEntry = entry;
    };

    const formPayload = () => {
        const data = new FormData(form);
        const keywords = String(data.get('keywords') ?? '')
            .split(/\r?\n/)
            .map((keyword) => keyword.trim())
            .filter(Boolean);

        return {
            title: data.get('title'),
            slug: data.get('slug'),
            local_name: data.get('local_name') || null,
            origin_region_id: data.get('origin_region_id') || null,
            category_ids: Array.from(categorySelect.selectedOptions, (option) => Number(option.value)),
            short_description: data.get('short_description') || null,
            description: data.get('description') || null,
            history: data.get('history') || null,
            meaning_and_function: data.get('meaning_and_function') || null,
            techniques: data.get('techniques') || null,
            materials: data.get('materials') || null,
            community_context: data.get('community_context') || null,
            keywords,
        };
    };

    document.querySelector('[data-entry-create]')?.addEventListener('click', () => {
        clearFeedback();
        setFormValues();
        formDialog.showModal();
    });

    document.addEventListener('click', async (event) => {
        const closeButton = event.target.closest('[data-entry-close]');
        if (closeButton) {
            closeButton.closest('dialog')?.close();
            return;
        }

        const pageLink = event.target.closest('[data-page-url]');
        if (pageLink) {
            event.preventDefault();
            await loadEntries(pageLink.dataset.pageUrl);
            return;
        }

        const action = event.target.closest('[data-entry-action]');
        if (action) {
            const id = action.dataset.entryId;
            try {
                clearFeedback();
                if (action.dataset.entryAction === 'delete') {
                    if (!window.confirm('Hapus entri budaya ini secara lunak? Data tetap tersimpan dan tidak tampil di arsip publik.')) {
                        return;
                    }

                    const response = await fetch(safeApiUrl(config.dataset.deleteTemplate.replace('__id__', encodeURIComponent(id))), {
                        method: 'DELETE',
                        headers: {
                            Accept: 'application/json',
                            'X-CSRF-TOKEN': config.dataset.csrfToken,
                        },
                        credentials: 'same-origin',
                    });
                    const body = await readJson(response);
                    showFeedback(body.message ?? 'Entri berhasil dihapus secara lunak.');
                    await loadEntries();
                    return;
                }

                const entry = await getEntry(id);
                if (action.dataset.entryAction === 'detail') {
                    renderDetail(entry);
                    return;
                }

                setFormValues(entry);
                formDialog.showModal();
            } catch (error) {
                showFeedback(error.message, true);
            }
        }
    });

    editDetailButton?.addEventListener('click', async () => {
        try {
            const entry = await getEntry(editDetailButton.dataset.entryId);
            detailDialog.close();
            setFormValues(entry);
            formDialog.showModal();
        } catch (error) {
            showFeedback(error.message, true);
        }
    });

    filterForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        clearFeedback();
        await loadEntries();
    });

    filterForm.addEventListener('reset', () => {
        window.setTimeout(() => {
            clearFeedback();
            loadEntries();
        }, 0);
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (isSaving) {
            return;
        }

        isSaving = true;
        saveButton.disabled = true;
        const entryId = form.dataset.entryId;
        const endpoint = entryId
            ? config.dataset.updateTemplate.replace('__id__', encodeURIComponent(entryId))
            : config.dataset.storeUrl;
        try {
            const response = await fetch(safeApiUrl(endpoint), {
                method: entryId ? 'PUT' : 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': config.dataset.csrfToken,
                },
                credentials: 'same-origin',
                body: JSON.stringify(formPayload()),
            });
            const body = await readJson(response);
            formDialog.close();
            showFeedback(body.message ?? 'Data budaya berhasil disimpan.');
            await loadEntries();
        } catch (error) {
            showFeedback(error.message, true);
        } finally {
            isSaving = false;
            saveButton.disabled = false;
        }
    });

    loadReferenceOptions()
        .then(() => loadEntries())
        .catch((error) => {
            renderEmpty('Data referensi tidak dapat dimuat.', error.message);
            showFeedback(error.message, true);
        });
});
