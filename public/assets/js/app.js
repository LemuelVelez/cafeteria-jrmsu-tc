(() => {
    'use strict';

    const csrfName = document.querySelector('meta[name="csrf-name"]')?.content;
    const csrfHash = document.querySelector('meta[name="csrf-hash"]')?.content;

    const fieldIcon = (field) => {
        const name = `${field.name || ''} ${field.id || ''} ${field.getAttribute('placeholder') || ''}`.toLowerCase();
        const type = (field.getAttribute('type') || '').toLowerCase();

        const namedIcons = [
            [/password/, 'bi-lock'],
            [/email/, 'bi-envelope'],
            [/phone|contact|tel/, 'bi-telephone'],
            [/address|location|landmark/, 'bi-geo-alt'],
            [/customer|user|rider|name/, 'bi-person'],
            [/category/, 'bi-tags'],
            [/price|fee|amount|total|payment/, 'bi-cash-coin'],
            [/stock|quantity|qty/, 'bi-box-seam'],
            [/promo|coupon|discount|code/, 'bi-ticket-perforated'],
            [/rating|review/, 'bi-star'],
            [/status|enabled|available/, 'bi-toggle-on'],
            [/sort|order/, 'bi-sort-numeric-down'],
            [/date|from|to/, 'bi-calendar-event'],
            [/time|hours/, 'bi-clock'],
            [/image|photo|media/, 'bi-image'],
            [/description|comment|notes|instructions/, 'bi-card-text'],
            [/search/, 'bi-search'],
        ];

        const matched = namedIcons.find(([pattern]) => pattern.test(name));
        if (matched) return matched[1];

        return {
            email: 'bi-envelope',
            password: 'bi-lock',
            tel: 'bi-telephone',
            date: 'bi-calendar-event',
            time: 'bi-clock',
            number: 'bi-123',
            file: 'bi-image',
            search: 'bi-search',
        }[type] || (field.tagName === 'SELECT' ? 'bi-list-ul' : field.tagName === 'TEXTAREA' ? 'bi-card-text' : 'bi-pencil');
    };

    const enhanceFormFields = () => {
        const selector = 'input.form-control, select.form-select, textarea.form-control';
        document.querySelectorAll(selector).forEach((field) => {
            if (
                field.closest('.input-group, .field-icon')
                || field.matches('[type="hidden"], [type="checkbox"], [type="radio"], [type="range"], .form-control-sm, .form-select-sm, [data-no-icon], [data-quantity], [data-cart-qty], [data-pos-qty]')
            ) return;

            const wrapper = document.createElement('div');
            wrapper.className = 'field-icon';
            field.parentNode.insertBefore(wrapper, field);
            wrapper.appendChild(field);

            const icon = document.createElement('i');
            icon.className = `bi ${fieldIcon(field)} field-icon-symbol`;
            icon.setAttribute('aria-hidden', 'true');
            wrapper.prepend(icon);
        });
    };

    const buttonIcon = (label) => {
        const rules = [
            [/sign out|log out/i, 'bi-box-arrow-right'],
            [/sign in|log in/i, 'bi-box-arrow-in-right'],
            [/create|new|add/i, 'bi-plus-lg'],
            [/save|update/i, 'bi-check2-circle'],
            [/submit|send/i, 'bi-send'],
            [/apply|filter/i, 'bi-funnel'],
            [/cancel|close/i, 'bi-x-lg'],
            [/delete|remove/i, 'bi-trash'],
            [/place order|complete order/i, 'bi-bag-check'],
            [/open|view/i, 'bi-arrow-right'],
            [/browse|search/i, 'bi-search'],
            [/all/i, 'bi-grid'],
        ];
        return rules.find(([pattern]) => pattern.test(label))?.[1] || null;
    };

    const enhanceButtons = () => {
        const accessibleLabels = {
            'bi-pencil': 'Edit',
            'bi-trash': 'Delete',
            'bi-x': 'Remove',
            'bi-list': 'Open navigation',
            'bi-basket': 'Open cart',
        };

        document.querySelectorAll('button.btn, a.btn').forEach((button) => {
            const existingIcon = button.querySelector('.bi');
            if (existingIcon && !button.textContent.trim() && !button.hasAttribute('aria-label')) {
                const iconClass = [...existingIcon.classList].find((className) => accessibleLabels[className]);
                if (iconClass) button.setAttribute('aria-label', accessibleLabels[iconClass]);
            }

            if (existingIcon || button.matches('.btn-close, [data-no-icon]')) return;
            const iconName = buttonIcon(button.textContent.trim());
            if (!iconName) return;

            const icon = document.createElement('i');
            icon.className = `bi ${iconName} me-1`;
            icon.setAttribute('aria-hidden', 'true');
            button.prepend(icon);
        });
    };

    const enhanceResponsiveTables = () => {
        document.querySelectorAll('.table-responsive table').forEach((table) => {
            table.classList.add('responsive-table');
            const headings = [...table.querySelectorAll('thead th')].map((heading) => heading.textContent.trim());
            table.querySelectorAll('tbody tr').forEach((row) => {
                [...row.children].forEach((cell, index) => {
                    if (cell.tagName === 'TD' && !cell.hasAttribute('colspan')) {
                        cell.dataset.label = headings[index] || '';
                    }
                });
            });
        });
    };

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

    enhanceFormFields();
    enhanceButtons();
    enhanceResponsiveTables();

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
