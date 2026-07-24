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

    const enhancePasswordFields = () => {
        document.querySelectorAll('input[type="password"]')
            .forEach((field) => {
                if (field.dataset.passwordToggleReady === 'true') return;

                const container = field.closest('.input-group, .field-icon');
                if (!container) return;

                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'password-toggle';
                button.setAttribute('aria-label', 'Show password');
                button.setAttribute('aria-pressed', 'false');

                const icon = document.createElement('i');
                icon.className = 'bi bi-eye';
                icon.setAttribute('aria-hidden', 'true');
                button.appendChild(icon);

                if (container.classList.contains('input-group')) {
                    button.classList.add('password-toggle-group');
                } else {
                    container.classList.add('has-password-toggle');
                    button.classList.add('password-toggle-overlay');
                }

                button.addEventListener('click', () => {
                    const reveal = field.type === 'password';
                    field.type = reveal ? 'text' : 'password';
                    icon.className = `bi ${reveal ? 'bi-eye-slash' : 'bi-eye'}`;
                    button.setAttribute('aria-label', reveal ? 'Hide password' : 'Show password');
                    button.setAttribute('aria-pressed', reveal ? 'true' : 'false');
                    field.focus({ preventScroll: true });
                });

                field.dataset.passwordToggleReady = 'true';
                container.appendChild(button);
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

    const confirmationOptions = (source = null) => {
        const message = source?.dataset.confirm || 'Continue with this action?';
        const isDangerous = /delete|remove|cancel|ban|sign out|log out|deactivate/i.test(message);
        return {
            title: source?.dataset.confirmTitle || 'Please confirm',
            confirmLabel: source?.dataset.confirmLabel || (isDangerous ? 'Confirm' : 'Continue'),
            confirmClass: source?.dataset.confirmClass || (isDangerous ? 'btn-danger' : 'btn-primary'),
        };
    };

    const getConfirmationDialog = () => {
        let element = document.getElementById('confirmationDialog');
        if (!element) {
            element = document.createElement('dialog');
            element.className = 'confirmation-dialog';
            element.id = 'confirmationDialog';
            element.setAttribute('aria-labelledby', 'confirmationDialogTitle');
            element.setAttribute('aria-describedby', 'confirmationDialogMessage');
            element.innerHTML = `
                <div class="confirmation-dialog-panel">
                    <div class="confirmation-dialog-header">
                        <h2 class="h5 mb-0" id="confirmationDialogTitle">Please confirm</h2>
                        <button class="btn-close" type="button" data-confirm-cancel aria-label="Close"></button>
                    </div>
                    <div class="confirmation-dialog-body">
                        <span class="confirmation-icon" aria-hidden="true"><i class="bi bi-exclamation-lg"></i></span>
                        <p class="mb-0" id="confirmationDialogMessage"></p>
                    </div>
                    <div class="confirmation-dialog-footer">
                        <button class="btn btn-light" type="button" data-confirm-cancel>Cancel</button>
                        <button class="btn btn-primary" type="button" data-confirm-accept>Continue</button>
                    </div>
                </div>`;
            document.body.appendChild(element);
        }

        return element;
    };

    window.cafeteriaConfirm = (message = 'Continue with this action?', options = {}) => new Promise((resolve) => {
        const element = getConfirmationDialog();
        if (typeof element.showModal !== 'function') {
            resolve(window.confirm(message));
            return;
        }

        if (element.open) {
            resolve(false);
            return;
        }

        const host = document.querySelector('.modal.show, .offcanvas.show') || document.body;
        if (element.parentElement !== host) host.appendChild(element);

        const title = element.querySelector('#confirmationDialogTitle');
        const messageNode = element.querySelector('#confirmationDialogMessage');
        const acceptButton = element.querySelector('[data-confirm-accept]');
        const cancelButtons = element.querySelectorAll('[data-confirm-cancel]');
        const icon = element.querySelector('.confirmation-icon');
        let settled = false;

        title.textContent = options.title || 'Please confirm';
        messageNode.textContent = message;
        acceptButton.textContent = options.confirmLabel || 'Continue';
        acceptButton.className = `btn ${options.confirmClass || 'btn-primary'}`;
        icon.classList.toggle('is-danger', (options.confirmClass || '').includes('danger'));

        const finish = (accepted) => {
            if (settled) return;
            settled = true;
            resolve(accepted);
        };
        const closeDialog = (value) => {
            if (element.open) element.close(value);
        };

        acceptButton.onclick = () => closeDialog('confirm');
        cancelButtons.forEach((button) => { button.onclick = () => closeDialog('cancel'); });
        element.oncancel = (event) => {
            event.preventDefault();
            closeDialog('cancel');
        };
        element.onclose = () => {
            finish(element.returnValue === 'confirm');
            acceptButton.onclick = null;
            cancelButtons.forEach((button) => { button.onclick = null; });
            element.oncancel = null;
            element.onclose = null;
            if (element.parentElement !== document.body) document.body.appendChild(element);
        };

        element.showModal();
        acceptButton.focus();
    });

    enhanceFormFields();
    enhancePasswordFields();
    enhanceButtons();
    enhanceResponsiveTables();

    document.addEventListener('submit', async (event) => {
        const form = event.target.closest('form[data-confirm]');
        if (!form) return;
        if (form.dataset.confirmBypass === 'true') {
            delete form.dataset.confirmBypass;
            return;
        }

        event.preventDefault();
        const source = event.submitter || form;
        const accepted = await window.cafeteriaConfirm(
            source.dataset.confirm || form.dataset.confirm || 'Continue with this action?',
            confirmationOptions(source.dataset.confirm ? source : form),
        );
        if (!accepted) return;

        form.dataset.confirmBypass = 'true';
        if (event.submitter && !event.submitter.disabled) form.requestSubmit(event.submitter);
        else form.requestSubmit();
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
