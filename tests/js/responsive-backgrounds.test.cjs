'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '..', '..');
const css = fs.readFileSync(path.join(root, 'public/assets/css/app.css'), 'utf8');
const queueCss = fs.readFileSync(path.join(root, 'public/assets/css/queue-board.css'), 'utf8');
for (const file of ['cafeteria-wallpaper.svg', 'card-watermark.svg', 'night-pattern.svg']) {
    const asset = path.join(root, 'public/assets/img/backgrounds', file);
    assert.ok(fs.statSync(asset).size > 100, `${file} must exist as a bundled image`);
    assert.match(fs.readFileSync(asset, 'utf8'), /^<svg\b/, `${file} should be a local SVG`);
    assert.ok(css.includes(`../img/backgrounds/${file}`) || queueCss.includes(`../img/backgrounds/${file}`));
}
for (const selector of ['.app-main', '.auth-page', '.surface-card', '.stat-card', '.product-card',
    '.modal-content', '.offcanvas', '.dropdown-menu', '.table-responsive', '.topbar-icon-button',
    '.btn', '.table-actions']) {
    assert.ok(css.includes(selector), `Shared responsive CSS should cover ${selector}`);
}
assert.match(css, /@media print\s*\{/, 'Decorative images must not appear on printed receipts');
assert.match(queueCss, /night-pattern\.svg/, 'The queue display should have an offline background');
for (const name of ['main', 'public', 'auth']) {
    assert.match(fs.readFileSync(path.join(root, `app/Views/layouts/${name}.php`), 'utf8'), /app\.css/);
}
assert.match(fs.readFileSync(path.join(root, 'app/Views/layouts/display.php'), 'utf8'), /queue-board\.css/);
assert.match(fs.readFileSync(path.join(root, 'app/Views/customer/orders/receipt.php'), 'utf8'), /table-responsive/);
// Mobile navigation must remain legible even when shared sheets use image-backed backgrounds.
const mainLayout = fs.readFileSync(path.join(root, 'app/Views/layouts/main.php'), 'utf8');
assert.doesNotMatch(mainLayout, /class="offcanvas offcanvas-start text-bg-dark"[^>]*id="mobileSidebar"/);
assert.match(css, /#mobileSidebar \.offcanvas-body\s*\{[^}]*background-image:\s*linear-gradient\(/s);
assert.match(css, /#mobileSidebar \.app-sidebar \.nav-link\s*\{[^}]*color:\s*#253b53/s);
assert.match(css, /#mobileSidebar \.app-sidebar \.nav-link\.active\s*\{[^}]*color:\s*#083969/s);
assert.match(css, /#mobileSidebar \.offcanvas-body \.text-white-50\s*\{[^}]*!important/s);
assert.match(css, /#mobileSidebar \.offcanvas-body \.btn-outline-light\s*\{[^}]*color:\s*#164c7a/s);
console.log('Responsive backgrounds and image assets tests passed.');
