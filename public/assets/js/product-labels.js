(() => {
    'use strict';
    document.querySelectorAll('[data-barcode-value]').forEach((svg) => {
        if (window.JsBarcode) window.JsBarcode(svg, svg.dataset.barcodeValue, { format: 'CODE128', displayValue: false, height: 48, margin: 2 });
    });
    document.querySelector('[data-print-page]')?.addEventListener('click', () => window.print());
})();
