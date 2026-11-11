const libraryTools = document.querySelector('[data-library-tools]');

if (libraryTools) {
    const searchInput = libraryTools.querySelector('[data-book-search]');
    const filterButtons = Array.from(libraryTools.querySelectorAll('[data-book-filter]'));
    const entries = Array.from(document.querySelectorAll('[data-book-entry]'));
    const emptyState = document.querySelector('[data-library-empty]');
    let activeFilter = 'all';

    const filterBooks = () => {
        const query = searchInput.value.trim().toLocaleLowerCase('id');
        let visibleCount = 0;

        entries.forEach((entry) => {
            const matchesFilter = activeFilter === 'all' || entry.dataset.category === activeFilter;
            const matchesSearch = !query || entry.dataset.search.includes(query);
            entry.hidden = !(matchesFilter && matchesSearch);
            visibleCount += Number(!entry.hidden);
        });

        emptyState.hidden = visibleCount > 0;
    };

    searchInput.addEventListener('input', filterBooks);
    filterButtons.forEach((button) => {
        button.addEventListener('click', () => {
            activeFilter = button.dataset.bookFilter;
            filterButtons.forEach((filterButton) => {
                const isActive = filterButton === button;
                filterButton.classList.toggle('is-active', isActive);
                filterButton.setAttribute('aria-pressed', String(isActive));
            });
            filterBooks();
        });
    });
}

document.querySelectorAll('[data-book-reader]').forEach((reader) => {
    const pages = Array.from(reader.querySelectorAll('[data-book-page]'));
    const previousButton = reader.querySelector('[data-page-previous]');
    const nextButton = reader.querySelector('[data-page-next]');
    const openBookButton = reader.querySelector('[data-open-book]');
    const tocLinks = Array.from(reader.querySelectorAll('[data-toc-link]'));
    const currentPageLabels = Array.from(reader.querySelectorAll('[data-page-current], [data-page-current-footer]'));
    const progress = reader.querySelector('[role="progressbar"]');
    const progressFill = reader.querySelector('[data-progress-fill]');
    const bookmarkButton = reader.querySelector('[data-bookmark-toggle]');
    const bookmarkLabel = reader.querySelector('[data-bookmark-label]');
    const comfortButton = reader.querySelector('[data-comfort-toggle]');
    const comfortLabel = reader.querySelector('[data-comfort-label]');
    const themeButton = reader.querySelector('[data-theme-toggle]');
    const themeLabel = reader.querySelector('[data-theme-label]');
    const status = reader.querySelector('[data-reader-status]');
    const slug = reader.dataset.bookSlug;
    const pageCount = Number(reader.dataset.pageCount);
    const progressKey = `warna-budaya:reading:${slug}`;
    const bookmarkKey = `warna-budaya:bookmarks:${slug}`;
    let currentPage = 0;
    let bookmarks = [];
    let statusTimer;

    if (!pages.length || !previousButton || !nextButton || !progress || !progressFill || !slug) {
        return;
    }

    const announce = (message) => {
        status.textContent = message;
        window.clearTimeout(statusTimer);
        statusTimer = window.setTimeout(() => {
            status.textContent = '';
        }, 3200);
    };

    const readStorage = (key, fallback) => {
        try {
            const value = window.localStorage.getItem(key);
            return value === null ? fallback : JSON.parse(value);
        } catch {
            announce('Penyimpanan lokal tidak tersedia. Penanda dan posisi baca tidak akan tersimpan.');
            return fallback;
        }
    };

    const writeStorage = (key, value) => {
        try {
            window.localStorage.setItem(key, JSON.stringify(value));
            return true;
        } catch {
            announce('Peramban tidak dapat menyimpan perubahan ini.');
            return false;
        }
    };

    const isBookmarked = () => bookmarks.includes(currentPage);

    const updateBookmark = () => {
        if (!bookmarkButton || !bookmarkLabel) {
            return;
        }

        bookmarkButton.setAttribute('aria-pressed', String(isBookmarked()));
        bookmarkButton.classList.toggle('is-saved', isBookmarked());
        bookmarkButton.setAttribute('aria-label', isBookmarked() ? 'Hapus penanda halaman' : 'Simpan halaman sebagai penanda');
        bookmarkLabel.textContent = isBookmarked() ? 'Halaman ditandai' : 'Simpan halaman';

        reader.querySelectorAll('[data-bookmark-marker]').forEach((marker) => {
            marker.hidden = !bookmarks.includes(Number(marker.dataset.bookmarkMarker));
        });
    };

    const displayPage = (pageIndex, options = {}) => {
        currentPage = Math.max(0, Math.min(pageIndex, pages.length - 1));

        pages.forEach((page, index) => {
            page.hidden = index !== currentPage;
            if (index === currentPage) {
                page.setAttribute('aria-current', 'page');
            } else {
                page.removeAttribute('aria-current');
            }
        });

        tocLinks.forEach((link) => {
            const isCurrent = Number(link.dataset.pageIndex) === currentPage;
            link.classList.toggle('is-current', isCurrent);
            if (isCurrent) {
                link.setAttribute('aria-current', 'page');
            } else {
                link.removeAttribute('aria-current');
            }
        });

        previousButton.disabled = currentPage === 0;
        nextButton.disabled = currentPage === pages.length - 1;
        const currentLabel = currentPage === 0 ? 'Sampul' : `Bab ${currentPage}`;
        currentPageLabels.forEach((label) => {
            label.textContent = currentLabel;
        });
        progress.setAttribute('aria-valuenow', String(Math.max(0, currentPage)));
        progressFill.style.width = `${(currentPage / Math.max(1, pageCount - 1)) * 100}%`;
        updateBookmark();

        if (options.persist) {
            writeStorage(progressKey, currentPage);
        }

        if (options.focus) {
            pages[currentPage].querySelector('h1, h2')?.focus();
            pages[currentPage].scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    };

    previousButton.addEventListener('click', () => displayPage(currentPage - 1, { focus: true, persist: true }));
    nextButton.addEventListener('click', () => displayPage(currentPage + 1, { focus: true, persist: true }));
    openBookButton?.addEventListener('click', () => displayPage(1, { focus: true, persist: true }));
    tocLinks.forEach((link) => {
        link.addEventListener('click', () => displayPage(Number(link.dataset.pageIndex), { focus: true, persist: true }));
    });

    bookmarkButton?.addEventListener('click', () => {
        bookmarks = isBookmarked()
            ? bookmarks.filter((page) => page !== currentPage)
            : [...bookmarks, currentPage];
        if (writeStorage(bookmarkKey, bookmarks)) {
            updateBookmark();
            announce(isBookmarked() ? 'Halaman ditambahkan ke penanda.' : 'Penanda halaman dihapus.');
        }
    });

    comfortButton?.addEventListener('click', () => {
        const isComfortable = reader.classList.toggle('is-comfortable');
        comfortButton.setAttribute('aria-pressed', String(isComfortable));
        comfortLabel.textContent = isComfortable ? 'Mode nyaman aktif' : 'Mode nyaman';
    });

    themeButton?.addEventListener('click', () => {
        const isNight = reader.classList.toggle('is-night-reading');
        themeButton.setAttribute('aria-pressed', String(isNight));
        themeLabel.textContent = isNight ? 'Mode terang' : 'Mode malam';
    });

    reader.addEventListener('keydown', (event) => {
        if (event.altKey || event.ctrlKey || event.metaKey || event.target.closest('button, a, input, textarea, select, summary')) {
            return;
        }
        if (event.key === 'ArrowRight' && currentPage < pages.length - 1) {
            displayPage(currentPage + 1, { focus: true, persist: true });
        } else if (event.key === 'ArrowLeft' && currentPage > 0) {
            displayPage(currentPage - 1, { focus: true, persist: true });
        }
    });

    bookmarks = readStorage(bookmarkKey, []);
    bookmarks = Array.isArray(bookmarks) ? bookmarks.filter((page) => Number.isInteger(page) && page >= 0 && page < pages.length) : [];
    const savedPage = readStorage(progressKey, 0);
    displayPage(Number.isInteger(savedPage) ? savedPage : 0);
});
