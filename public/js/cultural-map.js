(() => {
    const mapElement = document.querySelector('#culture-map');

    if (!mapElement) {
        return;
    }

    const dataElement = document.querySelector('#culture-map-data');
    const listElement = document.querySelector('#culture-map-list');
    const detailElement = document.querySelector('#culture-map-detail');
    const searchInput = document.querySelector('#culture-map-search');
    const islandSelect = document.querySelector('#culture-map-island');
    const provinceSelect = document.querySelector('#culture-map-province');
    const categorySelect = document.querySelector('#culture-map-category');
    const countElement = document.querySelector('#culture-map-count');
    const statusElement = document.querySelector('#culture-map-status');
    const emptyElement = document.querySelector('#culture-map-no-results');
    const entries = JSON.parse(dataElement.textContent);
    const listItems = [...listElement.querySelectorAll('[data-map-id]')];

    if (!window.L) {
        statusElement.textContent = 'Peta interaktif tidak dapat dimuat. Daftar budaya tetap dapat dijelajahi.';
        return;
    }

    const indonesiaBounds = L.latLngBounds([[-11.2, 94.5], [6.7, 141.5]]);
    const map = L.map(mapElement, {
        zoomControl: false,
        minZoom: 3,
        maxZoom: 12,
        maxBounds: L.latLngBounds([[-16, 88], [12, 150]]),
        maxBoundsViscosity: 0.72,
        scrollWheelZoom: false,
        tap: true,
    });

    L.imageOverlay(mapElement.dataset.mapImage, indonesiaBounds, {
        alt: 'Siluet relief ilustratif kepulauan Indonesia',
        interactive: false,
        opacity: 1,
    }).addTo(map);
    L.control.zoom({ position: 'bottomright' }).addTo(map);
    map.fitBounds(indonesiaBounds, { padding: [24, 24] });

    const markerById = new Map();
    const entryById = new Map(entries.map((entry) => [entry.id, entry]));
    let activeId = null;
    let visibleEntries = [...entries];

    const escapeHtml = (value) => String(value).replace(/[&<>"']/g, (character) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;',
    })[character]);

    const markerIcon = (entry, active = false) => L.divIcon({
        className: `culture-pin${active ? ' is-active' : ''}`,
        html: `<span class="culture-pin-bubble" aria-hidden="true"><span>${escapeHtml(entry.icon)}</span></span><span class="culture-pin-dot" aria-hidden="true"></span>`,
        iconSize: [active ? 54 : 42, active ? 68 : 54],
        iconAnchor: [active ? 27 : 21, active ? 60 : 48],
        tooltipAnchor: [0, -58],
    });

    entries.forEach((entry) => {
        const marker = L.marker(entry.coordinates, {
            icon: markerIcon(entry),
            title: `${entry.name} — ${entry.region}`,
            alt: `Lokasi budaya ${entry.name}, ${entry.region}`,
            riseOnHover: true,
        }).addTo(map);

        marker.on('click', () => selectEntry(entry.id, true));
        markerById.set(entry.id, marker);
    });

    const renderDetail = (entry) => {
        const visual = entry.image_url
            ? `<img src="${escapeHtml(entry.image_url)}" alt="${escapeHtml(entry.name)}" loading="lazy">`
            : '';
        const visualLabel = entry.image_url ? 'Dokumentasi budaya' : 'Ilustrasi simbolis';

        detailElement.innerHTML = `
            <article class="culture-map-detail-card">
                <div class="culture-map-detail-art ${entry.image ? '' : 'is-fallback'}">
                    ${visual}
                    <span class="culture-map-detail-art-icon" aria-hidden="true">${escapeHtml(entry.icon)}</span>
                    <span class="culture-map-detail-art-label">${visualLabel} · ${escapeHtml(entry.island)}</span>
                </div>
                <div class="culture-map-detail-copy">
                    <div class="culture-map-detail-meta"><span>${escapeHtml(entry.category)}</span><span>${escapeHtml(entry.province)}</span></div>
                    <h3>${escapeHtml(entry.name)}</h3>
                    <p class="culture-map-detail-region"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Z"></path><circle cx="12" cy="10" r="2.5"></circle></svg>${escapeHtml(entry.region)}</p>
                    <p>${escapeHtml(entry.description)}</p>
                    <div class="culture-map-story"><span>Jejak sejarah</span><p>${escapeHtml(entry.history)}</p></div>
                    <div class="culture-map-unique"><span aria-hidden="true">✳</span><p><strong>Yang membuatnya istimewa</strong>${escapeHtml(entry.unique)}</p></div>
                    <a class="culture-map-coordinates" href="https://www.openstreetmap.org/?mlat=${encodeURIComponent(entry.coordinates[0])}&mlon=${encodeURIComponent(entry.coordinates[1])}#map=9/${encodeURIComponent(entry.coordinates[0])}/${encodeURIComponent(entry.coordinates[1])}" target="_blank" rel="noopener noreferrer">Lihat kawasan di peta <span aria-hidden="true">↗</span></a>
                </div>
            </article>`;

        const detailImage = detailElement.querySelector('.culture-map-detail-art img');
        detailImage?.addEventListener('error', () => {
            detailImage.closest('.culture-map-detail-art').classList.add('is-fallback');
            detailImage.remove();
        }, { once: true });
    };

    function selectEntry(id, moveToMarker) {
        const entry = entryById.get(id);

        if (!entry || !visibleEntries.some((item) => item.id === id)) {
            return;
        }

        if (activeId && markerById.has(activeId)) {
            const previousMarker = markerById.get(activeId);
            previousMarker.setIcon(markerIcon(entryById.get(activeId)));
        }

        activeId = id;
        const marker = markerById.get(id);
        marker.setIcon(markerIcon(entry, true));
        renderDetail(entry);

        listItems.forEach((item) => {
            const active = item.dataset.mapId === id;
            item.classList.toggle('is-active', active);
            item.setAttribute('aria-pressed', String(active));
        });

        if (moveToMarker) {
            map.flyTo(entry.coordinates, Math.max(map.getZoom(), 6), { duration: 0.75 });
        }
        statusElement.textContent = `${entry.name}, ${entry.region} dipilih.`;
    }

    const applyFilters = () => {
        const search = searchInput.value.trim().toLocaleLowerCase('id');
        const island = islandSelect.value;
        const province = provinceSelect.value;
        const category = categorySelect.value;

        visibleEntries = entries.filter((entry) => {
            const searchable = `${entry.name} ${entry.region} ${entry.province} ${entry.category} ${entry.island}`.toLocaleLowerCase('id');

            return (!search || searchable.includes(search))
                && (!island || entry.island === island)
                && (!province || entry.province === province)
                && (!category || entry.category === category);
        });

        const visibleIds = new Set(visibleEntries.map((entry) => entry.id));
        const hiddenActiveId = activeId && !visibleIds.has(activeId) ? activeId : null;

        listItems.forEach((item) => {
            item.hidden = !visibleIds.has(item.dataset.mapId);
        });
        markerById.forEach((marker, id) => {
            if (visibleIds.has(id)) {
                if (!map.hasLayer(marker)) {
                    marker.addTo(map);
                }
            } else {
                if (map.hasLayer(marker)) map.removeLayer(marker);
            }
        });

        countElement.textContent = `${visibleEntries.length} lokasi`;
        emptyElement.hidden = visibleEntries.length > 0;
        statusElement.textContent = `${visibleEntries.length} lokasi budaya ditampilkan.`;

        if (hiddenActiveId) {
            const previousMarker = markerById.get(hiddenActiveId);
            previousMarker.setIcon(markerIcon(entryById.get(activeId)));
            activeId = null;
            detailElement.innerHTML = '<div class="culture-map-detail-empty"><span class="detail-empty-mark" aria-hidden="true">✳</span><p class="culture-map-detail-kicker">Catatan lapangan</p><h3>Pilih sebuah penanda.</h3><p>Sentuh titik di peta atau pilih nama budaya pada daftar untuk membuka cerita dan keunikannya.</p></div>';
            listItems.forEach((item) => {
                item.classList.remove('is-active');
                item.setAttribute('aria-pressed', 'false');
            });
        }
    };

    listItems.forEach((item) => {
        item.setAttribute('aria-pressed', 'false');
        item.addEventListener('click', () => selectEntry(item.dataset.mapId, true));
    });
    searchInput.addEventListener('input', applyFilters);
    islandSelect.addEventListener('change', applyFilters);
    provinceSelect.addEventListener('change', applyFilters);
    categorySelect.addEventListener('change', applyFilters);
    document.querySelector('#culture-map-reset').addEventListener('click', () => {
        searchInput.value = '';
        islandSelect.value = '';
        provinceSelect.value = '';
        categorySelect.value = '';
        applyFilters();
        map.fitBounds(indonesiaBounds, { padding: [24, 24] });
    });
    document.querySelector('#culture-map-fit').addEventListener('click', () => {
        map.fitBounds(indonesiaBounds, { padding: [24, 24] });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === '/' && !['INPUT', 'SELECT', 'TEXTAREA'].includes(document.activeElement.tagName)) {
            event.preventDefault();
            searchInput.focus();
        }
    });

    window.setTimeout(() => map.invalidateSize(), 100);
    window.addEventListener('resize', () => map.invalidateSize());
})();
