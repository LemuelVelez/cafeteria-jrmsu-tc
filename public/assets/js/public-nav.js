(() => {
    const navbar = document.querySelector('[data-public-navbar]');
    if (!navbar) return;

    const hero = document.querySelector('.hero-section');
    const sectionLinks = [...navbar.querySelectorAll('[data-public-section]')];

    const updateNavbar = () => {
        if (navbar.classList.contains('public-navbar--overlay') && hero) {
            navbar.classList.toggle('is-scrolled', hero.getBoundingClientRect().bottom <= navbar.offsetHeight + 8);
        }

        if (!sectionLinks.length) return;

        const activationLine = navbar.offsetHeight + 120;
        let activeSection = '';

        sectionLinks.forEach((link) => {
            const section = document.getElementById(link.dataset.publicSection || '');
            if (section && section.getBoundingClientRect().top <= activationLine) {
                activeSection = section.id;
            }
        });

        sectionLinks.forEach((link) => {
            const isActive = link.dataset.publicSection === activeSection;
            link.classList.toggle('is-active', isActive);
            if (isActive) link.setAttribute('aria-current', 'location');
            else if (link.getAttribute('aria-current') === 'location') link.removeAttribute('aria-current');
        });
    };

    let frame = null;
    const requestUpdate = () => {
        if (frame !== null) return;
        frame = window.requestAnimationFrame(() => {
            frame = null;
            updateNavbar();
        });
    };

    window.addEventListener('scroll', requestUpdate, { passive: true });
    window.addEventListener('resize', requestUpdate, { passive: true });
    window.addEventListener('hashchange', requestUpdate);
    updateNavbar();
})();
