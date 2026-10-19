(() => {
    'use strict';
    document.querySelectorAll('[data-barcode-value]').forEach((svg) => {
        if (!window.JsBarcode) return;
        const barcode = svg.dataset.barcodeValue || '';
        const format = /^\d{13}$/.test(barcode) ? 'EAN13' : /^\d{12}$/.test(barcode) ? 'UPC' : 'CODE128';
        try {
            window.JsBarcode(svg, barcode, { format, displayValue: true, height: 48, margin: 2 });
        } catch (error) {
            console.warn('Cannot render barcode label:', error);
            svg.replaceWith(document.createTextNode(barcode));
        }
    });
    document.querySelector('[data-print-page]')?.addEventListener('click', () => window.print());
})();
