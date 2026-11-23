(() => {
    'use strict';

    document.querySelectorAll('[data-product-search]').forEach((input, serial) => {
        const mode = input.dataset.productSearchMode || 'submit';
        const host = input.closest('[data-product-search-options]') || input;
        let options = [];
        if (host.dataset.productSearchOptions) {
            try {
                const parsed = JSON.parse(host.dataset.productSearchOptions);
                if (Array.isArray(parsed)) options = parsed;
            } catch (_) { options = []; }
        } else if (mode === 'filter') {
            options = Array.from(document.querySelectorAll('[data-product-card]')).map(card => {
                const categoryId = card.closest('[data-category-id]')?.dataset.categoryId;
                const category = document.querySelector('[data-category="' + CSS.escape(categoryId || '') + '"]');
                return {
                    id: Number(card.dataset.productId),
                    name: card.dataset.productName || '',
                    category: category?.textContent.trim() || '',
                    price: card.dataset.productPrice || '',
                };
            });
        }
        const wrapper = input.closest('.position-relative, .menu-search') || input.parentElement;
        if (wrapper && getComputedStyle(wrapper).position === 'static') wrapper.style.position = 'relative';
        const dropdown = document.createElement('div');
        dropdown.className = 'product-search-dropdown';
        dropdown.id = `product-search-list-${serial}`;
        dropdown.setAttribute('role', 'listbox');
        dropdown.hidden = true;
        wrapper?.appendChild(dropdown);
        input.setAttribute('role', 'combobox');
        input.setAttribute('aria-expanded', 'false');
        input.setAttribute('aria-controls', dropdown.id);
        input.setAttribute('aria-autocomplete', 'list');
        input.setAttribute('autocomplete', 'off');
        let choices = [];
        let active = -1;
        const open = () => { dropdown.hidden = false; input.setAttribute('aria-expanded', 'true'); };
        const close = () => { dropdown.hidden = true; input.setAttribute('aria-expanded', 'false'); input.removeAttribute('aria-activedescendant'); active = -1; };
        const activate = (index) => {
            if (!choices.length) return;
            active = (index + choices.length) % choices.length;
            choices.forEach((button, i) => {
                button.classList.toggle('active', i === active);
                button.setAttribute('aria-selected', String(i === active));
            });
            input.setAttribute('aria-activedescendant', choices[active].id);
            choices[active].scrollIntoView({ block: 'nearest' });
        };
        const select = (product) => {
            if (mode === 'submit') {
                input.value = product.name;
                input.closest('form')?.requestSubmit();
                return;
            }
            // Re-enable all categories so the chosen card remains visible.
            const allButton = document.querySelector('[data-category="all"]');
            allButton?.click();
            input.value = product.name;
            input.dispatchEvent(new Event('input', { bubbles: true }));
            close();
            const card = Array.from(document.querySelectorAll('[data-product-card]'))
                .find(element => Number(element.dataset.productId) === Number(product.id));
            if (card) {
                card.scrollIntoView({ behavior: 'smooth', block: 'center' });
                card.classList.add('product-search-hit');
                setTimeout(() => card.classList.remove('product-search-hit'), 1500);
            }
        };
        const render = (query = '') => {
            const term = query.toLocaleLowerCase().trim();
            const filtered = options.filter(product => (String(product.name || '') + ' ' + String(product.category || '')).toLocaleLowerCase().includes(term));
            dropdown.replaceChildren();
            choices = [];
            active = -1;
            input.removeAttribute('aria-activedescendant');
            if (filtered.length === 0) {
                const empty = document.createElement('div');
                empty.className = 'p-3 text-secondary small';
                empty.textContent = 'No matching products';
                dropdown.appendChild(empty);
            } else {
                filtered.forEach((product, index) => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'product-search-option';
                    button.id = `${dropdown.id}-option-${index}`;
                    button.setAttribute('role', 'option');
                    button.setAttribute('aria-selected', 'false');
                    const name = document.createElement('strong');
                    name.className = 'd-block';
                    name.textContent = String(product.name || '');
                    const details = document.createElement('small');
                    details.className = 'text-secondary';
                    details.textContent = [product.category, product.price].filter(Boolean).join(' · ');
                    button.append(name, details);
                    button.addEventListener('pointerdown', event => event.preventDefault());
                    button.addEventListener('click', () => select(product));
                    dropdown.appendChild(button);
                    choices.push(button);
                });
            }
            open();
        };
        input.addEventListener('focus', () => render(''));
        input.addEventListener('click', () => render(''));
        input.addEventListener('input', () => render(input.value));
        input.addEventListener('keydown', event => {
            if (event.key === 'Escape') { close(); return; }
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                if (dropdown.hidden) render('');
                activate(active + (event.key === 'ArrowDown' ? 1 : -1));
            } else if (event.key === 'Enter' && !dropdown.hidden && active !== -1) {
                event.preventDefault();
                choices[active]?.click();
            }
        });
        document.addEventListener('click', event => {
            if (!wrapper?.contains(event.target)) close();
        });
    });
})();
