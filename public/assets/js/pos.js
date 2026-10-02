(() => {
    'use strict';

    const cart = window.jrmsuCart;
    const escapeHtml = (value) => String(value).replace(/[&<>'"]/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[character]));
    const rows = document.querySelector('[data-pos-rows]');
    const form = document.querySelector('[data-pos-form]');
    if (!cart || !rows || !form) return;

    const orderType = document.querySelector('[data-pos-order-type]');
    const search = document.querySelector('[data-pos-search]');
    const deliveryFields = document.querySelector('[data-pos-delivery-fields]');
    const deliveryAddress = deliveryFields?.querySelector('[name="delivery_address"]');
    const paymentMethod = document.querySelector('[data-pos-payment-method]');
    const paymentLabel = document.querySelector('[data-pos-payment-label]');
    const submitButton = document.querySelector('[data-pos-submit]');
    const deliveryFee = Math.max(0, Number(form.dataset.deliveryFee || 0));
    const orderEndpoint = form.dataset.orderEndpoint || window.cafeteriaUrl('api/orders');
    const ordersUrl = form.dataset.ordersUrl || window.cafeteriaUrl('cashier/orders');
    const barcodeInput = document.querySelector('[data-pos-barcode]');
    const scanFeedback = document.querySelector('[data-pos-scan-feedback]');
    const beep = (ok = true) => {
        try {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            const context = new AudioContext(); const oscillator = context.createOscillator(); const gain = context.createGain();
            oscillator.type = 'sine'; oscillator.frequency.value = ok ? 880 : 220; gain.gain.value = 0.05;
            oscillator.connect(gain); gain.connect(context.destination); oscillator.start(); oscillator.stop(context.currentTime + (ok ? 0.08 : 0.18));
        } catch (_) {}
    };
    const setScanFeedback = (message, ok) => {
        if (!scanFeedback) return; scanFeedback.textContent = message; scanFeedback.classList.toggle('text-success', ok === true); scanFeedback.classList.toggle('text-danger', ok === false);
    };

    const total = () => cart.subtotal() + (orderType?.value === 'delivery' ? deliveryFee : 0);

    const syncDeliveryFields = () => {
        const isDelivery = orderType?.value === 'delivery';
        if (deliveryFields) deliveryFields.hidden = !isDelivery;
        if (deliveryAddress) {
            deliveryAddress.required = isDelivery;
            if (!isDelivery) deliveryAddress.setCustomValidity('');
        }
        const paymentMode = window.cafeteriaPaymentMode(form, orderType?.value || 'pickup');
        if (paymentMethod) paymentMethod.value = paymentMode.value;
        if (paymentLabel) paymentLabel.value = paymentMode.label;
        render();
    };

    const render = () => {
        rows.innerHTML = cart.items.length
            ? cart.items.map((line, index) => {
                const addons = (Array.isArray(line.addons) ? line.addons : []).map((addon) => addon.name).filter(Boolean);
                const unitPrice = Number(line.price || 0) + Number(line.addon_total || 0);
                const availability = line.available === false
                    ? '<small class="text-danger d-block">Product unavailable</small>'
                    : line.addon_invalid
                        ? '<small class="text-danger d-block">Selected add-on unavailable</small>'
                        : '';
                return `
                <div class="pos-line py-2 border-bottom">
                    <div class="pos-line-main">
                        <div class="fw-semibold">${escapeHtml(line.name)}</div>
                        <small class="text-secondary">₱${unitPrice.toFixed(2)}${addons.length ? ` · ${escapeHtml(addons.join(', '))}` : ''}</small>
                        ${availability}
                    </div>
                    <input class="form-control form-control-sm pos-line-quantity" type="number" min="1" value="${line.quantity}" data-pos-qty="${index}" aria-label="Quantity for ${escapeHtml(line.name)}">
                    <button class="btn btn-sm btn-outline-danger pos-line-remove" type="button" data-pos-remove="${index}" aria-label="Remove ${escapeHtml(line.name)}"><i class="bi bi-x"></i></button>
                </div>`;
            }).join('')
            : '<div class="empty-state py-5"><i class="bi bi-cart3"></i><p>Select products to begin.</p></div>';

        const totalNode = document.querySelector('[data-pos-total]');
        if (totalNode) totalNode.textContent = `₱${total().toFixed(2)}`;

        document.querySelectorAll('[data-pos-qty]').forEach((input) => input.addEventListener('change', () => {
            cart.quantity(Number(input.dataset.posQty), input.value);
            render();
        }));
        document.querySelectorAll('[data-pos-remove]').forEach((button) => button.addEventListener('click', async () => {
            const index = Number(button.dataset.posRemove);
            const line = cart.items[index];
            if (!line) return;
            const accepted = await window.cafeteriaConfirm(`Remove ${line.name} from the current order?`, {
                title: 'Remove order item',
                confirmLabel: 'Remove',
                confirmClass: 'btn-danger',
            });
            if (!accepted) return;
            cart.remove(index);
            render();
        }));
    };

    barcodeInput?.addEventListener('keydown', async (event) => {
        if (event.key !== 'Enter') return;
        event.preventDefault(); const scanned = barcodeInput.value.trim(); if (!scanned) return;
        try {
            const maybeUrl = new URL(scanned, window.location.origin);
            if (maybeUrl.origin === window.location.origin && maybeUrl.pathname.includes('/staff/orders/verify/')) {
                window.location.assign(maybeUrl.toString()); return;
            }
        } catch (_) {}
        barcodeInput.disabled = true;
        try {
            const { data: product } = await window.cafeteriaFetch(window.cafeteriaUrl(`api/products/barcode/${encodeURIComponent(scanned)}`));
            if (!product.is_available) throw new Error(`${product.name} is unavailable.`);
            if (Number(product.stock) < 1) throw new Error(`${product.name} is out of stock.`);
            const full = cart.add({ product_id: Number(product.id), name: product.name, price: Number(product.price), stock: Number(product.stock), quantity: 1, image: product.image_url || '', addons: [], addon_total: 0, notes: '', available: true });
            if (!full) throw new Error(`No more stock is available for ${product.name}.`);
            render(); beep(true); setScanFeedback(`${product.name} added.`, true); barcodeInput.value = '';
        } catch (error) {
            beep(false); setScanFeedback(error instanceof Error ? error.message : 'Barcode scan failed.', false); barcodeInput.select();
        } finally { barcodeInput.disabled = false; barcodeInput.focus(); }
    });

    document.querySelectorAll('[data-add-product]').forEach((button) => {
        button.addEventListener('click', () => setTimeout(render, 0));
    });

    let activeCategory = 'all';
    const applyFilters = () => {
        const query = String(search?.value || '').trim().toLowerCase();
        document.querySelectorAll('[data-product-column]').forEach((column) => {
            const card = column.querySelector('[data-product-card]');
            const categoryMatches = activeCategory === 'all' || column.dataset.categoryId === activeCategory;
            const searchMatches = !query || String(card?.dataset.productName || '').toLowerCase().includes(query);
            column.hidden = !categoryMatches || !searchMatches;
        });
    };

    document.querySelectorAll('[data-category]').forEach((button) => {
        button.addEventListener('click', () => {
            activeCategory = button.dataset.category || 'all';
            document.querySelectorAll('[data-category]').forEach((item) => {
                item.classList.toggle('btn-primary', item === button);
                item.classList.toggle('btn-outline-secondary', item !== button);
            });
            applyFilters();
        });
    });
    search?.addEventListener('input', applyFilters);

    orderType?.addEventListener('change', syncDeliveryFields);
    window.addEventListener('jrmsu:cart-updated', render);

    submitButton?.addEventListener('click', async () => {
        if (submitButton.disabled) return;
        if (!cart.items.length) {
            alert('Add at least one product.');
            return;
        }

        if (!form.reportValidity()) return;

        submitButton.disabled = true;
        try {
            const cartError = await cart.refreshProducts();
            render();
            if (cartError) {
                alert(cartError);
                submitButton.disabled = false;
                return;
            }

            const accepted = await window.cafeteriaConfirm(
                `Complete this ${orderType?.value === 'delivery' ? 'delivery' : 'pickup'} order totaling ₱${total().toFixed(2)}?`,
                { title: 'Complete order', confirmLabel: 'Complete order' },
            );
            if (!accepted) {
                submitButton.disabled = false;
                return;
            }

            const payload = Object.fromEntries(new FormData(form).entries());
            payload.request_token = cart.requestToken();
            payload.items = cart.items.map((line) => ({
                product_id: Number(line.product_id),
                quantity: Math.max(1, Number(line.quantity || 1)),
                addons: (line.addons || []).map((addon) => Number(addon.id || addon)).filter(Boolean),
                notes: String(line.notes || ''),
            }));
            const result = await window.cafeteriaFetch(orderEndpoint, {
                method: 'POST',
                body: JSON.stringify(payload),
            });
            cart.clear();
            alert(`Order ${result.data.order_number} created.`);
            window.location.assign(result.data.redirect_url || ordersUrl);
        } catch (error) {
            alert(error instanceof Error ? error.message : 'Unable to create the order.');
            submitButton.disabled = false;
        }
    });

    syncDeliveryFields();
})();
