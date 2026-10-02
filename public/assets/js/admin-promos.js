(() => {
    const form = document.getElementById('promoForm');
    const modal = document.getElementById('promoModal');
    const resetForm = () => {
        form.reset();
        form.action = form.dataset.baseAction;
        form.elements.discount_type.value = 'percentage';
        form.elements.minimum_order.value = 0;
        form.elements.usage_limit.value = 0;
        form.elements.is_active.checked = true;
    };

    document.querySelector('[data-create-promo]')?.addEventListener('click', resetForm);
    document.querySelectorAll('[data-edit-promo]').forEach((button) => button.addEventListener('click', () => {
        const promo = JSON.parse(button.dataset.editPromo);
        form.action = `${form.dataset.baseAction}/${promo.id}`;
        ['code', 'description', 'discount_type', 'discount_value', 'minimum_order', 'usage_limit'].forEach((name) => { form.elements[name].value = promo[name] ?? ''; });
        form.elements.starts_at.value = (promo.starts_at || '').replace(' ', 'T').slice(0, 16);
        form.elements.ends_at.value = (promo.ends_at || '').replace(' ', 'T').slice(0, 16);
        form.elements.is_active.checked = Number(promo.is_active) === 1;
        bootstrap.Modal.getOrCreateInstance(modal).show();
    }));
})();
