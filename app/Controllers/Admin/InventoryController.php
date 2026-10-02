<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\InventoryMovementModel;
use App\Models\ProductModel;
use App\Services\AuditLogService;
use App\Services\InventoryService;
use App\Services\NotificationService;
use Throwable;

class InventoryController extends BaseController
{
    public function index(): string
    {
        $role = (string) (session()->get('user')['role'] ?? '');
        $productId = (int) ($this->request->getGet('product_id') ?? 0);
        $movements = [];
        if ($productId > 0) {
            $movements = (new InventoryMovementModel())
                ->select('inventory_movements.*, users.name AS user_name')
                ->join('users', 'users.id=inventory_movements.user_id', 'left')
                ->where('product_id', $productId)->orderBy('created_at', 'DESC')->findAll(100);
        }
        return $this->render('admin/inventory/index', [
            'title' => 'Inventory',
            'products' => (new ProductModel())->withCategory(),
            'movements' => $movements,
            'selectedProductId' => $productId,
            'canEdit' => $role === 'admin',
        ]);
    }

    public function stockIn()
    {
        return $this->mutate('stock_in');
    }

    public function adjust()
    {
        return $this->mutate('adjustment');
    }

    public function waste()
    {
        return $this->mutate('waste');
    }

    public function reconcile()
    {
        $db = db_connect();
        $rows = $db->query(
            'SELECT p.id,p.name,p.stock,COALESCE(SUM(m.quantity),0) ledger_stock FROM products p LEFT JOIN inventory_movements m ON m.product_id=p.id WHERE p.deleted_at IS NULL GROUP BY p.id,p.name,p.stock HAVING p.stock <> ledger_stock ORDER BY p.name'
        )->getResultArray();
        if ($rows === []) {
            return redirect()->to('/admin/inventory')->with('success', 'Inventory reconciliation passed with zero stock mismatches.');
        }
        return redirect()->to('/admin/inventory')->with('error', 'Inventory reconciliation found ' . count($rows) . ' stock mismatch(es). Run php spark inventory:reconcile for details.');
    }

    private function mutate(string $type)
    {
        $productId = filter_var($this->request->getPost('product_id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $quantity = filter_var($this->request->getPost('quantity'), FILTER_VALIDATE_INT);
        $note = trim((string) $this->request->getPost('note'));
        $reference = trim((string) $this->request->getPost('reference'));
        if ($productId === false || $quantity === false) {
            return redirect()->to('/admin/inventory')->with('error', 'Select a product and enter a valid quantity.');
        }
        $actorId = (int) (session()->get('user')['id'] ?? 0);
        $db = db_connect();
        $db->transBegin();
        try {
            $inventory = new InventoryService($db);
            $product = match ($type) {
                'stock_in' => $inventory->stockIn((int) $productId, (int) $quantity, $actorId, $reference ?: null, $note ?: null),
                'adjustment' => $inventory->adjust((int) $productId, (int) $quantity, $actorId, $note),
                'waste' => $inventory->recordWaste((int) $productId, abs((int) $quantity), $actorId, $note),
                default => throw new \DomainException('Invalid inventory operation.'),
            };
            (new NotificationService($db))->lowStock($product);
            (new AuditLogService($db))->record('inventory_' . $type, 'product', (int) $productId, ['quantity' => (int) $quantity, 'reference' => $reference ?: null]);
            if (! $db->transStatus()) {
                throw new \RuntimeException('Unable to save inventory change.');
            }
            $db->transCommit();
            return redirect()->to('/admin/inventory?product_id=' . (int) $productId)->with('success', 'Inventory updated.');
        } catch (Throwable $exception) {
            $db->transRollback();
            return redirect()->to('/admin/inventory?product_id=' . (int) $productId)->with('error', $exception->getMessage());
        }
    }
}
