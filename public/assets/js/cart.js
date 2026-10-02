(() => {
    'use strict';

    class CartStore {
        constructor(key = 'jrmsu-cafeteria-cart') {
            this.key = key;
            this.requestTokenKey = `${key}:request-token`;
            try {
                const storedItems = JSON.parse(localStorage.getItem(key) || '[]');
                const source = Array.isArray(storedItems) ? storedItems : [];
                this.items = source.map((item) => this.normalizeItem(item)).filter(Boolean);
                if (JSON.stringify(this.items) !== JSON.stringify(source)) {
                    localStorage.setItem(this.key, JSON.stringify(this.items));
                    localStorage.removeItem(this.requestTokenKey);
                }
            } catch (error) {
                console.warn('Unable to read the saved cart. The invalid cart data was cleared.', error);
                this.items = [];
                localStorage.removeItem(key);
                localStorage.removeItem(this.requestTokenKey);
            }
        }
        normalizeItem(item) {
            if (!item || typeof item !== 'object' || Array.isArray(item)) return null;

            const productId = Number(item.product_id);
            const price = Number(item.price);
            const stock = Number(item.stock);
            if (!Number.isInteger(productId) || productId < 1 || !Number.isFinite(price) || price < 0) return null;

            const normalizedStock = Number.isFinite(stock) && stock >= 0 ? Math.floor(stock) : 0;
            const quantity = Math.min(99, Math.max(1, Math.floor(Number(item.quantity) || 1)));
            const addons = Array.isArray(item.addons) ? item.addons.map((addon) => {
                if (!addon || typeof addon !== 'object') return null;
                const id = Number(addon.id);
                const addonPrice = Number(addon.price);
                if (!Number.isInteger(id) || id < 1 || !Number.isFinite(addonPrice) || addonPrice < 0) return null;
                return { id, name: String(addon.name || '').slice(0, 100), price: addonPrice };
            }).filter(Boolean) : [];

            return {
                ...item,
                product_id: productId,
                name: String(item.name || 'Product').slice(0, 120),
                price,
                stock: normalizedStock,
                quantity,
                image: String(item.image || '').slice(0, 2000),
                addons,
                addon_total: addons.reduce((sum, addon) => sum + addon.price, 0),
                notes: String(item.notes || '').slice(0, 500),
                available: item.available !== false,
                addon_invalid: Boolean(item.addon_invalid),
            };
        }
        save() {
            localStorage.setItem(this.key, JSON.stringify(this.items));
            localStorage.removeItem(this.requestTokenKey);
            this.updateBadges();
            window.dispatchEvent(new CustomEvent('jrmsu:cart-updated'));
        }
        requestToken() {
            let token = localStorage.getItem(this.requestTokenKey) || '';
            if (!/^[A-Za-z0-9_-]{16,64}$/.test(token)) {
                token = typeof crypto?.randomUUID === 'function'
                    ? crypto.randomUUID().replace(/-/g, '')
                    : Array.from(crypto.getRandomValues(new Uint8Array(16)), (value) => value.toString(16).padStart(2, '0')).join('');
                localStorage.setItem(this.requestTokenKey, token);
            }
            return token;
        }
        validationError() {
            const requestedByProduct = new Map();
            for (const line of this.items) {
                if (line.available === false) return `${line.name || 'A product'} is no longer available. Remove it from the cart before continuing.`;
                if (line.addon_invalid) return `An add-on selected for ${line.name || 'a product'} is no longer available. Remove and re-add that item before continuing.`;

                const productId = Number(line.product_id);
                const requested = (requestedByProduct.get(productId) || 0) + Number(line.quantity || 0);
                requestedByProduct.set(productId, requested);
                if (requested > Number(line.stock || 0)) {
                    return `Only ${Math.max(0, Number(line.stock || 0))} unit(s) of ${line.name || 'this product'} are currently available.`;
                }
            }
            return '';
        }
        async refreshProducts() {
            if (typeof window.cafeteriaFetch !== 'function' || !this.items.length) return this.validationError();

            const response = await window.cafeteriaFetch(window.cafeteriaUrl('api/products'));
            const products = Array.isArray(response?.data) ? response.data : [];
            const productMap = new Map(products.map((product) => [Number(product.id), product]));
            let changed = false;

            this.items.forEach((line) => {
                const product = productMap.get(Number(line.product_id));
                if (!product) {
                    if (line.available !== false) changed = true;
                    line.available = false;
                    return;
                }

                const serverPrice = Number(product.price);
                const serverStock = Math.max(0, Math.floor(Number(product.stock) || 0));
                const serverName = String(product.name || line.name || 'Product').slice(0, 120);
                const serverImage = String(product.image_url || line.image || '').slice(0, 2000);
                if (line.available === false || line.price !== serverPrice || line.stock !== serverStock || line.name !== serverName || line.image !== serverImage) changed = true;
                line.available = true;
                line.price = serverPrice;
                line.stock = serverStock;
                line.name = serverName;
                line.image = serverImage;

                const addonMap = new Map((Array.isArray(product.addons) ? product.addons : []).map((addon) => [Number(addon.id), addon]));
                let addonInvalid = false;
                line.addons = (Array.isArray(line.addons) ? line.addons : []).map((selected) => {
                    const current = addonMap.get(Number(selected.id));
                    if (!current) {
                        addonInvalid = true;
                        return selected;
                    }
                    const normalized = { id: Number(current.id), name: String(current.name || '').slice(0, 100), price: Number(current.price || 0) };
                    if (selected.name !== normalized.name || Number(selected.price) !== normalized.price) changed = true;
                    return normalized;
                });
                if (line.addon_invalid !== addonInvalid) changed = true;
                line.addon_invalid = addonInvalid;
                const addonTotal = line.addons.reduce((sum, addon) => sum + Number(addon.price || 0), 0);
                if (Number(line.addon_total || 0) !== addonTotal) changed = true;
                line.addon_total = addonTotal;
            });

            if (changed) {
                localStorage.setItem(this.key, JSON.stringify(this.items));
                localStorage.removeItem(this.requestTokenKey);
                this.updateBadges();
                window.dispatchEvent(new CustomEvent('jrmsu:cart-updated'));
            }

            return this.validationError();
        }
        add(item) {
            const productId = Number(item.product_id || 0);
            const stock = Math.max(0, Number(item.stock || 0));
            const requested = Math.min(99, Math.max(1, Number(item.quantity) || 1));
            const inCart = this.items
                .filter((line) => Number(line.product_id) === productId)
                .reduce((sum, line) => sum + Number(line.quantity || 0), 0);
            const signature = JSON.stringify(item.addons || []);
            const found = this.items.find((line) => Number(line.product_id) === productId && JSON.stringify(line.addons || []) === signature);
            const lineCapacity = found ? Math.max(0, 99 - Number(found.quantity || 0)) : 99;
            const available = Math.max(0, stock - inCart);
            const quantity = Math.min(requested, available, lineCapacity);
            if (productId < 1 || quantity < 1) return false;

            if (found) found.quantity = Number(found.quantity || 0) + quantity;
            else this.items.push({ ...item, product_id: productId, stock, quantity });
            this.save();
            return quantity === requested;
        }
        remove(index) { this.items.splice(index, 1); this.save(); }
        note(index, value) {
            const line = this.items[index];
            if (!line) return;
            line.notes = String(value || '').slice(0, 500);
            this.save();
        }
        quantity(index, value) {
            const line = this.items[index];
            if (!line) return 0;

            const productId = Number(line.product_id || 0);
            const otherQuantity = this.items
                .filter((item, itemIndex) => itemIndex !== index && Number(item.product_id) === productId)
                .reduce((sum, item) => sum + Number(item.quantity || 0), 0);
            const stock = Math.max(1, Number(line.stock || 99));
            const maximum = Math.max(1, Math.min(99, stock - otherQuantity));
            line.quantity = Math.min(maximum, Math.max(1, Number(value) || 1));
            this.save();
            return line.quantity;
        }
        clear() { this.items = []; this.save(); }
        subtotal() { return this.items.reduce((sum, line) => sum + ((Number(line.price) + Number(line.addon_total || 0)) * Number(line.quantity)), 0); }
        updateBadges() {
            const count = this.items.reduce((sum, line) => sum + Number(line.quantity), 0);
            document.querySelectorAll('[data-cart-count]').forEach((node) => { node.textContent = count; node.hidden = count < 1; });
        }
    }

    const cart = new CartStore(document.body.dataset.cartKey || 'jrmsu-cafeteria-cart');
    const productImagesData = document.querySelector('[data-cart-product-images]');

    if (productImagesData) {
        try {
            const productImages = JSON.parse(productImagesData.textContent || '{}');
            let updated = false;

            cart.items.forEach((line) => {
                const image = productImages[String(line.product_id)];
                if (image && line.image !== image) {
                    line.image = image;
                    updated = true;
                }
            });

            if (updated) {
                localStorage.setItem(cart.key, JSON.stringify(cart.items));
            }
        } catch (error) {
            console.warn('Unable to load cart product images.', error);
        }
    }

    window.jrmsuCart = cart;
    cart.updateBadges();

    const cartPreview = document.querySelector('[data-cart-preview]');
    const cartPreviewTrigger = document.querySelector('[data-cart-preview-trigger]');
    const cartPreviewItems = document.querySelector('[data-cart-preview-items]');
    const cartPreviewCount = document.querySelector('[data-cart-preview-count]');
    const cartPreviewSubtotal = document.querySelector('[data-cart-preview-subtotal]');

    const renderCartPreview = () => {
        if (!cartPreview || !cartPreviewItems) return;

        const itemCount = cart.items.reduce((sum, line) => sum + Number(line.quantity), 0);
        if (cartPreviewCount) {
            cartPreviewCount.textContent = `${itemCount} ${itemCount === 1 ? 'item' : 'items'}`;
        }
        if (cartPreviewSubtotal) {
            cartPreviewSubtotal.textContent = `₱${cart.subtotal().toFixed(2)}`;
        }

        if (!cart.items.length) {
            cartPreviewItems.innerHTML = `
                <div class="cart-preview-empty">
                    <span class="cart-preview-empty-icon" aria-hidden="true"><i class="bi bi-basket2"></i></span>
                    <strong>Your cart is empty</strong>
                    <span>Add meals or drinks to see them here.</span>
                </div>`;
            return;
        }

        const visibleItems = cart.items.slice(0, 3);
        const itemMarkup = visibleItems.map((line) => {
            const quantity = Number(line.quantity);
            const unitPrice = Number(line.price) + Number(line.addon_total || 0);
            const lineTotal = unitPrice * quantity;
            const addons = (line.addons || []).map((addon) => addon.name).filter(Boolean);
            const meta = addons.length
                ? `${quantity} × ${addons.join(', ')}`
                : `${quantity} × Standard`;

            return `
                <article class="cart-preview-item">
                    ${renderProductMedia(line, 'cart-preview-item-image', 'cart-preview-item-icon')}
                    <div class="cart-preview-item-copy">
                        <h3 class="cart-preview-item-name">${escapeHtml(line.name)}</h3>
                        <div class="cart-preview-item-meta">${escapeHtml(meta)}</div>
                    </div>
                    <strong class="cart-preview-item-price">₱${lineTotal.toFixed(2)}</strong>
                </article>`;
        }).join('');
        const remaining = cart.items.length - visibleItems.length;
        cartPreviewItems.innerHTML = itemMarkup + (remaining > 0
            ? `<div class="cart-preview-more">+${remaining} more ${remaining === 1 ? 'item' : 'items'} in your cart</div>`
            : '');
    };

    if (cartPreview && cartPreviewTrigger) {
        const setPreviewState = (open) => {
            cartPreview.classList.toggle('is-open', open);
            cartPreviewTrigger.setAttribute('aria-expanded', String(open));
        };

        const previewContainer = cartPreview.closest('.cart-hover-preview');
        previewContainer?.addEventListener('pointerenter', () => setPreviewState(true));
        previewContainer?.addEventListener('pointerleave', () => setPreviewState(false));
        previewContainer?.addEventListener('focusin', () => setPreviewState(true));
        previewContainer?.addEventListener('focusout', (event) => {
            if (!previewContainer.contains(event.relatedTarget)) setPreviewState(false);
        });
        document.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape' || !cartPreview.classList.contains('is-open')) return;
            setPreviewState(false);
            cartPreviewTrigger.focus();
        });
    }

    window.addEventListener('jrmsu:cart-updated', renderCartPreview);
    renderCartPreview();

    document.querySelectorAll('[data-add-product]').forEach((button) => {
        button.addEventListener('click', () => {
            const card = button.closest('[data-product-card]');
            const addons = [...card.querySelectorAll('[data-addon]:checked')].map((input) => ({ id: Number(input.value), name: input.dataset.name, price: Number(input.dataset.price) }));
            const addedInFull = cart.add({
                product_id: Number(card.dataset.productId),
                name: card.dataset.productName,
                price: Number(card.dataset.productPrice),
                stock: Number(card.dataset.productStock || 0),
                image: card.dataset.productImage || '',
                quantity: Number(card.querySelector('[data-quantity]')?.value || 1),
                addons,
                addon_total: addons.reduce((sum, addon) => sum + addon.price, 0),
            });
            button.innerHTML = addedInFull
                ? '<i class="bi bi-check2"></i> Added'
                : '<i class="bi bi-exclamation-circle"></i> Stock limit';
            setTimeout(() => { button.innerHTML = '<i class="bi bi-bag-plus" aria-hidden="true"></i><span>Add to cart</span>'; }, 900);
        });
    });

    const cartRows = document.querySelector('[data-cart-rows]');
    const render = () => {
        const itemCount = cart.items.reduce((sum, line) => sum + Number(line.quantity), 0);
        document.querySelectorAll('[data-cart-summary-count]').forEach((node) => {
            node.textContent = `${itemCount} ${itemCount === 1 ? 'item' : 'items'} selected`;
        });
        document.querySelectorAll('[data-cart-subtotal]').forEach((node) => {
            node.textContent = `₱${cart.subtotal().toFixed(2)}`;
        });

        if (!cartRows) return;

        if (!cart.items.length) {
            cartRows.innerHTML = `
                <div class="empty-state cart-empty-state">
                    <span class="empty-state-icon"><i class="bi bi-basket2" aria-hidden="true"></i></span>
                    <h3 class="h5 section-title fw-bold">Your cart is empty</h3>
                    <p>Add something delicious from the menu.</p>
                    <a class="btn btn-primary" href="${window.cafeteriaUrl('customer/menu')}">
                        <i class="bi bi-grid" aria-hidden="true"></i>
                        <span>Browse menu</span>
                    </a>
                </div>`;
        } else {
            cartRows.innerHTML = cart.items.map((line, index) => {
                const addons = line.addons || [];
                const unitPrice = Number(line.price) + Number(line.addon_total || 0);
                const lineTotal = unitPrice * Number(line.quantity);
                const addonMarkup = addons.length
                    ? addons.map((addon) => `<span class="cart-addon-chip">${escapeHtml(addon.name)}</span>`).join('')
                    : '<span class="cart-addon-chip is-standard">Standard</span>';

                return `
                    <article class="cart-line">
                        ${renderProductMedia(line, 'cart-line-image', 'cart-line-icon')}
                        <div class="cart-line-main">
                            <div class="cart-line-title-row">
                                <h3 class="cart-line-title">${escapeHtml(line.name)}</h3>
                                <span class="cart-line-unit-price">₱${unitPrice.toFixed(2)} each</span>
                            </div>
                            <div class="cart-addon-list">${addonMarkup}</div>
                            <label class="small text-muted mt-2 d-block" for="cart-note-${index}">Item note (optional)</label>
                            <textarea class="form-control form-control-sm mt-1" id="cart-note-${index}" rows="2" maxlength="500" data-cart-note="${index}" placeholder="e.g. No onions or sauce on the side">${escapeHtml(line.notes || '')}</textarea>
                            ${line.available === false ? '<div class="small text-danger mt-2">This product is no longer available.</div>' : ''}
                            ${line.addon_invalid ? '<div class="small text-danger mt-2">A selected add-on is no longer available.</div>' : ''}
                        </div>
                        <div class="cart-line-controls">
                            <div class="quantity-stepper" role="group" aria-label="Quantity for ${escapeHtml(line.name)}">
                                <button type="button" data-cart-decrease="${index}" aria-label="Decrease quantity">
                                    <i class="bi bi-dash-lg" aria-hidden="true"></i>
                                </button>
                                <input class="cart-line-quantity" type="number" min="1" max="${Math.max(1, Math.min(99, Number(line.stock || 99)))}" value="${line.quantity}" data-cart-qty="${index}" aria-label="Quantity for ${escapeHtml(line.name)}">
                                <button type="button" data-cart-increase="${index}" aria-label="Increase quantity">
                                    <i class="bi bi-plus-lg" aria-hidden="true"></i>
                                </button>
                            </div>
                            <strong class="cart-line-total">₱${lineTotal.toFixed(2)}</strong>
                        </div>
                        <button class="cart-line-remove" type="button" data-cart-remove="${index}" aria-label="Remove ${escapeHtml(line.name)}">
                            <i class="bi bi-trash3" aria-hidden="true"></i>
                        </button>
                    </article>`;
            }).join('');
        }

        document.querySelectorAll('[data-cart-qty]').forEach((input) => {
            input.addEventListener('change', () => {
                cart.quantity(Number(input.dataset.cartQty), input.value);
                render();
            });
        });
        document.querySelectorAll('[data-cart-note]').forEach((input) => {
            input.addEventListener('change', () => {
                cart.note(Number(input.dataset.cartNote), input.value);
                render();
            });
        });
        document.querySelectorAll('[data-cart-decrease]').forEach((button) => {
            button.addEventListener('click', () => {
                const index = Number(button.dataset.cartDecrease);
                const line = cart.items[index];
                if (!line) return;
                cart.quantity(index, Math.max(1, Number(line.quantity) - 1));
                render();
            });
        });
        document.querySelectorAll('[data-cart-increase]').forEach((button) => {
            button.addEventListener('click', () => {
                const index = Number(button.dataset.cartIncrease);
                const line = cart.items[index];
                if (!line) return;
                cart.quantity(index, Number(line.quantity) + 1);
                render();
            });
        });
        document.querySelectorAll('[data-cart-remove]').forEach((button) => button.addEventListener('click', async () => {
            const index = Number(button.dataset.cartRemove);
            const line = cart.items[index];
            if (!line) return;
            const accepted = await window.cafeteriaConfirm(`Remove ${line.name} from your cart?`, {
                title: 'Remove cart item',
                confirmLabel: 'Remove',
                confirmClass: 'btn-danger',
            });
            if (!accepted) return;
            cart.remove(index);
            render();
        }));
    };

    window.addEventListener('jrmsu:cart-updated', render);

    const checkoutForm = document.querySelector('[data-checkout-form]');
    if (checkoutForm) {
        const orderType = checkoutForm.querySelector('[name="order_type"]');
        const deliveryFields = checkoutForm.querySelector('[data-delivery-fields]');
        const deliveryAddress = deliveryFields?.querySelector('[name="delivery_address"]');
        const paymentMethod = checkoutForm.querySelector('[data-payment-method]');
        const paymentLabel = checkoutForm.querySelector('[data-payment-label]');
        const paymentSummary = checkoutForm.querySelector('[data-payment-summary]');
        const itemCount = checkoutForm.querySelector('[data-checkout-item-count]');
        const checkoutItems = checkoutForm.querySelector('[data-checkout-items]');
        const subtotalNode = checkoutForm.querySelector('[data-cart-subtotal]');
        const deliveryFeeNode = checkoutForm.querySelector('[data-delivery-fee]');
        const totalNode = checkoutForm.querySelector('[data-checkout-total]');
        const errorNode = checkoutForm.querySelector('[data-checkout-error]');
        const submit = checkoutForm.querySelector('[data-checkout-submit]');
        const submitLabel = checkoutForm.querySelector('[data-checkout-submit-label]');
        const deliveryFee = Number(checkoutForm.dataset.deliveryFee || 0);
        const orderEndpoint = checkoutForm.dataset.orderEndpoint || window.cafeteriaUrl('api/orders');
        const ordersUrl = (checkoutForm.dataset.ordersUrl || window.cafeteriaUrl('customer/orders')).replace(/\/$/, '');
        const menuUrl = checkoutForm.dataset.menuUrl || window.cafeteriaUrl('customer/menu');

        let paymentModes = {};
        try {
            paymentModes = JSON.parse(checkoutForm.dataset.paymentModes || '{}');
        } catch (error) {
            console.warn('Unable to read checkout payment modes.', error);
        }

        const paymentModeFor = (type) => paymentModes[type] || paymentModes.pickup || { value: '', label: '' };
        const formatMoney = (amount) => `₱${Number(amount || 0).toFixed(2)}`;
        const showError = (message = '') => {
            if (!errorNode) return;
            errorNode.textContent = message;
            errorNode.hidden = !message;
            if (message) errorNode.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        };
        const setSubmitting = (isSubmitting) => {
            if (!submit) return;
            submit.disabled = isSubmitting || !cart.items.length || !orderType?.value || Boolean(orderType.disabled);
            submit.setAttribute('aria-busy', String(isSubmitting));
            if (submitLabel) submitLabel.textContent = isSubmitting ? 'Placing order…' : 'Place order';
        };
        const requestJson = async (url, options) => {
            if (typeof window.cafeteriaFetch === 'function') {
                return window.cafeteriaFetch(url, options);
            }

            const headers = new Headers(options?.headers || {});
            headers.set('Accept', 'application/json');
            headers.set('Content-Type', 'application/json');
            const csrfHash = document.querySelector('meta[name="csrf-hash"]')?.content;
            if (csrfHash) headers.set('X-CSRF-TOKEN', csrfHash);

            const response = await fetch(url, { ...options, headers });
            const data = await response.json().catch(() => ({ success: false, message: 'Invalid server response.' }));
            if (!response.ok || data.success === false) throw new Error(data.message || 'Unable to place the order.');
            return data;
        };
        const confirmOrder = (message) => {
            if (typeof window.cafeteriaConfirm === 'function') {
                return window.cafeteriaConfirm(message, { title: 'Place order', confirmLabel: 'Place order' });
            }
            return Promise.resolve(window.confirm(message));
        };

        const updateCheckout = () => {
            const quantity = cart.items.reduce((sum, line) => sum + Number(line.quantity || 0), 0);
            const subtotal = cart.subtotal();
            const isDelivery = orderType?.value === 'delivery';
            const fee = isDelivery ? deliveryFee : 0;
            const paymentMode = paymentModeFor(orderType?.value || 'pickup');

            if (itemCount) itemCount.textContent = `${quantity} ${quantity === 1 ? 'item' : 'items'} selected`;
            if (subtotalNode) subtotalNode.textContent = formatMoney(subtotal);
            if (deliveryFeeNode) deliveryFeeNode.textContent = formatMoney(fee);
            if (totalNode) totalNode.textContent = formatMoney(subtotal + fee);
            if (deliveryFields) deliveryFields.hidden = !isDelivery;
            if (deliveryAddress) deliveryAddress.required = isDelivery;
            if (paymentMethod) paymentMethod.value = paymentMode.value;
            if (paymentLabel) paymentLabel.value = paymentMode.label;
            if (paymentSummary) paymentSummary.textContent = paymentMode.label;

            if (checkoutItems) {
                if (!cart.items.length) {
                    checkoutItems.innerHTML = `
                        <div class="checkout-empty">
                            <span class="checkout-empty-icon" aria-hidden="true"><i class="bi bi-basket2"></i></span>
                            <div>
                                <strong>Your cart is empty</strong>
                                <p class="mb-0">Add products before placing an order.</p>
                            </div>
                            <a class="btn btn-primary" href="${escapeHtml(menuUrl)}">Browse menu</a>
                        </div>`;
                } else {
                    checkoutItems.innerHTML = cart.items.map((line) => {
                        const lineQuantity = Math.max(1, Number(line.quantity || 1));
                        const unitPrice = Number(line.price || 0) + Number(line.addon_total || 0);
                        const addons = (line.addons || []).map((addon) => addon.name).filter(Boolean);
                        const description = addons.length ? addons.join(', ') : 'Standard';

                        return `
                            <article class="checkout-item">
                                ${renderProductMedia(line, 'checkout-item-image', 'checkout-item-placeholder')}
                                <div class="checkout-item-copy">
                                    <h3>${escapeHtml(line.name || 'Product')}</h3>
                                    <p>${escapeHtml(description)}</p>
                                    <span>${lineQuantity} × ${formatMoney(unitPrice)}</span>
                                </div>
                                <strong>${formatMoney(unitPrice * lineQuantity)}</strong>
                            </article>`;
                    }).join('');
                }
            }

            setSubmitting(false);
        };

        orderType?.addEventListener('change', () => {
            showError();
            updateCheckout();
        });
        window.addEventListener('jrmsu:cart-updated', updateCheckout);
        updateCheckout();

        checkoutForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (submit?.getAttribute('aria-busy') === 'true') return;
            showError();

            if (!cart.items.length) {
                showError('Your cart is empty. Add at least one product before checking out.');
                return;
            }
            if (!checkoutForm.reportValidity()) return;

            setSubmitting(true);
            try {
                const cartError = await cart.refreshProducts();
                updateCheckout();
                if (cartError) {
                    showError(cartError);
                    setSubmitting(false);
                    return;
                }

                const orderLabel = orderType?.value === 'delivery' ? 'delivery' : 'pickup';
                const fee = orderType?.value === 'delivery' ? deliveryFee : 0;
                const accepted = await confirmOrder(`Place this ${orderLabel} order totaling ${formatMoney(cart.subtotal() + fee)}?`);
                if (!accepted) {
                    setSubmitting(false);
                    return;
                }

                const payload = Object.fromEntries(new FormData(checkoutForm).entries());
                payload.request_token = cart.requestToken();
                payload.items = cart.items.map((line) => ({
                    product_id: Number(line.product_id),
                    quantity: Math.max(1, Number(line.quantity || 1)),
                    addons: (line.addons || []).map((addon) => Number(addon.id || addon)).filter(Boolean),
                    notes: String(line.notes || ''),
                }));

                const result = await requestJson(orderEndpoint, { method: 'POST', body: JSON.stringify(payload) });
                const orderId = Number(result?.data?.order_id || 0);
                if (!orderId) throw new Error('The order was saved, but the confirmation page could not be opened.');

                const redirectUrl = result.data.redirect_url || `${ordersUrl}/${orderId}`;
                cart.clear();
                window.location.assign(redirectUrl);
            } catch (error) {
                showError(error instanceof Error ? error.message : 'Unable to place the order. Please try again.');
                setSubmitting(false);
            }
        });
    }

    document.querySelectorAll('[data-reorder-order]').forEach((button) => {
        button.addEventListener('click', async () => {
            const orderId = Number(button.dataset.reorderOrder || 0);
            if (!orderId || typeof window.cafeteriaFetch !== 'function') return;
            const original = button.innerHTML;
            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span>';
            try {
                const result = await window.cafeteriaFetch(window.cafeteriaUrl(`api/orders/${orderId}/reorder`), { method: 'POST' });
                const items = Array.isArray(result?.data?.items) ? result.data.items : [];
                const skipped = Array.isArray(result?.data?.skipped) ? result.data.skipped : [];
                let added = 0;
                items.forEach((item) => { if (cart.add(item)) added += 1; });
                const message = [`${added} item line(s) added to your cart.`];
                if (skipped.length) message.push(`Skipped: ${skipped.join('; ')}`);
                window.alert(message.join('\n'));
                if (added) window.location.assign(window.cafeteriaUrl('customer/cart'));
            } catch (error) {
                window.alert(error instanceof Error ? error.message : 'Unable to reorder these items.');
            } finally {
                button.disabled = false;
                button.innerHTML = original;
            }
        });
    });

    render();
    cart.refreshProducts().catch((error) => console.warn('Unable to refresh cart product data.', error));

    function renderProductMedia(line, imageClass, placeholderClass) {
        const image = String(line.image || '').trim();
        if (image) {
            return `<img class="${imageClass}" src="${escapeHtml(image)}" alt="">`;
        }

        return `<span class="${placeholderClass}" aria-hidden="true"><i class="bi bi-image"></i></span>`;
    }

    function escapeHtml(value) {
        return String(value).replace(/[&<>'"]/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[character]));
    }
})();
