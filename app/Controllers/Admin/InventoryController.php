<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\InventoryMovementModel;
use App\Models\ProductModel;
use App\Services\AuditLogService;
use App\Services\InventoryService;
use App\Services\NotificationService;
use App\Services\StockLevelPolicy;
use Throwable;

class InventoryController extends BaseController
{
    public function index(): string
    {
        $role = (string) (session()->get('user')['role'] ?? '');
        $productId = (int) ($this->request->getGet('product_id') ?? 0);
        // The filtered, paginated stock card lives at inventory/stock-card.
        $movements = [];
        $balances = db_connect()->query("SELECT product_id, COALESCE(SUM(quantity),0) ledger_balance FROM inventory_movements GROUP BY product_id")->getResultArray();
        $balancesById = array_column($balances, 'ledger_balance', 'product_id');
        $settings = StockLevelPolicy::settings();
        $products = (new ProductModel())->withCategory();
        $summary = StockLevelPolicy::summary($products, $settings);
        $level = (string) $this->request->getGet('level');
        if (! in_array($level, ['all', 'green', 'yellow', 'red', 'empty'], true)) $level = 'all';
        // Urgent stock must come first, even when the user is viewing all products.
        usort($products, static function (array $a, array $b) use ($settings): int {
            $severity = ['empty' => 0, 'red' => 1, 'yellow' => 2, 'green' => 3];
            $first = StockLevelPolicy::forProduct($a, $settings)['level'];
            $second = StockLevelPolicy::forProduct($b, $settings)['level'];
            return ($severity[$first] <=> $severity[$second]) ?: strcmp($a['name'], $b['name']);
        });
        $visible = $level === 'all' ? $products : array_values(array_filter($products,
            static fn (array $p): bool => StockLevelPolicy::forProduct($p, $settings)['level'] === $level));
        return $this->render('admin/inventory/index', ['balancesById' => $balancesById,
            'title' => 'Inventory',
            'products' => $visible,
            'allProducts' => $products,
            'stockSettings' => $settings, 'stockSummary' => $summary, 'levelFilter' => $level,
            'movements' => $movements,
            'selectedProductId' => $productId,
            'canEdit' => $role === 'admin',
        ]);
    }

    private function stockCardData(int $page=1,int $pageSize=25): array
    {
        $ledger=(string)($this->request->getGet('ledger') ?: 'product');
        $id=(int)($this->request->getGet($ledger==='ingredient'?'ingredient_id':'product_id') ?: 0);
        if ($id < 1 && in_array($ledger, ['product','ingredient'], true)) {
            $table=$ledger==='ingredient'?'ingredients':'products';
            $id=(int)(db_connect()->table($table)->select('id')->orderBy('id')->get(1)->getRow('id')??0);
        }
        $from=(string)($this->request->getGet('from') ?: date('Y-m-01'));
        $to=(string)($this->request->getGet('to') ?: date('Y-m-d'));
        $type=trim((string)$this->request->getGet('movement_type'));
        foreach ([$from,$to] as $date) {
            $d=\DateTimeImmutable::createFromFormat('!Y-m-d',$date);
            if (!$d || $d->format('Y-m-d')!==$date) throw new \DomainException('Invalid stock card date.');
        }
        return (new \App\Services\StockCardService())->get($ledger,$id,$from,$to,$type,$page,$pageSize);
    }

    public function stockCard(): string
    {
        $page=max(1,(int)$this->request->getGet('page'));
        $card=$this->stockCardData($page);
        $db=db_connect();
        $items=$card['ledger']==='ingredient'
            ? $db->table('ingredients')->select('id,name')->orderBy('name')->get()->getResultArray()
            : $db->table('products')->select('id,name')->where('deleted_at',null)->orderBy('name')->get()->getResultArray();
        $pager = service('pager');
        $pager->only(['ledger', 'product_id', 'ingredient_id', 'from', 'to', 'movement_type']);
        $pageLinks = $pager->makeLinks($card['page'], $card['perPage'], $card['total'], 'default_full');
        return $this->render('admin/inventory/stock_card',[
            'title'=>'Stock card','card'=>$card,'items'=>$items,'pagerLinks'=>$pageLinks,
            'canEdit'=>(session()->get('user')['role']??'')==='admin',
        ]);
    }

    public function stockCardPrint(): string
    {
        $card = $this->stockCardData(1, 25);
        // The print page deliberately has no CI pager; it includes every
        // movement matching the filters, including pages 2, 3, and beyond.
        return view('admin/inventory/stock_card_print', [
            'card' => $card,
            'rows' => (new \App\Services\StockCardService())->exportRows(
                $card['ledger'], $card['id'], $card['from'], $card['to'], $card['type'],
            ),
            'generatedBy' => (string) (session()->get('user')['name'] ?? 'Staff'),
        ]);
    }

    public function stockCardExport()
    {
        $card = $this->stockCardData(1, 25);
        $stream = fopen('php://temp', 'w+');
        if ($stream === false) {
            throw new \RuntimeException('Unable to create stock card export.');
        }
        fwrite($stream, "\xEF\xBB\xBF");
        $put = static function (array $cells) use ($stream): void {
            fputcsv($stream, array_map([\App\Controllers\Admin\ReportController::class, 'safeCsvCell'], $cells), ',', '"', '');
        };
        $put(['Stock card', $card['ledger'] . ' #' . $card['id']]);
        $put(['Period', $card['from'] . ' to ' . $card['to']]);
        $put(['Generated at', date('Y-m-d H:i:s')]);
        $put(['Generated by', (string) (session()->get('user')['name'] ?? 'Staff')]);
        $put(['Opening balance', $card['opening']]);
        $put(['Date', 'Movement', 'Quantity', 'Running balance', 'Actor', 'Reference', 'Note']);
        $exported = 0;
        foreach ((new \App\Services\StockCardService())->exportRows(
            $card['ledger'], $card['id'], $card['from'], $card['to'], $card['type'],
        ) as $row) {
            $put([$row['created_at'], $row['movement_type'], $row['quantity'], $row['running_balance'],
                $row['user_name'] ?? 'System', $row['reference'] ?? '', $row['note'] ?? '']);
            $exported++;
        }
        $put(['Movement rows', $exported]);
        $put(['Closing balance', $card['closing']]);
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);
        return $this->response->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="stock-card-' . $card['ledger'] . '-' . $card['id'] . '.csv"')
            ->setBody($csv ?: '');
    }

    public function stockIn()
    {
        return $this->mutate('stock_in');
    }

    public function adjust()
    {
        return $this->mutate('adjustment');
    }

    public function stockOut()
    {
        return $this->mutate('stock_out');
    }

    public function count()
    {
        return $this->mutate('count');
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

    /** Post one reconciliation adjustment per product for a physical scan session. */
    public function batchCount()
    {
        $payload=$this->request->getJSON(true);
        $items=$payload['items']??null;
        if(!is_array($items) || !$items || count($items)>100)return $this->jsonError('Count session must contain 1–100 products.',null,422);
        $counts=[];
        foreach($items as $item){
            if(!is_array($item))return $this->jsonError('Invalid count item.',null,422);
            $id=filter_var($item['product_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
            $quantity=filter_var($item['counted_quantity']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>0,'max_range'=>500000]]);
            if($id===false||$quantity===false||isset($counts[$id]))return $this->jsonError('Duplicate or invalid product count.',null,422);
            $counts[$id]=$quantity;
        }
        ksort($counts,SORT_NUMERIC);
        $db=db_connect();$db->transBegin();
        try{
            $inventory=new InventoryService($db);
            $alerts=new NotificationService($db);
            $actor=(int)(session()->get('user')['id']??0);
            foreach($counts as $id=>$quantity){
                $product=$inventory->count((int)$id,(int)$quantity,$actor,'Physical barcode scan session');
                $alerts->lowStock($product);
            }
            (new AuditLogService($db))->record('inventory_count_session','inventory',null,['products'=>count($counts)],$actor);
            if(!$db->transStatus())throw new \RuntimeException('Unable to reconcile all counts.');
            $db->transCommit();return $this->jsonSuccess('Physical count session reconciled.', ['products'=>count($counts)]);
        }catch(Throwable $e){$db->transRollback();return $this->jsonError($e->getMessage(),null,422);}
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
                'stock_out' => $inventory->stockOut((int) $productId, (int) $quantity, $actorId, $note),
                'count' => $inventory->count((int) $productId, (int) $quantity, $actorId, $note),
                'waste' => $inventory->recordWaste((int) $productId, abs((int) $quantity), $actorId, $note),
                default => throw new \DomainException('Invalid inventory operation.'),
            };
            (new NotificationService($db))->lowStock($product);
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
