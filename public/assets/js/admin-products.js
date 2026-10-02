(() => {
    'use strict';
    const form = document.getElementById('productForm');
    const modal = document.getElementById('productModal');
    if (!form || !modal) return;
    const stockGroup = form.querySelector('[data-initial-stock-group]');
    const nutritionFields = ['serving_size','calories','protein_g','carbohydrates_g','fat_g','sugar_g','fiber_g','sodium_mg','allergens'];
    const setInitialStockMode = (create) => {
        stockGroup.hidden = !create;
        form.elements.stock.disabled = !create;
        form.elements.stock.required = create;
    };
    const resetForm = () => {
        form.reset(); form.action = form.dataset.baseAction;
        form.elements.is_available.checked = true; form.elements.reorder_level.value = 5;
        setInitialStockMode(true);
    };
    document.querySelector('[data-create-product]')?.addEventListener('click', resetForm);
    document.querySelectorAll('[data-edit-product]').forEach((button) => button.addEventListener('click', () => {
        const product = JSON.parse(button.dataset.editProduct || '{}');
        form.action = `${form.dataset.baseAction}/${product.id}`;
        ['name','category_id','description','price','sku','barcode','reorder_level',...nutritionFields].forEach((name) => { if (form.elements[name]) form.elements[name].value = product[name] ?? ''; });
        form.elements.image.value = '';
        ['is_available','is_featured','is_healthy_choice'].forEach((name) => { form.elements[name].checked = Number(product[name]) === 1; });
        setInitialStockMode(false);
        bootstrap.Modal.getOrCreateInstance(modal).show();
    }));
    form.querySelector('[data-generate-barcode]')?.addEventListener('click', () => {
        const sku = String(form.elements.sku.value || '').trim();
        if (!sku) { form.elements.sku.focus(); alert('Enter an SKU first.'); return; }
        form.elements.barcode.value = `INT-${sku}`.replace(/[^A-Za-z0-9_-]/g, '').slice(0, 64);
    });
    document.querySelector('[data-select-all-products]')?.addEventListener('change', (event) => {
        document.querySelectorAll('input[name="ids[]"]').forEach((box) => { box.checked = event.target.checked; });
    });
    const qrModal = document.getElementById('productQrModal');
    document.querySelectorAll('[data-show-product-qr]').forEach((button) => button.addEventListener('click', () => {
        const url = button.dataset.productUrl; const output = qrModal?.querySelector('[data-product-qr-output]');
        if (!output || !window.qrcode) return;
        const qr = window.qrcode(0, 'M'); qr.addData(url); qr.make(); output.innerHTML = qr.createSvgTag({ cellSize: 6, margin: 2 });
        const link = qrModal.querySelector('[data-product-qr-link]'); link.href = url;
        bootstrap.Modal.getOrCreateInstance(qrModal).show();
    }));
})();
