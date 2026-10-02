(() => {
    'use strict';
    const render = (node) => {
        const value = node.dataset.qrValue || '';
        if (!value || typeof window.qrcode !== 'function') return;
        try {
            const qr = window.qrcode(0, 'M');
            qr.addData(value);
            qr.make();
            const count = qr.getModuleCount();
            const quiet = 4;
            const total = count + quiet * 2;
            let path = '';
            for (let r = 0; r < count; r += 1) {
                for (let c = 0; c < count; c += 1) {
                    if (qr.isDark(r, c)) path += `M${c + quiet},${r + quiet}h1v1h-1z`;
                }
            }
            node.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${total} ${total}" width="220" height="220" role="img" aria-label="QR code"><rect width="100%" height="100%" fill="white"/><path d="${path}" fill="black"/></svg>`;
        } catch (error) {
            console.warn('Unable to render QR code.', error);
        }
    };
    document.querySelectorAll('[data-qr-code]').forEach(render);
    document.querySelectorAll('[data-print-page]').forEach((button) => button.addEventListener('click', () => window.print()));
})();
