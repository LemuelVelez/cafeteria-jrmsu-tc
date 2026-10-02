<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<div class="d-flex flex-column flex-md-row justify-content-between gap-3 mb-4">
    <div><h1 class="h3 section-title fw-bold">Products</h1><p class="text-secondary mb-0">Manage menu details, barcodes, nutrition, pricing, and availability. Stock changes are handled in Inventory.</p></div>
    <div class="d-flex gap-2"><a class="btn btn-outline-secondary" href="<?= base_url('admin/products/labels') ?>"><i class="bi bi-upc-scan me-1"></i>Print labels</a><button class="btn btn-primary" type="button" data-create-product data-bs-toggle="modal" data-bs-target="#productModal"><i class="bi bi-plus-lg me-1"></i>New product</button></div>
</div>
<form action="<?= base_url('admin/products/labels') ?>" method="get" target="_blank">
<div class="surface-card table-card overflow-hidden"><div class="table-responsive"><table class="table align-middle"><thead><tr><th><input class="form-check-input" type="checkbox" data-select-all-products aria-label="Select all products"></th><th>Product</th><th>SKU / Barcode</th><th>Price</th><th>Stock</th><th>Nutrition</th><th>Status</th><th class="text-end">Actions</th></tr></thead><tbody>
<?php foreach ($products as $product): ?><tr>
<td><input class="form-check-input" type="checkbox" name="ids[]" value="<?= (int) $product['id'] ?>"></td>
<td><div class="d-flex align-items-center gap-3"><?php if ($product['image']): ?><img src="<?= media_url($product['image']) ?>" alt="<?= esc($product['name']) ?>" class="rounded" style="width:54px;height:54px;object-fit:cover"><?php else: ?><div class="rounded bg-light p-2" style="width:54px;height:54px"><img src="<?= base_url('assets/img/jrmsu-cafeteria-logo.png') ?>" alt=""></div><?php endif; ?><div><div class="fw-semibold"><?= esc($product['name']) ?></div><small class="text-secondary"><?= esc($product['category_name'] ?? '—') ?></small></div></div></td>
<td><div><?= esc($product['sku'] ?: '—') ?></div><small class="text-secondary"><?= esc($product['barcode'] ?: 'No barcode') ?></small></td>
<td class="fw-semibold"><?= format_price($product['price']) ?></td>
<td><span class="<?= (int)$product['stock'] === 0 ? 'text-danger fw-semibold' : ((int)$product['stock'] <= (int)$product['reorder_level'] ? 'text-warning fw-semibold' : '') ?>"><?= (int) $product['stock'] ?></span><small class="text-secondary d-block">Reorder at <?= (int) $product['reorder_level'] ?></small></td>
<td><?= $product['calories'] !== null ? esc($product['calories']).' kcal' : '—' ?><?php if (!empty($product['is_healthy_choice'])): ?><span class="badge text-bg-success ms-1">Healthy</span><?php endif; ?></td>
<td><span class="badge <?= $product['is_available'] ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= $product['is_available'] ? ((int)$product['stock'] > 0 ? 'Available' : 'Out of stock') : 'Hidden' ?></span></td>
<td class="text-end action-cell"><div class="table-actions"><?php if ($product['barcode']): ?><button class="btn btn-sm btn-outline-secondary" type="button" data-show-product-qr data-product-url="<?= esc(base_url('menu/'.$product['slug']), 'attr') ?>" title="Menu QR"><i class="bi bi-qr-code"></i></button><?php endif; ?><button class="btn btn-sm btn-outline-primary" type="button" data-edit-product='<?= esc(json_encode($product, JSON_HEX_APOS|JSON_HEX_QUOT), 'attr') ?>'><i class="bi bi-pencil"></i></button><form class="d-inline" data-confirm="Remove this product?" action="<?= base_url('admin/products/'.$product['id'].'/delete') ?>" method="post"><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form></div></td>
</tr><?php endforeach; ?>
<?php if (!$products): ?><tr><td colspan="8"><?= view('components/empty', ['icon'=>'bi-cup-hot','message'=>'Add your first menu product.']) ?></td></tr><?php endif; ?>
</tbody></table></div></div>
<div class="mt-3"><button class="btn btn-outline-secondary" type="submit"><i class="bi bi-printer me-1"></i>Print selected labels</button></div>
</form>

<div class="modal fade" id="productModal" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-scrollable"><form class="modal-content" id="productForm" action="<?= base_url('admin/products') ?>" data-base-action="<?= esc(base_url('admin/products'), 'attr') ?>" method="post" enctype="multipart/form-data" data-confirm="Save this product?" data-confirm-title="Save product" data-confirm-label="Save"><?= csrf_field() ?>
<div class="modal-header"><h2 class="modal-title h5">Product details</h2><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div>
<div class="modal-body"><div class="row g-3">
<div class="col-md-7"><label class="form-label">Product name</label><input class="form-control" name="name" required maxlength="150"></div>
<div class="col-md-5"><label class="form-label">Category</label><select class="form-select" name="category_id" required><option value="">Select category</option><?php foreach ($categories as $category): ?><option value="<?= (int)$category['id'] ?>"><?= esc($category['name']) ?></option><?php endforeach; ?></select></div>
<div class="col-12"><label class="form-label">Description</label><textarea class="form-control" name="description"></textarea></div>
<div class="col-md-4"><label class="form-label">Price</label><input class="form-control" type="number" step="0.01" min="0" name="price" required></div>
<div class="col-md-4"><label class="form-label">SKU</label><input class="form-control" name="sku" maxlength="40" pattern="[A-Za-z0-9_-]*"></div>
<div class="col-md-4"><label class="form-label">Barcode</label><div class="input-group"><input class="form-control" name="barcode" maxlength="64" pattern="[A-Za-z0-9_-]*" data-product-barcode><button class="btn btn-outline-secondary" type="button" data-generate-barcode>Generate</button></div></div>
<div class="col-md-4" data-initial-stock-group><label class="form-label">Initial stock</label><input class="form-control" type="number" min="0" name="stock" value="0" required></div>
<div class="col-md-4"><label class="form-label">Reorder level</label><input class="form-control" type="number" min="0" name="reorder_level" value="5" required></div>
<div class="col-md-4"><label class="form-label">Product image</label><input class="form-control" type="file" name="image" accept="image/png,image/jpeg,image/webp"></div>
<div class="col-12 d-flex flex-wrap gap-4"><div class="form-check"><input class="form-check-input" type="checkbox" value="1" name="is_available" id="available" checked><label class="form-check-label" for="available">Available</label></div><div class="form-check"><input class="form-check-input" type="checkbox" value="1" name="is_featured" id="featured"><label class="form-check-label" for="featured">Featured</label></div><div class="form-check"><input class="form-check-input" type="checkbox" value="1" name="is_healthy_choice" id="healthyChoice"><label class="form-check-label" for="healthyChoice">Healthy choice</label></div></div>
<div class="col-12"><button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#nutritionFields"><i class="bi bi-heart-pulse me-1"></i>Nutrition facts</button></div>
<div class="col-12 collapse" id="nutritionFields"><div class="border rounded p-3"><div class="row g-3">
<div class="col-md-4"><label class="form-label">Serving size</label><input class="form-control" name="serving_size" maxlength="50"></div><div class="col-md-4"><label class="form-label">Calories</label><input class="form-control" type="number" min="0" name="calories"></div><div class="col-md-4"><label class="form-label">Sodium (mg)</label><input class="form-control" type="number" min="0" name="sodium_mg"></div>
<?php foreach (['protein_g'=>'Protein (g)','carbohydrates_g'=>'Carbohydrates (g)','fat_g'=>'Fat (g)','sugar_g'=>'Sugar (g)','fiber_g'=>'Fiber (g)'] as $name=>$label): ?><div class="col-md-4"><label class="form-label"><?= esc($label) ?></label><input class="form-control" type="number" step="0.01" min="0" name="<?= esc($name) ?>"></div><?php endforeach; ?>
<div class="col-md-8"><label class="form-label">Allergens</label><input class="form-control" name="allergens" maxlength="255" placeholder="e.g. milk, egg, peanuts"></div>
</div><small class="text-secondary">Nutrition values are estimates per serving.</small></div></div>
</div></div>
<div class="modal-footer"><button class="btn btn-light" type="button" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Save product</button></div></form></div></div>

<div class="modal fade" id="productQrModal" tabindex="-1"><div class="modal-dialog modal-sm modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h2 class="modal-title h5">Menu item QR</h2><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div><div class="modal-body text-center"><div data-product-qr-output></div><a class="btn btn-outline-primary mt-3" data-product-qr-link target="_blank" rel="noopener">Open public page</a></div></div></div></div>
<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script src="<?= base_url('assets/vendor/qrcode-generator/qrcode.min.js') ?>"></script>
<script src="<?= base_url('assets/js/admin-products.js') ?>"></script>
<?= $this->endSection() ?>
