(() => {
    'use strict';

    class CartStore {
        constructor(key = 'jrmsu-cafeteria-cart') {
            this.key = key;
            this.items = JSON.parse(localStorage.getItem(key) || '[]');
        }
        save() {
            localStorage.setItem(this.key, JSON.stringify(this.items));
            this.updateBadges();
            window.dispatchEvent(new CustomEvent('jrmsu:cart-updated'));
        }
        add(item) {
            const signature = JSON.stringify(item.addons || []);
            const found = this.items.find((line) => line.product_id === item.product_id && JSON.stringify(line.addons || []) === signature);
            if (found) found.quantity += item.quantity;
            else this.items.push(item);
            this.save();
        }
        remove(index) { this.items.splice(index, 1); this.save(); }
        quantity(index, value) { this.items[index].quantity = Math.max(1, Number(value) || 1); this.save(); }
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
            cart.add({
                product_id: Number(card.dataset.productId),
                name: card.dataset.productName,
                price: Number(card.dataset.productPrice),
                image: card.dataset.productImage || '',
                quantity: Number(card.querySelector('[data-quantity]')?.value || 1),
                addons,
                addon_total: addons.reduce((sum, addon) => sum + addon.price, 0),
            });
            button.innerHTML = '<i class="bi bi-check2"></i> Added';
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
                    <a class="btn btn-primary" href="/customer/menu">
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
                        </div>
                        <div class="cart-line-controls">
                            <div class="quantity-stepper" role="group" aria-label="Quantity for ${escapeHtml(line.name)}">
                                <button type="button" data-cart-decrease="${index}" aria-label="Decrease quantity">
                                    <i class="bi bi-dash-lg" aria-hidden="true"></i>
                                </button>
                                <input class="cart-line-quantity" type="number" min="1" value="${line.quantity}" data-cart-qty="${index}" aria-label="Quantity for ${escapeHtml(line.name)}">
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

    const checkoutForm = document.querySelector('[data-checkout-form]');
    if (checkoutForm) {
        const orderType = checkoutForm.querySelector('[name="order_type"]');
        const deliveryFields = document.querySelector('[data-delivery-fields]');
        const deliveryAddress = deliveryFields?.querySelector('[name="delivery_address"]');
        const paymentMethod = checkoutForm.querySelector('[data-payment-method]');
        const paymentLabel = checkoutForm.querySelector('[data-payment-label]');
        const paymentSummary = checkoutForm.querySelector('[data-payment-summary]');
        const deliveryFee = Number(checkoutForm.dataset.deliveryFee || 0);
        const updateTotal = () => {
            const isDelivery = orderType.value === 'delivery';
            const fee = isDelivery ? deliveryFee : 0;
            const paymentMode = window.cafeteriaPaymentMode(checkoutForm, orderType.value);
            document.querySelector('[data-delivery-fee]').textContent = `₱${fee.toFixed(2)}`;
            document.querySelector('[data-checkout-total]').textContent = `₱${(cart.subtotal() + fee).toFixed(2)}`;
            deliveryFields.hidden = !isDelivery;
            if (deliveryAddress) deliveryAddress.required = isDelivery;
            if (paymentMethod) paymentMethod.value = paymentMode.value;
            if (paymentLabel) paymentLabel.value = paymentMode.label;
            if (paymentSummary) paymentSummary.textContent = paymentMode.label;
        };
        orderType.addEventListener('change', updateTotal);
        updateTotal();
        render();
        checkoutForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (!cart.items.length) return alert('Your cart is empty.');
            const orderLabel = orderType.value === 'delivery' ? 'delivery' : 'pickup';
            const fee = orderType.value === 'delivery' ? deliveryFee : 0;
            const accepted = await window.cafeteriaConfirm(
                `Place this ${orderLabel} order totaling ₱${(cart.subtotal() + fee).toFixed(2)}?`,
                { title: 'Place order', confirmLabel: 'Place order' },
            );
            if (!accepted) return;

            const submit = checkoutForm.querySelector('[type="submit"]');
            submit.disabled = true;
            try {
                const payload = Object.fromEntries(new FormData(checkoutForm).entries());
                payload.items = cart.items;
                const result = await window.cafeteriaFetch('/api/orders', { method: 'POST', body: JSON.stringify(payload) });
                cart.clear();
                window.location.href = `/customer/orders/${result.data.order_id}`;
            } catch (error) {
                alert(error.message);
                submit.disabled = false;
            }
        });
    }

    render();

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
