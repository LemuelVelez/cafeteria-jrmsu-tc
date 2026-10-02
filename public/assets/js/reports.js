(() => {
    'use strict';
    document.querySelector('[data-print-page]')?.addEventListener('click', () => window.print());
    document.querySelectorAll('[data-sales-chart]').forEach((canvas) => {
        if (!window.Chart) return;
        try { new window.Chart(canvas, { type: 'line', data: { labels: JSON.parse(canvas.dataset.labels || '[]'), datasets: [{ label: 'Paid sales', data: JSON.parse(canvas.dataset.values || '[]') }] }, options: { responsive: true } }); } catch (_) {}
    });
})();
