const menuToggle = document.querySelector('.menu-toggle');
const navigationMenu = document.querySelector('#primary-menu');

if (menuToggle && navigationMenu) {
    const closeMenu = () => {
        menuToggle.setAttribute('aria-expanded', 'false');
        menuToggle.setAttribute('aria-label', 'Buka menu navigasi');
        navigationMenu.classList.remove('is-open');
        document.body.classList.remove('menu-open');
    };

    menuToggle.addEventListener('click', () => {
        const isOpen = menuToggle.getAttribute('aria-expanded') === 'true';
        menuToggle.setAttribute('aria-expanded', String(!isOpen));
        menuToggle.setAttribute('aria-label', isOpen ? 'Buka menu navigasi' : 'Tutup menu navigasi');
        navigationMenu.classList.toggle('is-open', !isOpen);
        document.body.classList.toggle('menu-open', !isOpen);
    });

    navigationMenu.querySelectorAll('a').forEach((link) => {
        link.addEventListener('click', closeMenu);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeMenu();
        }
    });
}

document.querySelectorAll('.featured-image-media').forEach((image) => {
    const showPlaceholder = () => {
        image.hidden = true;
        const placeholder = image.parentElement?.querySelector('.image-placeholder');

        if (placeholder) {
            placeholder.hidden = false;
        }
    };

    image.addEventListener('error', showPlaceholder);

    if (image.complete && image.naturalWidth === 0) {
        showPlaceholder();
    }
});

document.querySelectorAll('[data-destination-photo-open]').forEach((openButton) => {
    const dialog = document.querySelector('[data-destination-photo-dialog]');
    const closeButton = dialog?.querySelector('[data-destination-photo-close]');

    if (!dialog || !closeButton || typeof dialog.showModal !== 'function') {
        return;
    }

    let closeTimer;

    const closeDialog = () => {
        if (!dialog.open || dialog.classList.contains('is-closing')) {
            return;
        }

        dialog.classList.remove('is-open');
        dialog.classList.add('is-closing');

        closeTimer = window.setTimeout(() => {
            if (dialog.open) {
                dialog.close();
            }
        }, window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 180);
    };

    openButton.addEventListener('click', () => {
        window.clearTimeout(closeTimer);
        dialog.classList.remove('is-closing');
        dialog.showModal();
        window.requestAnimationFrame(() => dialog.classList.add('is-open'));
    });

    closeButton.addEventListener('click', closeDialog);
    dialog.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            event.preventDefault();
            closeDialog();
        }
    });
    dialog.addEventListener('cancel', (event) => {
        event.preventDefault();
        closeDialog();
    });
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) {
            closeDialog();
        }
    });
    dialog.addEventListener('close', () => {
        dialog.classList.remove('is-open', 'is-closing');
    });
});

document.querySelectorAll('.destination-detail-link').forEach((link) => {
    link.addEventListener('click', () => {
        sessionStorage.setItem('destination-list-scroll', String(window.scrollY));
    });
});

window.addEventListener('pageshow', () => {
    if (window.location.pathname !== '/') {
        return;
    }

    const savedScroll = sessionStorage.getItem('destination-list-scroll');

    if (savedScroll === null) {
        return;
    }

    sessionStorage.removeItem('destination-list-scroll');
    window.requestAnimationFrame(() => window.scrollTo(0, Number(savedScroll)));
});

if (document.body.classList.contains('home-page') && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    document.body.classList.add('motion-ready');
    const revealSelectors = [
        '.hero-content > .eyebrow',
        '.hero-content > h1',
        '.hero-content > .hero-description',
        '.hero-content > .hero-search',
        '.hero-content > .button',
        '.section-heading > div > *',
        '.section-heading > .section-heading-link',
        '.intro-copy',
        '.intro-aside',
        '.stats-band > *',
        '.category-card',
        '.featured-card',
        '.curation-note',
        '.story-copy > *',
        '.story-image',
        '.digital-book-card',
        '.travel-card',
    ];
    const revealItems = document.querySelectorAll(`.home-page main ${revealSelectors.join(', .home-page main ')}`);
    const revealTargets = [...revealItems, ...document.querySelectorAll('.home-page main .heritage-card')];

    if ('IntersectionObserver' in window && revealTargets.length) {
        const revealObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.12, rootMargin: '0px 0px -36px 0px' });

        revealTargets.forEach((item) => {
            const stagger = Math.min([...item.parentElement.children].indexOf(item), 5);

            item.classList.add(item.matches('.heritage-card') ? 'motion-fade' : 'motion-reveal');
            item.style.setProperty('--reveal-delay', `${stagger * 70}ms`);
            revealObserver.observe(item);
        });
    }

    document.querySelectorAll('.home-page main img').forEach((image) => {
        const markLoaded = () => image.classList.add('image-loaded');
        const markFailed = () => image.classList.remove('motion-image');

        image.classList.add('motion-image');
        image.addEventListener('load', markLoaded, { once: true });
        image.addEventListener('error', markFailed, { once: true });

        if (image.complete && image.naturalWidth > 0) {
            markLoaded();
        }
    });

    const hero = document.querySelector('.home-page .hero');
    const heroBackdrop = hero?.querySelector('.hero-backdrop');
    let parallaxFrame = 0;

    if (hero && heroBackdrop) {
        const updateParallax = () => {
            parallaxFrame = 0;
            const heroBottom = hero.offsetTop + hero.offsetHeight;
            const progress = Math.min(Math.max(window.scrollY, 0), heroBottom) * 0.12;

            heroBackdrop.style.setProperty('--hero-parallax-y', `${-progress}px`);
        };

        const queueParallaxUpdate = () => {
            if (!parallaxFrame) {
                parallaxFrame = window.requestAnimationFrame(updateParallax);
            }
        };

        window.addEventListener('scroll', queueParallaxUpdate, { passive: true });
        window.addEventListener('resize', queueParallaxUpdate, { passive: true });
        queueParallaxUpdate();
    }
}
