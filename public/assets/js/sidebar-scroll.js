(() => {
    'use strict';

    // A full page navigation rebuilds the sidebar. Remember the position of
    // both menus within the tab so selecting a lower menu item does not snap
    // the list back to its beginning on every page transition.
    const base = document.querySelector('meta[name="app-base-url"]')?.content || location.origin;
    const role = document.body?.dataset.sidebarRole || 'user';
    const storagePrefix = `jrmsu-sidebar:${base}:${role}:`;
    const desktop = document.querySelector('[data-persistent-sidebar]');
    const mobile = document.querySelector('[data-mobile-sidebar-scroll]');

    const stored = (key) => {
        try {
            const value = Number(sessionStorage.getItem(storagePrefix + key));
            return Number.isFinite(value) && value > 0 ? value : 0;
        } catch {
            return 0;
        }
    };
    const save = (element, key) => {
        if (!element) return;
        try { sessionStorage.setItem(storagePrefix + key, String(element.scrollTop)); } catch { /* storage disabled */ }
    };
    const restore = (element, key) => {
        if (!element) return;
        element.scrollTop = stored(key);
    };

    if (desktop) {
        restore(desktop, 'desktop');
        desktop.addEventListener('scroll', () => save(desktop, 'desktop'), { passive: true });
        desktop.querySelectorAll('a[href]').forEach((link) => {
            link.addEventListener('click', () => save(desktop, 'desktop'));
        });
    }
    if (mobile) {
        const menu = document.getElementById('mobileSidebar');
        menu?.addEventListener('shown.bs.offcanvas', () => restore(mobile, 'mobile'));
        mobile.addEventListener('scroll', () => save(mobile, 'mobile'), { passive: true });
        mobile.querySelectorAll('a[href]').forEach((link) => {
            link.addEventListener('click', () => save(mobile, 'mobile'));
        });
    }
    window.addEventListener('pagehide', () => {
        save(desktop, 'desktop');
        save(mobile, 'mobile');
    });
    window.addEventListener('pageshow', (event) => {
        if (event.persisted) restore(desktop, 'desktop');
    });
})();
