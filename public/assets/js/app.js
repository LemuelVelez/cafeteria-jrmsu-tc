(() => {
    'use strict';

    const csrfName = document.querySelector('meta[name="csrf-name"]')?.content;
    const csrfHash = document.querySelector('meta[name="csrf-hash"]')?.content;

    window.cafeteriaPaymentMode = (container, orderType) => {
        const modes = JSON.parse(container?.dataset.paymentModes || '{}');
        return modes[orderType] || modes.pickup || { value: '', label: '' };
    };

    window.cafeteriaFetch = async (url, options = {}) => {
        const headers = new Headers(options.headers || {});
        headers.set('Accept', 'application/json');
        if (csrfName && csrfHash) headers.set('X-CSRF-TOKEN', csrfHash);
        if (options.body && typeof options.body === 'string') headers.set('Content-Type', 'application/json');
        const response = await fetch(url, { ...options, headers });
        const data = await response.json().catch(() => ({ success: false, message: 'Invalid server response.' }));
        if (!response.ok || data.success === false) throw new Error(data.message || 'Request failed.');
        return data;
    };

    document.querySelectorAll('[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (!window.confirm(form.dataset.confirm || 'Continue with this action?')) event.preventDefault();
        });
    });

    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((element) => new bootstrap.Tooltip(element));

    const pendingBadge = document.querySelector('[data-pending-orders]');
    if (pendingBadge) {
        const refresh = () => window.cafeteriaFetch('/api/orders/pending-count')
            .then(({ data }) => {
                pendingBadge.textContent = data.count;
                pendingBadge.hidden = data.count < 1;
            })
            .catch(() => {});
        refresh();
        window.setInterval(refresh, 60000);
    }
})();
