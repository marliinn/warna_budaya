document.addEventListener('DOMContentLoaded', () => {
    const config = document.querySelector('[data-taxonomy-config]');
    const filterForm = document.querySelector('[data-filter-form]');
    const recordBody = document.querySelector('.admin-taxonomy-table tbody');
    const totalLabel = document.querySelector('[data-record-total]');
    const pagination = document.querySelector('[data-pagination]');
    const feedback = document.querySelector('[data-feedback]');
    const kind = config?.dataset.kind;
    const recordLabel = kind === 'region' ? 'wilayah' : 'kategori';
    const icon = kind === 'region' ? '⌖' : '◇';
    let activePageUrl = null;

    const showFeedback = (message, isError = false) => {
        if (!feedback) {
            return;
        }

        feedback.textContent = message;
        feedback.hidden = false;
        feedback.classList.toggle('is-error', isError);
        feedback.classList.toggle('is-success', !isError);
    };

    const readJson = async (response) => {
        if (!response.headers.get('content-type')?.includes('application/json')) {
            throw new Error(response.redirected
                ? 'Sesi admin berakhir. Silakan masuk kembali.'
                : 'Server tidak mengembalikan respons JSON yang diharapkan.');
        }

        return response.json();
    };

    const renderEmpty = (message, detail = 'Coba ubah kata pencarian atau filter status.') => {
        if (!recordBody) {
            return;
        }

        const row = document.createElement('tr');
        const cell = document.createElement('td');
        const mark = document.createElement('span');
        const title = document.createElement('strong');
        const note = document.createElement('small');

        cell.className = 'admin-taxonomy-empty';
        cell.colSpan = 6;
        mark.setAttribute('aria-hidden', 'true');
        mark.textContent = icon;
        title.textContent = message;
        note.textContent = detail;
        cell.append(mark, title, note);
        row.append(cell);
        recordBody.replaceChildren(row);
    };

    const createCell = (label, className = '') => {
        const cell = document.createElement('td');
        cell.dataset.label = label;

        if (className) {
            cell.className = className;
        }

        return cell;
    };

    const renderRecord = (record) => {
        const row = document.createElement('tr');
        const nameCell = createCell(`Nama ${recordLabel}`);
        const mark = document.createElement('span');
        const name = document.createElement('strong');
        const slugCell = createCell('Slug');
        const slug = document.createElement('code');
        const detailCell = createCell(kind === 'region' ? 'Tipe' : 'Deskripsi', kind === 'category' ? 'admin-taxonomy-description' : '');
        const countCell = createCell('Entri budaya');
        const count = document.createElement('span');
        const statusCell = createCell('Status');
        const status = document.createElement('span');
        const statusDot = document.createElement('span');
        const actionsCell = createCell('Tindakan');
        const actions = document.createElement('div');
        const edit = document.createElement('button');
        const toggle = document.createElement('button');

        mark.className = `admin-taxonomy-record-mark${kind === 'category' ? ' is-category' : ''}`;
        mark.setAttribute('aria-hidden', 'true');
        mark.textContent = icon;
        name.className = 'admin-taxonomy-record-name';
        name.textContent = record.name ?? '';
        nameCell.append(mark, name);

        slug.textContent = record.slug ?? '';
        slugCell.append(slug);

        if (kind === 'region') {
            const type = document.createElement('span');
            type.className = 'admin-taxonomy-type';
            type.textContent = String(record.region_type ?? '').replaceAll('_', ' ');
            detailCell.append(type);
        } else {
            detailCell.textContent = record.description || 'Belum ada deskripsi.';
        }

        const countValue = Number(record.cultural_entries_count ?? 0);
        count.className = record.cultural_entries_count === undefined ? 'admin-taxonomy-unavailable' : 'admin-taxonomy-count';
        count.textContent = record.cultural_entries_count === undefined
            ? 'Belum tersedia'
            : new Intl.NumberFormat('id-ID').format(countValue);
        countCell.append(count);

        status.className = `admin-taxonomy-status ${record.is_active ? 'is-active' : 'is-inactive'}`;
        statusDot.setAttribute('aria-hidden', 'true');
        status.append(statusDot, document.createTextNode(record.is_active ? 'Aktif' : 'Nonaktif'));
        statusCell.append(status);

        actions.className = 'admin-taxonomy-row-actions';
        edit.className = 'admin-taxonomy-link-button';
        edit.type = 'button';
        edit.dataset.dialogOpen = `${kind}-edit-dialog`;
        edit.dataset.recordId = String(record.id);
        edit.dataset.recordName = record.name ?? '';
        edit.dataset.recordSlug = record.slug ?? '';
        edit.dataset.recordType = record.region_type ?? '';
        edit.dataset.recordDescription = record.description ?? '';
        edit.dataset.recordActive = record.is_active ? '1' : '0';
        edit.textContent = 'Ubah';

        toggle.className = `admin-taxonomy-link-button ${record.is_active ? 'is-danger' : 'is-restore'}`;
        toggle.type = 'button';
        toggle.dataset.statusId = String(record.id);
        toggle.dataset.statusActive = record.is_active ? '1' : '0';
        toggle.textContent = record.is_active ? 'Nonaktifkan' : 'Aktifkan';
        actions.append(edit, toggle);
        actionsCell.append(actions);
        row.append(nameCell, slugCell, detailCell, countCell, statusCell, actionsCell);

        return row;
    };

    const renderPagination = (meta, links) => {
        if (!pagination) {
            return;
        }

        pagination.replaceChildren();

        if (!meta || meta.last_page <= 1) {
            return;
        }

        const summary = document.createElement('span');
        summary.className = 'admin-taxonomy-page-summary';
        summary.textContent = `${new Intl.NumberFormat('id-ID').format(meta.from ?? 0)}–${new Intl.NumberFormat('id-ID').format(meta.to ?? 0)} dari ${new Intl.NumberFormat('id-ID').format(meta.total ?? 0)} ${recordLabel}`;
        pagination.append(summary);

        [
            ['first', '«'],
            ['prev', '‹'],
            ['next', '›'],
            ['last', '»'],
        ].forEach(([key, label]) => {
            const url = links?.[key];
            const button = document.createElement(url ? 'a' : 'span');

            button.className = 'admin-taxonomy-page-link';
            button.textContent = label;
            button.setAttribute('aria-label', {
                first: 'Halaman pertama',
                prev: 'Halaman sebelumnya',
                next: 'Halaman berikutnya',
                last: 'Halaman terakhir',
            }[key]);

            if (url) {
                button.href = url;
                button.dataset.pageUrl = url;
            } else {
                button.setAttribute('aria-disabled', 'true');
            }

            pagination.append(button);
        });

        const current = document.createElement('span');
        current.className = 'admin-taxonomy-page-current';
        current.textContent = `Halaman ${meta.current_page} / ${meta.last_page}`;
        pagination.append(current);
    };

    const loadRecords = async (url = null) => {
        if (!config || config.dataset.apiReady !== 'true' || !config.dataset.indexUrl) {
            renderEmpty('Endpoint data belum terhubung.', 'UI siap menunggu route halaman dan API admin.');

            return;
        }

        const requestUrl = new URL(url ?? config.dataset.indexUrl, window.location.origin);

        if (!url && filterForm) {
            new FormData(filterForm).forEach((value, key) => {
                if (String(value).trim() !== '') {
                    requestUrl.searchParams.set(key, String(value).trim());
                }
            });
        }

        activePageUrl = requestUrl.toString();
        renderEmpty(`Memuat ${recordLabel}…`, 'Mengambil data dari API admin.');

        try {
            const response = await fetch(requestUrl, {
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            const payload = await readJson(response);

            if (!response.ok) {
                throw new Error(payload.message ?? `Permintaan gagal (${response.status}).`);
            }

            const records = Array.isArray(payload.data) ? payload.data : [];
            recordBody.replaceChildren();

            if (records.length === 0) {
                renderEmpty(`Tidak ada ${recordLabel} yang cocok.`, 'Coba ubah kata pencarian atau filter status.');
            } else {
                records.forEach((record) => recordBody.append(renderRecord(record)));
            }

            if (totalLabel) {
                totalLabel.textContent = new Intl.NumberFormat('id-ID').format(payload.meta?.total ?? records.length);
            }

            renderPagination(payload.meta, payload.links);
        } catch (error) {
            renderEmpty(`Data ${recordLabel} tidak dapat dimuat.`, 'Pastikan sesi admin masih aktif, lalu coba lagi.');
            showFeedback(error.message, true);
        }
    };

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-dialog-open]');

        if (trigger) {
            const dialog = document.getElementById(trigger.dataset.dialogOpen);

            if (!dialog) {
                return;
            }

            const form = dialog.querySelector('[data-record-form]');

            if (form?.dataset.recordForm === 'edit') {
                const fields = {
                    name: trigger.dataset.recordName ?? '',
                    slug: trigger.dataset.recordSlug ?? '',
                    type: trigger.dataset.recordType ?? '',
                    description: trigger.dataset.recordDescription ?? '',
                };

                Object.entries(fields).forEach(([name, value]) => {
                    const input = form.querySelector(`[data-edit-field="${name}"]`);

                    if (input) {
                        input.value = value;
                    }
                });

                const activeInput = form.querySelector('[data-edit-field="active"]');

                if (activeInput) {
                    activeInput.checked = trigger.dataset.recordActive === '1';
                }

                form.dataset.recordId = trigger.dataset.recordId ?? '';
                form.dataset.backendReady = config?.dataset.apiReady ?? 'false';
            }

            dialog.showModal();
        }
    });

    document.querySelectorAll('[data-dialog-close]').forEach((trigger) => {
        trigger.addEventListener('click', () => trigger.closest('dialog')?.close());
    });

    document.querySelectorAll('[data-record-form]').forEach((form) => {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            if (form.dataset.backendReady === 'false' || config?.dataset.apiReady !== 'true') {
                showFeedback('Aksi belum tersedia karena endpoint backend belum terhubung.', true);

                return;
            }

            if (!window.confirm(form.dataset.confirm)) {
                return;
            }

            const formData = new FormData(form);
            const data = Object.fromEntries(formData.entries());
            const active = form.querySelector('[name="is_active"]');

            if (active) {
                data.is_active = active.checked;
            }

            const editing = form.dataset.recordForm === 'edit';
            const template = config.dataset.updateTemplate;
            const endpoint = editing
                ? template?.replace('__id__', encodeURIComponent(form.dataset.recordId ?? ''))
                : config.dataset.storeUrl;

            if (!endpoint) {
                showFeedback(`Endpoint ${editing ? 'update' : 'store'} ${recordLabel} belum tersedia.`, true);

                return;
            }

            const submit = form.querySelector('[type="submit"]');
            submit.disabled = true;

            try {
                const response = await fetch(endpoint, {
                    method: editing ? 'PUT' : 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': config.dataset.csrfToken ?? '',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify(data),
                });
                const payload = await readJson(response);

                if (!response.ok) {
                    const validationMessage = Object.values(payload.errors ?? {}).flat().join(' ');
                    throw new Error(validationMessage || payload.message || `Permintaan gagal (${response.status}).`);
                }

                form.closest('dialog')?.close();
                form.reset();
                showFeedback(payload.message ?? `${recordLabel} berhasil disimpan.`);
                await loadRecords(activePageUrl);
            } catch (error) {
                showFeedback(error.message, true);
            } finally {
                submit.disabled = false;
            }
        });
    });

    document.addEventListener('click', async (event) => {
        const statusButton = event.target.closest('[data-status-id]');
        const pageLink = event.target.closest('[data-page-url]');

        if (pageLink) {
            event.preventDefault();
            await loadRecords(pageLink.dataset.pageUrl);

            return;
        }

        if (!statusButton) {
            return;
        }

        if (config?.dataset.apiReady !== 'true' || !config.dataset.statusTemplate) {
            showFeedback('Endpoint perubahan status belum tersedia.', true);

            return;
        }

        const active = statusButton.dataset.statusActive !== '1';
        const verb = active ? 'mengaktifkan' : 'menonaktifkan';

        if (!window.confirm(`Apakah Anda yakin ingin ${verb} ${recordLabel} ini?`)) {
            return;
        }

        statusButton.disabled = true;

        try {
            const endpoint = config.dataset.statusTemplate.replace('__id__', encodeURIComponent(statusButton.dataset.statusId));
            const response = await fetch(endpoint, {
                method: 'PATCH',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': config.dataset.csrfToken ?? '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ is_active: active }),
            });
            const payload = await readJson(response);

            if (!response.ok) {
                const validationMessage = Object.values(payload.errors ?? {}).flat().join(' ');
                throw new Error(validationMessage || payload.message || `Permintaan gagal (${response.status}).`);
            }

            showFeedback(payload.message ?? `Status ${recordLabel} berhasil diperbarui.`);
            await loadRecords(activePageUrl);
        } catch (error) {
            showFeedback(error.message, true);
            statusButton.disabled = false;
        }
    });

    filterForm?.addEventListener('submit', (event) => {
        event.preventDefault();
        loadRecords();
    });

    filterForm?.addEventListener('reset', () => {
        window.setTimeout(() => loadRecords(), 0);
    });

    loadRecords();
});
