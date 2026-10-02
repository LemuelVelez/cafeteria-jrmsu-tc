(() => {
    'use strict';
    const canvas = document.getElementById('revenueChart');
    const source = document.getElementById('dashboardRevenueData');
    if (!canvas || !source || !window.Chart) return;
    try {
        const rows = JSON.parse(source.textContent || '[]');
        new window.Chart(canvas, { type: 'line', data: { labels: rows.map((row) => row.day), datasets: [{ label: 'Paid revenue', data: rows.map((row) => Number(row.revenue || 0)) }] }, options: { responsive: true } });
    } catch (_) {}
})();
