(() => {
    'use strict';

    const cart = window.jrmsuCart;
    const escapeHtml = (value) => String(value).replace(/[&<>'"]/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[character]));
    const rows = document.querySelector('[data-pos-rows]');
    const form = document.querySelector('[data-pos-form]');
    if (!cart || !rows || !form) return;

    const orderType = document.querySelector('[data-pos-order-type]');
    const deliveryFields = document.querySelector('[data-pos-delivery-fields]');
    const deliveryAddress = deliveryFields?.querySelector('[name="delivery_address"]');
    const paymentMethod = document.querySelector('[data-pos-payment-method]');
    const paymentLabel = document.querySelector('[data-pos-payment-label]');
    const submitButton = document.querySelector('[data-pos-submit]');
    const deliveryFee = Math.max(0, Number(form.dataset.deliveryFee || 0));
    const orderEndpoint = form.dataset.orderEndpoint || window.cafeteriaUrl('api/orders');
    const ordersUrl = form.dataset.ordersUrl || window.cafeteriaUrl('cashier/orders');

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
            ? cart.items.map((line, index) => `
                <div class="pos-line py-2 border-bottom">
                    <div class="pos-line-main">
                        <div class="fw-semibold">${escapeHtml(line.name)}</div>
                        <small class="text-secondary">₱${Number(line.price).toFixed(2)}</small>
                    </div>
                    <input class="form-control form-control-sm pos-line-quantity" type="number" min="1" value="${line.quantity}" data-pos-qty="${index}" aria-label="Quantity for ${escapeHtml(line.name)}">
                    <button class="btn btn-sm btn-outline-danger pos-line-remove" type="button" data-pos-remove="${index}" aria-label="Remove ${escapeHtml(line.name)}"><i class="bi bi-x"></i></button>
                </div>`).join('')
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

    document.querySelectorAll('[data-add-product]').forEach((button) => {
        button.addEventListener('click', () => setTimeout(render, 0));
    });

    document.querySelectorAll('[data-category]').forEach((button) => {
        button.addEventListener('click', () => {
            const category = button.dataset.category;
            document.querySelectorAll('[data-category]').forEach((item) => {
                item.classList.toggle('btn-primary', item === button);
                item.classList.toggle('btn-outline-secondary', item !== button);
            });
            document.querySelectorAll('[data-product-column]').forEach((column) => {
                column.hidden = category !== 'all' && column.dataset.categoryId !== category;
            });
        });
    });

    orderType?.addEventListener('change', syncDeliveryFields);

    submitButton?.addEventListener('click', async () => {
        if (!cart.items.length) {
            alert('Add at least one product.');
            return;
        }

        if (!form.reportValidity()) return;

        const accepted = await window.cafeteriaConfirm(
            `Complete this ${orderType?.value === 'delivery' ? 'delivery' : 'pickup'} order totaling ₱${total().toFixed(2)}?`,
            { title: 'Complete order', confirmLabel: 'Complete order' },
        );
        if (!accepted) return;

        submitButton.disabled = true;
        try {
            const payload = Object.fromEntries(new FormData(form).entries());
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
