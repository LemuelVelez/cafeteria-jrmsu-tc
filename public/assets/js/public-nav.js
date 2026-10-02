(() => {
    const navbar = document.querySelector('[data-public-navbar]');
    if (!navbar) return;

    const hero = document.querySelector('.hero-section');
    const sectionLinks = [...navbar.querySelectorAll('[data-public-section]')];

    const setActiveSection = (activeSection) => {
        sectionLinks.forEach((link) => {
            const isActive = link.dataset.publicSection === activeSection;
            link.classList.toggle('is-active', isActive);
            if (isActive) link.setAttribute('aria-current', 'location');
            else if (link.getAttribute('aria-current') === 'location') link.removeAttribute('aria-current');
        });
    };

    const updateNavbar = () => {
        if (navbar.classList.contains('public-navbar--overlay') && hero) {
            navbar.classList.toggle('is-scrolled', hero.getBoundingClientRect().bottom <= navbar.offsetHeight + 8);
        }

        if (!sectionLinks.length) return;

        const activationPoint = window.scrollY + navbar.offsetHeight + 48;
        let activeSection = '';
        let activeSectionTop = -Infinity;

        sectionLinks.forEach((link) => {
            const section = document.getElementById(link.dataset.publicSection || '');
            if (!section) return;

            const sectionTop = window.scrollY + section.getBoundingClientRect().top;
            if (sectionTop <= activationPoint && sectionTop > activeSectionTop) {
                activeSection = section.id;
                activeSectionTop = sectionTop;
            }
        });

        setActiveSection(activeSection);
    };

    let frame = null;
    const requestUpdate = () => {
        if (frame !== null) return;
        frame = window.requestAnimationFrame(() => {
            frame = null;
            updateNavbar();
        });
    };

    sectionLinks.forEach((link) => {
        link.addEventListener('click', () => {
            const sectionId = link.dataset.publicSection || '';
            if (document.getElementById(sectionId)) setActiveSection(sectionId);
        });
    });

    window.addEventListener('scroll', requestUpdate, { passive: true });
    window.addEventListener('resize', requestUpdate, { passive: true });
    window.addEventListener('hashchange', requestUpdate);
    window.addEventListener('load', requestUpdate);
    updateNavbar();
})();
