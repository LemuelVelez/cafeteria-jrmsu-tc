(() => {
    'use strict';

    class CartStore {
        constructor(key = 'jrmsu-cafeteria-cart') {
            this.key = key;
            this.items = JSON.parse(localStorage.getItem(key) || '[]');
        }
        save() { localStorage.setItem(this.key, JSON.stringify(this.items)); this.updateBadges(); }
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
    window.jrmsuCart = cart;
    cart.updateBadges();

    document.querySelectorAll('[data-add-product]').forEach((button) => {
        button.addEventListener('click', () => {
            const card = button.closest('[data-product-card]');
            const addons = [...card.querySelectorAll('[data-addon]:checked')].map((input) => ({ id: Number(input.value), name: input.dataset.name, price: Number(input.dataset.price) }));
            cart.add({
                product_id: Number(card.dataset.productId),
                name: card.dataset.productName,
                price: Number(card.dataset.productPrice),
                quantity: Number(card.querySelector('[data-quantity]')?.value || 1),
                addons,
                addon_total: addons.reduce((sum, addon) => sum + addon.price, 0),
            });
            button.innerHTML = '<i class="bi bi-check2"></i> Added';
            setTimeout(() => { button.innerHTML = '<i class="bi bi-bag-plus"></i> Add'; }, 900);
        });
    });

    const cartRows = document.querySelector('[data-cart-rows]');
    const render = () => {
        if (!cartRows) return;
        if (!cart.items.length) {
            cartRows.innerHTML = '<div class="empty-state"><i class="bi bi-basket"></i><h5>Your cart is empty</h5><p>Add something delicious from the menu.</p><a class="btn btn-primary" href="/customer/menu">Browse menu</a></div>';
        } else {
            cartRows.innerHTML = cart.items.map((line, index) => `
                <div class="cart-line py-3 border-bottom">
                    <div class="cart-line-main">
                        <h6 class="mb-1">${escapeHtml(line.name)}</h6>
                        <div class="small text-secondary">${(line.addons || []).map((a) => escapeHtml(a.name)).join(', ') || 'Standard'}</div>
                        <div class="price mt-1">₱${((Number(line.price) + Number(line.addon_total || 0)) * Number(line.quantity)).toFixed(2)}</div>
                    </div>
                    <input class="form-control form-control-sm cart-line-quantity" type="number" min="1" value="${line.quantity}" data-cart-qty="${index}" aria-label="Quantity for ${escapeHtml(line.name)}">
                    <button class="btn btn-sm btn-outline-danger cart-line-remove" type="button" data-cart-remove="${index}" aria-label="Remove ${escapeHtml(line.name)}"><i class="bi bi-trash"></i></button>
                </div>`).join('');
        }
        document.querySelectorAll('[data-cart-subtotal]').forEach((node) => node.textContent = `₱${cart.subtotal().toFixed(2)}`);
        document.querySelectorAll('[data-cart-qty]').forEach((input) => input.addEventListener('change', () => { cart.quantity(Number(input.dataset.cartQty), input.value); render(); }));
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

    function escapeHtml(value) {
        return String(value).replace(/[&<>'"]/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[character]));
    }
})();
