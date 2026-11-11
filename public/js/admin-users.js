(() => {
    const config = document.querySelector('[data-user-config]');
    if (!config) return;

    const api = {
        index: config.dataset.indexUrl,
        show: config.dataset.showTemplate,
        update: config.dataset.updateTemplate,
        password: config.dataset.passwordTemplate,
    };
    const rows = document.querySelector('[data-user-rows]');
    const total = document.querySelector('[data-users-total]');
    const pagination = document.querySelector('[data-user-pagination]');
    const filterForm = document.querySelector('[data-user-filter-form]');
    const statusFilter = filterForm.elements.status;
    const dialog = document.querySelector('[data-user-dialog]');
    const form = document.querySelector('[data-user-form]');
    const feedback = document.querySelector('[data-users-feedback]');
    const toast = document.querySelector('[data-user-toast]');
    const passwordDialog = document.querySelector('[data-password-dialog]');
    const passwordForm = document.querySelector('[data-password-form]');
    let activeUrl = api.index;
    let editingUser = null;
    let resetUser = null;
    let toastTimer;
    let isLoading = false;
    let pendingUrl = null;

    const escapePath = (template, id) => template.replace('__id__', encodeURIComponent(id));
    const labelRole = (role) => ({
        super_admin: 'Super Admin',
    }[role] || role || '—');
    const formatDate = (value) => {
        if (!value) return '—';
        const date = new Date(value);
        return Number.isNaN(date.getTime()) ? '—' : new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium' }).format(date);
    };
    async function request(url, options = {}) {
        const response = await fetch(url, {
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': config.dataset.csrfToken,
                ...(options.headers || {}),
            },
            ...options,
        });
        const body = await response.json().catch(() => ({}));
        if (!response.ok) {
            const validation = body.errors ? Object.values(body.errors).flat().join(' ') : '';
            throw new Error(validation || body.message || `Permintaan gagal (${response.status}).`);
        }
        return body;
    }

    function showFeedback(message, isError = false) {
        feedback.textContent = message;
        feedback.classList.toggle('is-error', isError);
        feedback.hidden = false;
    }

    function showToast(message) {
        toast.textContent = message;
        toast.hidden = false;
        window.clearTimeout(toastTimer);
        toastTimer = window.setTimeout(() => { toast.hidden = true; }, 3600);
    }

    function emptyRow(title, note) {
        const row = document.createElement('tr');
        const cell = document.createElement('td');
        cell.colSpan = 6;
        cell.className = 'ua-state';
        const strong = document.createElement('strong');
        strong.textContent = title;
        cell.append(strong);
        if (note) {
            const small = document.createElement('small');
            small.textContent = note;
            cell.append(small);
        }
        row.append(cell);
        return row;
    }

    function actionButton(label, action, danger = false) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = `ua-action${danger ? ' ua-action-danger' : ''}`;
        button.textContent = label;
        button.dataset.action = action;
        return button;
    }

    function renderRows(users) {
        rows.replaceChildren();
        if (!users.length) {
            rows.append(emptyRow('Belum ada akun yang cocok.', 'Ubah pencarian atau filter untuk melihat hasil lain.'));
            return;
        }

        users.forEach((user) => {
            const row = document.createElement('tr');
            const nameCell = document.createElement('td');
            const name = document.createElement('span');
            name.className = 'ua-account-name';
            name.textContent = user.name || '—';
            nameCell.append(name);

            const emailCell = document.createElement('td');
            const email = document.createElement('span');
            email.className = 'ua-account-email';
            email.textContent = user.email || '—';
            emailCell.append(email);

            const roleCell = document.createElement('td');
            const role = document.createElement('span');
            role.className = `ua-role${user.role === 'super_admin' ? ' ua-role-super_admin' : ''}`;
            role.textContent = labelRole(user.role);
            roleCell.append(role);

            const statusCell = document.createElement('td');
            const status = document.createElement('span');
            const loginStatusAvailable = typeof user.login_active === 'boolean';
            status.className = `ua-status ${loginStatusAvailable
                ? (user.login_active ? 'ua-status-active' : 'ua-status-inactive')
                : 'ua-status-unavailable'}`;
            status.textContent = loginStatusAvailable
                ? (user.login_active ? 'Aktif' : 'Tidak aktif')
                : 'Belum tersedia';
            statusCell.append(status);

            const createdCell = document.createElement('td');
            createdCell.textContent = formatDate(user.created_at);

            const actionsCell = document.createElement('td');
            const actions = document.createElement('div');
            actions.className = 'ua-row-actions';
            const edit = actionButton('Edit', 'edit');
            edit.dataset.userId = user.id;
            actions.append(edit);
            const reset = actionButton('Reset sandi', 'reset');
            reset.dataset.userId = user.id;
            actions.append(reset);
            actionsCell.append(actions);
            row.append(nameCell, emailCell, roleCell, statusCell, createdCell, actionsCell);
            rows.append(row);
        });
    }

    function renderPagination(meta, links) {
        pagination.replaceChildren();
        if (!meta || meta.last_page <= 1) return;
        const previous = document.createElement('button');
        previous.type = 'button';
        previous.textContent = '‹';
        previous.setAttribute('aria-label', 'Halaman sebelumnya');
        previous.disabled = !links.prev;
        previous.addEventListener('click', () => links.prev && load(links.prev));
        const summary = document.createElement('span');
        summary.className = 'ua-page-summary';
        summary.textContent = `${meta.current_page} / ${meta.last_page}`;
        const next = document.createElement('button');
        next.type = 'button';
        next.textContent = '›';
        next.setAttribute('aria-label', 'Halaman berikutnya');
        next.disabled = !links.next;
        next.addEventListener('click', () => links.next && load(links.next));
        pagination.append(previous, summary, next);
    }

    async function load(url = api.index, { silent = false } = {}) {
        if (isLoading) {
            if (!silent) pendingUrl = url;
            return;
        }

        isLoading = true;
        activeUrl = url;
        if (!silent) {
            feedback.hidden = true;
            rows.replaceChildren(emptyRow('Memuat akun…', 'Mohon tunggu sebentar.'));
        }
        try {
            const query = new URLSearchParams(new FormData(filterForm));
            const requestUrl = url === api.index ? `${url}?${query}` : url;
            const result = await request(requestUrl);
            feedback.hidden = true;
            const statusAvailable = result.meta?.status_available !== false;
            statusFilter.disabled = !statusAvailable;
            statusFilter.value = statusAvailable ? statusFilter.value : '';
            statusFilter.options[0].textContent = statusAvailable
                ? 'Semua status login'
                : 'Status login belum tersedia';
            renderRows(result.data || []);
            total.textContent = new Intl.NumberFormat('id-ID').format(result.meta?.total || 0);
            renderPagination(result.meta, result.links || {});
        } catch (error) {
            if (!silent) {
                rows.replaceChildren(emptyRow('Daftar akun tidak dapat dimuat.', error.message));
                total.textContent = '—';
                pagination.replaceChildren();
            }
            showFeedback(error.message, true);
        } finally {
            isLoading = false;
            if (pendingUrl !== null) {
                const nextUrl = pendingUrl;
                pendingUrl = null;
                load(nextUrl);
            }
        }
    }

    function setFormError(element, error) {
        element.textContent = error;
        element.hidden = false;
    }

    async function openEdit(id) {
        try {
            const result = await request(escapePath(api.show, id));
            editingUser = result.data;
            form.reset();
            form.elements.name.value = editingUser.name || '';
            form.elements.email.value = editingUser.email || '';
            document.querySelector('[data-user-form-error]').hidden = true;
            dialog.showModal();
        } catch (error) {
            showFeedback(error.message, true);
        }
    }

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const submit = document.querySelector('[data-user-submit]');
        const errorBox = document.querySelector('[data-user-form-error]');
        errorBox.hidden = true;
        submit.disabled = true;
        const values = Object.fromEntries(new FormData(form).entries());
        try {
            await request(escapePath(api.update, editingUser.id), {
                method: 'PUT',
                body: JSON.stringify({
                    name: values.name,
                    email: values.email,
                }),
            });
            dialog.close();
            showToast('Informasi akun diperbarui.');
            await load(activeUrl);
        } catch (error) {
            setFormError(errorBox, error.message);
        } finally {
            submit.disabled = false;
        }
    });

    rows.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-action]');
        if (!button) return;
        const id = Number(button.dataset.userId);
        if (button.dataset.action === 'edit') {
            await openEdit(id);
            return;
        }
        if (button.dataset.action === 'reset') {
            resetUser = id;
            const row = button.closest('tr');
            const accountName = row.querySelector('.ua-account-name').textContent;
            if (!window.confirm(`Lanjutkan reset password untuk akun ${accountName}?`)) return;
            document.querySelector('[data-password-caption]').textContent =
                `Atur password baru untuk ${accountName}. Password lama tidak akan ditampilkan.`;
            passwordForm.reset();
            document.querySelector('[data-password-error]').hidden = true;
            passwordDialog.showModal();
            return;
        }
    });

    passwordForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        const errorBox = document.querySelector('[data-password-error]');
        errorBox.hidden = true;
        const values = Object.fromEntries(new FormData(passwordForm).entries());
        try {
            await request(escapePath(api.password, resetUser), {
                method: 'PUT',
                body: JSON.stringify({
                    password: values.password,
                    password_confirmation: values.password_confirmation,
                }),
            });
            passwordDialog.close();
            showToast('Password akun berhasil direset.');
        } catch (error) {
            setFormError(errorBox, error.message);
        }
    });

    document.querySelectorAll('[data-user-close]').forEach((button) => {
        button.addEventListener('click', () => dialog.close());
    });
    document.querySelectorAll('[data-password-close]').forEach((button) => {
        button.addEventListener('click', () => passwordDialog.close());
    });
    filterForm.addEventListener('submit', (event) => {
        event.preventDefault();
        const query = new URLSearchParams(new FormData(filterForm));
        load(`${api.index}?${query}`);
    });
    filterForm.addEventListener('reset', () => window.setTimeout(() => load(api.index), 0));
    statusFilter.disabled = true;
    load();
    window.setInterval(() => {
        if (!document.hidden && !isLoading) load(activeUrl, { silent: true });
    }, 12000);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden && !isLoading) load(activeUrl, { silent: true });
    });
})();
