(() => {
    'use strict';

    // Also used by the audit screen so both pages have a working Print action.
    document.querySelectorAll('[data-print-page]').forEach((button) => {
        button.addEventListener('click', () => window.print());
    });

    document.querySelectorAll('[data-sales-chart]').forEach((canvas) => {
        if (!window.Chart) return;
        try {
            const labels = JSON.parse(canvas.dataset.labels || '[]');
            const values = JSON.parse(canvas.dataset.values || '[]');
            new window.Chart(canvas, {
                type: 'line',
                data: {
                    labels,
                    datasets: [{ label: 'Paid sales', data: values, borderColor: '#0f766e' }],
                },
                options: { responsive: true, maintainAspectRatio: false },
            });
        } catch (error) {
            // Invalid series must not break the rest of the reports page.
            console.warn('Unable to draw daily sales chart', error);
        }
    });
})();
