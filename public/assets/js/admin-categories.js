(() => {
    const form = document.getElementById('categoryForm');
    const modal = document.getElementById('categoryModal');
    const resetForm = () => {
        form.reset();
        form.action = form.dataset.baseAction;
        form.elements.sort_order.value = 0;
        form.elements.is_active.checked = true;
    };

    document.querySelector('[data-create-category]')?.addEventListener('click', resetForm);
    document.querySelectorAll('[data-edit-category]').forEach((button) => button.addEventListener('click', () => {
        const category = JSON.parse(button.dataset.editCategory);
        form.action = `${form.dataset.baseAction}/${category.id}`;
        ['name', 'description', 'sort_order'].forEach((name) => { form.elements[name].value = category[name] ?? ''; });
        form.elements.is_active.checked = Number(category.is_active) === 1;
        bootstrap.Modal.getOrCreateInstance(modal).show();
    }));
})();
