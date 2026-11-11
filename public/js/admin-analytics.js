(() => {
    const config = document.querySelector('[data-analytics-config]');
    if (!config) return;

    const endpoints = {
        summary: config.dataset.summaryUrl,
        traffic: config.dataset.trafficUrl,
        devices: config.dataset.devicesUrl,
        pages: config.dataset.pagesUrl,
        geography: config.dataset.geographyUrl,
    };
    const filterForm = document.querySelector('[data-analytics-filter-form]');
    const feedback = document.querySelector('[data-analytics-feedback]');
    const statusNote = document.querySelector('[data-analytics-status]');
    const count = new Intl.NumberFormat('id-ID');
    let controller;

    async function getJson(url) {
        const response = await fetch(url, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        });
        const body = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(body.message || `Data analitik gagal dimuat (${response.status}).`);
        return body;
    }

    function queryString() {
        const values = new URLSearchParams(new FormData(filterForm));
        return `?${values.toString()}`;
    }

    function setFeedback(message, isError = false) {
        feedback.textContent = message;
        feedback.classList.toggle('is-error', isError);
        feedback.hidden = false;
    }

    function setEmpty(container, message) {
        const state = document.createElement('div');
        state.className = 'ua-state';
        const strong = document.createElement('strong');
        strong.textContent = message;
        state.append(strong);
        container.replaceChildren(state);
    }

    function renderBreakdown(container, items, labelKey) {
        container.replaceChildren();
        if (!Array.isArray(items) || items.length === 0) {
            setEmpty(container, 'Belum ada data pada rentang ini.');
            return;
        }
        const max = Math.max(...items.map((item) => Number(item.page_views) || 0), 1);
        items.forEach((item) => {
            const row = document.createElement('div');
            row.className = 'ua-breakdown-row';
            const label = document.createElement('span');
            label.className = 'ua-breakdown-label';
            label.textContent = item[labelKey] || 'Tidak diketahui';
            const track = document.createElement('span');
            track.className = 'ua-breakdown-track';
            const fill = document.createElement('span');
            fill.className = 'ua-breakdown-fill';
            fill.style.display = 'block';
            fill.style.width = `${Math.max(3, (Number(item.page_views) / max) * 100)}%`;
            track.append(fill);
            const value = document.createElement('span');
            value.className = 'ua-breakdown-value';
            value.textContent = count.format(Number(item.page_views) || 0);
            row.append(label, track, value);
            container.append(row);
        });
    }

    function renderTraffic(container, items) {
        if (!Array.isArray(items) || !items.length) {
            setEmpty(container, 'Belum ada kunjungan pada rentang ini.');
            return;
        }
        const ns = 'http://www.w3.org/2000/svg';
        const width = Math.max(640, items.length * 52);
        const height = 235;
        const padding = { top: 20, right: 22, bottom: 38, left: 42 };
        const plotWidth = width - padding.left - padding.right;
        const plotHeight = height - padding.top - padding.bottom;
        const values = items.map((item) => Number(item.unique_visitors) || 0);
        const max = Math.max(...values, 1);
        const points = values.map((value, index) => ({
            x: padding.left + (items.length === 1 ? plotWidth / 2 : (index / (items.length - 1)) * plotWidth),
            y: padding.top + plotHeight - (value / max) * plotHeight,
        }));
        const svg = document.createElementNS(ns, 'svg');
        svg.setAttribute('viewBox', `0 0 ${width} ${height}`);
        svg.setAttribute('role', 'img');
        svg.setAttribute('aria-label', 'Grafik pengunjung unik dalam rentang tanggal terpilih');

        [0, .5, 1].forEach((ratio) => {
            const y = padding.top + plotHeight * ratio;
            const line = document.createElementNS(ns, 'line');
            line.setAttribute('x1', String(padding.left));
            line.setAttribute('x2', String(width - padding.right));
            line.setAttribute('y1', String(y));
            line.setAttribute('y2', String(y));
            line.setAttribute('class', 'ua-chart-gridline');
            svg.append(line);
            const label = document.createElementNS(ns, 'text');
            label.setAttribute('x', String(padding.left - 8));
            label.setAttribute('y', String(y + 4));
            label.setAttribute('text-anchor', 'end');
            label.setAttribute('class', 'ua-chart-label');
            label.textContent = count.format(Math.round(max * (1 - ratio)));
            svg.append(label);
        });

        const polylinePoints = points.map((point) => `${point.x},${point.y}`).join(' ');
        const area = document.createElementNS(ns, 'polygon');
        area.setAttribute('points', `${padding.left},${padding.top + plotHeight} ${polylinePoints} ${width - padding.right},${padding.top + plotHeight}`);
        area.setAttribute('class', 'ua-chart-area');
        svg.append(area);
        const line = document.createElementNS(ns, 'polyline');
        line.setAttribute('points', polylinePoints);
        line.setAttribute('class', 'ua-chart-line');
        svg.append(line);

        const labelStep = Math.max(1, Math.ceil(items.length / 7));
        points.forEach((point, index) => {
            const circle = document.createElementNS(ns, 'circle');
            circle.setAttribute('cx', String(point.x));
            circle.setAttribute('cy', String(point.y));
            circle.setAttribute('r', '4');
            circle.setAttribute('class', 'ua-chart-point');
            const title = document.createElementNS(ns, 'title');
            title.textContent = `${items[index].period_start}: ${count.format(values[index])} pengunjung unik, ${count.format(Number(items[index].page_views) || 0)} page views`;
            circle.append(title);
            svg.append(circle);
            if (index % labelStep === 0 || index === items.length - 1) {
                const label = document.createElementNS(ns, 'text');
                label.setAttribute('x', String(point.x));
                label.setAttribute('y', String(height - 12));
                label.setAttribute('text-anchor', 'middle');
                label.setAttribute('class', 'ua-chart-label');
                label.textContent = String(items[index].period_start || '').slice(5);
                svg.append(label);
            }
        });
        container.replaceChildren(svg);
    }

    function renderPages(items, error = '') {
        const body = document.querySelector('[data-popular-pages]');
        body.replaceChildren();
        if (!Array.isArray(items) || !items.length) {
            const row = document.createElement('tr');
            const cell = document.createElement('td');
            cell.colSpan = 3;
            cell.className = 'ua-state';
            cell.textContent = error || 'Belum ada page view pada rentang ini.';
            row.append(cell);
            body.append(row);
            return;
        }
        items.forEach((item) => {
            const row = document.createElement('tr');
            [item.path, count.format(Number(item.page_views) || 0), count.format(Number(item.unique_visitors) || 0)]
                .forEach((value) => {
                    const cell = document.createElement('td');
                    cell.textContent = value;
                    row.append(cell);
                });
            body.append(row);
        });
    }

    function renderLocations(data, error = '') {
        const note = document.querySelector('[data-location-note]');
        note.textContent = error || (data.available
            ? 'Lokasi bersifat perkiraan dan hanya berasal dari data yang tersedia.'
            : 'Data lokasi belum tersedia; lokasi tidak ditebak atau diisi otomatis.');
        renderBreakdown(document.querySelector('[data-countries-list]'), data.countries, 'country_code');
        renderBreakdown(document.querySelector('[data-provinces-list]'), data.provinces, 'province');
        renderBreakdown(document.querySelector('[data-cities-list]'), data.cities, 'city');
    }

    async function load() {
        if (controller) controller.abort();
        controller = new AbortController();
        feedback.hidden = true;
        statusNote.textContent = 'Memuat data analitik…';
        const query = queryString();
        const requests = Object.entries(endpoints).map(async ([name, url]) => {
            const response = await fetch(`${url}${query}`, {
                credentials: 'same-origin',
                signal: controller.signal,
                headers: { Accept: 'application/json' },
            });
            const body = await response.json().catch(() => ({}));
            if (!response.ok) {
                const error = new Error(body.message || `Gagal memuat data (${response.status}).`);
                error.endpoint = name;
                throw error;
            }
            return [name, body];
        });
        const results = await Promise.allSettled(requests);
        const errors = {};
        const loaded = {};
        results.forEach((result) => {
            if (result.status === 'fulfilled') {
                loaded[result.value[0]] = result.value[1];
            } else if (result.reason?.name !== 'AbortError') {
                errors[result.reason?.endpoint || 'request'] = result.reason?.message || 'Gagal memuat data.';
            }
        });

        if (loaded.summary) {
            const metrics = loaded.summary.data || {};
            document.querySelectorAll('[data-stat]').forEach((element) => {
                element.textContent = count.format(Number(metrics[element.dataset.stat]) || 0);
            });
            const meta = loaded.summary.meta || {};
            statusNote.textContent = meta.enabled
                ? `Analitik aktif · retensi ${meta.retention_days} hari · ${meta.date_from}–${meta.date_to}`
                : 'Pencatatan analitik sedang dinonaktifkan. Data historis yang tersedia tetap ditampilkan.';
        } else {
            statusNote.textContent = errors.summary || 'Ringkasan belum tersedia.';
            document.querySelectorAll('[data-stat]').forEach((element) => { element.textContent = '—'; });
        }

        if (loaded.traffic) {
            renderTraffic(document.querySelector('[data-traffic-chart]'), loaded.traffic.data);
        } else {
            setEmpty(document.querySelector('[data-traffic-chart]'), errors.traffic || 'Grafik tidak tersedia.');
        }
        if (loaded.devices) {
            renderBreakdown(document.querySelector('[data-device-chart]'), loaded.devices.data?.devices, 'device_type');
            renderBreakdown(document.querySelector('[data-browser-list]'), loaded.devices.data?.browsers, 'browser');
            renderBreakdown(document.querySelector('[data-os-list]'), loaded.devices.data?.operating_systems, 'operating_system');
        } else {
            [document.querySelector('[data-device-chart]'), document.querySelector('[data-browser-list]'), document.querySelector('[data-os-list]')]
                .forEach((container) => setEmpty(container, errors.devices || 'Statistik perangkat tidak tersedia.'));
        }
        if (loaded.pages) renderPages(loaded.pages.data);
        else renderPages([], errors.pages || 'Daftar halaman tidak tersedia.');
        if (loaded.geography) renderLocations(loaded.geography.data || {});
        else renderLocations({ available: false, countries: [], provinces: [], cities: [] }, errors.geography || '');

        if (Object.keys(errors).length) {
            setFeedback(`${Object.keys(errors).length} bagian analitik gagal dimuat. ${Object.values(errors).join(' ')}`, true);
        }
    }

    function setDefaultDates() {
        const today = new Date();
        const start = new Date(today);
        start.setDate(start.getDate() - 29);
        const toInput = (date) => {
            const local = new Date(date.getTime() - date.getTimezoneOffset() * 60000);
            return local.toISOString().slice(0, 10);
        };
        filterForm.elements.date_from.value = toInput(start);
        filterForm.elements.date_to.value = toInput(today);
    }

    filterForm.addEventListener('submit', (event) => {
        event.preventDefault();
        if (filterForm.elements.date_from.value > filterForm.elements.date_to.value) {
            setFeedback('Tanggal awal harus sama dengan atau sebelum tanggal akhir.', true);
            return;
        }
        const from = new Date(`${filterForm.elements.date_from.value}T00:00:00`);
        const to = new Date(`${filterForm.elements.date_to.value}T00:00:00`);
        const days = Math.floor((to - from) / 86400000) + 1;
        if (days > 90) {
            setFeedback('Rentang tanggal maksimal 90 hari.', true);
            return;
        }
        load();
    });

    setDefaultDates();
    load();
})();
