(() => {
    'use strict';

    const csrfName = document.querySelector('meta[name="csrf-name"]')?.content;
    const csrfHash = document.querySelector('meta[name="csrf-hash"]')?.content;
    const appBaseUrl = document.querySelector('meta[name="app-base-url"]')?.content || `${window.location.origin}/`;

    window.cafeteriaUrl = (path = '') => new URL(String(path).replace(/^\/+/, ''), appBaseUrl).toString();

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
                || field.matches('[type="hidden"], [type="checkbox"], [type="radio"], [type="range"], [type="file"], .form-control-sm, .form-select-sm, [data-no-icon], [data-quantity], [data-cart-qty], [data-pos-qty]')
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

    const enhanceFileInputs = () => {
        const formatFileSize = (bytes) => {
            if (!Number.isFinite(bytes) || bytes < 1) return '';
            if (bytes < 1024) return `${bytes} B`;
            if (bytes < 1024 ** 2) return `${Math.round(bytes / 1024)} KB`;
            return `${(bytes / (1024 ** 2)).toFixed(bytes < 10 * 1024 ** 2 ? 1 : 0)} MB`;
        };

        const acceptedFileLabel = (field) => {
            const labels = {
                'image/png': 'PNG',
                'image/jpeg': 'JPG',
                'image/webp': 'WebP',
                'image/gif': 'GIF',
                'application/pdf': 'PDF',
            };
            const accepted = (field.accept || '')
                .split(',')
                .map((value) => value.trim().toLowerCase())
                .filter(Boolean)
                .map((value) => labels[value] || value.replace(/^\./, '').toUpperCase());

            return [...new Set(accepted)].join(', ');
        };

        const acceptsFile = (field, file) => {
            const accepted = (field.accept || '')
                .split(',')
                .map((value) => value.trim().toLowerCase())
                .filter(Boolean);
            if (!accepted.length) return true;

            const filename = file.name.toLowerCase();
            const mime = (file.type || '').toLowerCase();
            const mimeExtensions = {
                'image/png': ['.png'],
                'image/jpeg': ['.jpg', '.jpeg'],
                'image/webp': ['.webp'],
                'image/gif': ['.gif'],
                'application/pdf': ['.pdf'],
            };

            return accepted.some((rule) => (
                rule.startsWith('.')
                    ? filename.endsWith(rule)
                    : rule.endsWith('/*')
                        ? mime.startsWith(rule.slice(0, -1))
                        : mime === rule || (mimeExtensions[rule] || []).some((extension) => filename.endsWith(extension))
            ));
        };

        document.querySelectorAll('input[type="file"]').forEach((field, index) => {
            if (field.dataset.filePickerReady === 'true') return;

            if (!field.id) field.id = `filePicker${index + 1}`;
            const imagePicker = (field.accept || '').includes('image/');
            const emptyLabel = field.dataset.fileEmpty || (imagePicker ? 'No image selected' : 'No file selected');
            const chooseLabel = field.dataset.fileButton || (imagePicker ? 'Choose image' : 'Choose file');
            const changeLabel = field.dataset.fileChangeButton || (imagePicker ? 'Change image' : 'Change file');
            const acceptedLabel = acceptedFileLabel(field);
            const externalPreview = field.closest('form')?.querySelector('.settings-avatar') || null;

            const picker = document.createElement('div');
            picker.className = 'file-picker';
            picker.dataset.filePicker = '';

            field.parentNode.insertBefore(picker, field);
            picker.appendChild(field);
            field.classList.add('file-picker-input');
            field.dataset.filePickerReady = 'true';

            const label = document.createElement('label');
            label.className = 'file-picker-shell';
            label.htmlFor = field.id;

            const thumbnail = document.createElement('span');
            thumbnail.className = 'file-picker-thumbnail';
            thumbnail.setAttribute('aria-hidden', 'true');
            thumbnail.innerHTML = '<i class="bi bi-image"></i><img alt="" hidden>';

            const details = document.createElement('span');
            details.className = 'file-picker-details';

            const name = document.createElement('span');
            name.className = 'file-picker-name';
            name.textContent = emptyLabel;

            const meta = document.createElement('span');
            meta.className = 'file-picker-meta';
            meta.textContent = acceptedLabel;

            const action = document.createElement('span');
            action.className = 'file-picker-action';
            action.innerHTML = `<i class="bi bi-upload" aria-hidden="true"></i><span>${chooseLabel}</span>`;

            const clear = document.createElement('button');
            clear.className = 'file-picker-clear';
            clear.type = 'button';
            clear.hidden = true;
            clear.setAttribute('aria-label', 'Remove selected file');
            clear.innerHTML = '<i class="bi bi-x-lg" aria-hidden="true"></i>';

            const status = document.createElement('span');
            status.className = 'visually-hidden';
            status.setAttribute('aria-live', 'polite');

            details.append(name, meta);
            label.append(thumbnail, details, action);
            picker.append(label, clear, status);

            const previewImage = thumbnail.querySelector('img');
            const previewIcon = thumbnail.querySelector('i');
            const actionText = action.querySelector('span');
            let objectUrl = '';

            if (externalPreview && !externalPreview.dataset.originalSrc) {
                externalPreview.dataset.originalSrc = externalPreview.currentSrc || externalPreview.src;
            }

            const resetPreview = () => {
                if (objectUrl) URL.revokeObjectURL(objectUrl);
                objectUrl = '';
                previewImage.hidden = true;
                previewImage.removeAttribute('src');
                previewIcon.hidden = false;
                if (externalPreview?.dataset.originalSrc) externalPreview.src = externalPreview.dataset.originalSrc;
            };

            const update = () => {
                const files = [...(field.files || [])];
                const file = files[0];
                picker.classList.remove('is-invalid', 'has-file');
                field.setCustomValidity('');
                resetPreview();

                if (!file) {
                    name.textContent = emptyLabel;
                    meta.textContent = acceptedLabel;
                    actionText.textContent = chooseLabel;
                    clear.hidden = true;
                    status.textContent = emptyLabel;
                    return;
                }

                if (!acceptsFile(field, file)) {
                    field.value = '';
                    field.setCustomValidity('Choose a supported file type.');
                    picker.classList.add('is-invalid');
                    name.textContent = 'Unsupported file type';
                    meta.textContent = acceptedLabel;
                    actionText.textContent = chooseLabel;
                    clear.hidden = true;
                    status.textContent = 'Unsupported file type';
                    return;
                }

                picker.classList.add('has-file');
                name.textContent = files.length > 1 ? `${files.length} files selected` : file.name;
                meta.textContent = files.length > 1
                    ? files.map((item) => formatFileSize(item.size)).filter(Boolean).join(' · ')
                    : formatFileSize(file.size);
                actionText.textContent = changeLabel;
                clear.hidden = false;
                status.textContent = `${file.name} selected`;

                if (file.type.startsWith('image/')) {
                    objectUrl = URL.createObjectURL(file);
                    previewImage.src = objectUrl;
                    previewImage.hidden = false;
                    previewIcon.hidden = true;
                    if (externalPreview) externalPreview.src = objectUrl;
                }
            };

            field.addEventListener('change', update);
            field.form?.addEventListener('reset', () => window.requestAnimationFrame(update));

            clear.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                field.value = '';
                update();
                field.focus({ preventScroll: true });
            });

            ['dragenter', 'dragover'].forEach((eventName) => {
                picker.addEventListener(eventName, (event) => {
                    event.preventDefault();
                    if (!field.disabled) picker.classList.add('is-dragging');
                });
            });

            ['dragleave', 'drop'].forEach((eventName) => {
                picker.addEventListener(eventName, (event) => {
                    event.preventDefault();
                    picker.classList.remove('is-dragging');
                });
            });

            picker.addEventListener('drop', (event) => {
                if (field.disabled || !event.dataTransfer?.files.length) return;

                const transfer = new DataTransfer();
                [...event.dataTransfer.files]
                    .slice(0, field.multiple ? undefined : 1)
                    .forEach((file) => transfer.items.add(file));
                field.files = transfer.files;
                field.dispatchEvent(new Event('change', { bubbles: true }));
            });

            update();
        });

        document.addEventListener('shown.bs.modal', (event) => {
            event.target.querySelectorAll('input[type="file"]').forEach((field) => {
                field.dispatchEvent(new Event('change', { bubbles: true }));
            });
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
    enhanceFileInputs();
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
        const refresh = () => window.cafeteriaFetch(window.cafeteriaUrl('api/orders/pending-count'))
            .then(({ data }) => {
                pendingBadge.textContent = data.count;
                pendingBadge.hidden = data.count < 1;
            })
            .catch(() => {});
        refresh();
        window.setInterval(refresh, 60000);
    }
})();
