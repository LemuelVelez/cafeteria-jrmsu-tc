(() => {
    'use strict';
    document.querySelectorAll('[data-reveal-customer-phone]').forEach((button) => button.addEventListener('click', async () => {
        try {
            const { data } = await window.cafeteriaFetch(window.cafeteriaUrl(`admin/customers/${button.dataset.revealCustomerPhone}/reveal-phone`));
            const cell = button.closest('td'); const value = cell?.querySelector('[data-customer-phone]'); if (value) value.textContent = data.phone || '—'; button.remove();
        } catch (error) { alert(error.message || 'Unable to reveal phone number.'); }
    }));
})();
