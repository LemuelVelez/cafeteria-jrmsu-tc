/* Capture error events: IMG error does not bubble. Never use inline handlers. */
(() => {
    'use strict';
    const logo = document.querySelector('meta[name="cafeteria-logo-url"]')?.content || '';
    document.addEventListener('error', (event) => {
        const image = event.target;
        if (!(image instanceof HTMLImageElement) || !image.hasAttribute('data-fallback-src')) return;
        const fallback = image.dataset.fallbackSrc;
        const stage = image.dataset.fallbackStage || 'primary';
        if (stage === 'primary' && fallback && image.src !== fallback) {
            image.dataset.fallbackStage = 'category';
            image.src = fallback;
        } else if (stage !== 'logo' && logo) {
            image.dataset.fallbackStage = 'logo';
            image.src = logo;
        }
    }, true);
})();
