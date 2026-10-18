<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

class ReportService
{
    public function __construct(private readonly ?BaseConnection $connection = null)
    {
    }

    public function profit(string $from,string $to): array
    {
        $rows=$this->db()->query("SELECT oi.product_id,oi.product_name,c.name category,COALESCE(SUM(oi.line_total),0) revenue,COALESCE(SUM(oi.quantity*oi.unit_cost),0) cost,COALESCE(SUM(oi.line_total-oi.quantity*oi.unit_cost),0) gross_profit FROM order_items oi JOIN orders o ON o.id=oi.order_id LEFT JOIN products p ON p.id=oi.product_id LEFT JOIN categories c ON c.id=p.category_id WHERE o.payment_status='paid' AND DATE(o.created_at)>=? AND DATE(o.created_at)<=? GROUP BY oi.product_id,oi.product_name,c.name ORDER BY gross_profit DESC",[$from,$to])->getResultArray();
        return ['rows'=>$rows,'revenue'=>array_sum(array_column($rows,'revenue')),'cost'=>array_sum(array_column($rows,'cost')),'profit'=>array_sum(array_column($rows,'gross_profit'))];
    }
    public function lowStock(string $from,string $to): array
    {
        // Reconstruct the balance at the end of the selected day from immutable movements.
        // Reorder and par levels are the current thresholds; their history is not recorded.
        return $this->db()->query("SELECT p.id,p.name,p.sku,COALESCE(t.balance,0) stock,p.reorder_level,p.max_stock,GREATEST(0,p.max_stock-COALESCE(t.balance,0)) suggested_reorder FROM products p LEFT JOIN (SELECT product_id,SUM(quantity) balance FROM inventory_movements WHERE created_at < ? GROUP BY product_id) t ON t.product_id=p.id WHERE p.deleted_at IS NULL AND (p.created_at IS NULL OR p.created_at < ?) AND COALESCE(t.balance,0)<=p.reorder_level ORDER BY stock,p.name",[$to.' 23:59:59.999999',$to.' 23:59:59.999999'])->getResultArray();
    }

    /** Payroll estimates are in the admin reports and staff attendance view only when enabled. */
    public function attendance(string $from,string $to): array
    {
        $db=$this->db();
        $settings=$db->table('settings')->whereIn('setting_key',['attendance_overtime_premium','attendance_night_differential'])->get()->getResultArray();
        $values=array_column($settings,'setting_value','setting_key');
        $premium=max(0,(float)($values['attendance_overtime_premium']??0.25));
        $night=max(0,(float)($values['attendance_night_differential']??0.10));
        $staff=$db->table('users')->select('id,name,role')->whereIn('role',['admin','cashier','rider'])->get()->getResultArray();
        $rows=[];
        $scanEnd=(new \DateTimeImmutable($to.' 00:00:00'))->modify('+2 days')->format('Y-m-d H:i:s');
        foreach($staff as $person){
            $id=(int)$person['id'];
            $punches=$db->table('attendance_punches')->where('user_id',$id)->where('at >=',$from.' 00:00:00')->where('at <',$scanEnd)->orderBy('at')->get()->getResultArray();
            for($i=0;$i+1<count($punches);$i++){
                if($punches[$i]['kind']!=='in' || $punches[$i+1]['kind']!=='out')continue;
                $in=$punches[$i]['at'];$day=substr($in,0,10);
                if($day>$to)break;
                $shift=$db->table('attendance_shifts')->where(['user_id'=>$id,'weekday'=>(int)date('N',strtotime($in))])->get()->getRowArray();
                $rates=$db->table('attendance_pay_rates')->where('user_id',$id)->where('effective_from <=',$day)->orderBy('effective_from','DESC')->limit(1)->get()->getRowArray();
                $results=AttendanceService::estimate($in,$punches[$i+1]['at'],$shift['start_time']??'08:00:00',$shift['end_time']??'17:00:00',(float)($rates['hourly_rate']??0),(int)($shift['grace_minutes']??5),$premium,$night);
                $rows[]=array_merge(['employee'=>$person['name'],'date'=>$day],$results);
                $i++;
            }
        }
        return $rows;
    }

    public function audit(string $from,string $to): array
    {
        return $this->db()->query('SELECT a.id,a.created_at,a.action,a.entity_type,a.entity_id,a.details_json,u.name user_name FROM audit_logs a LEFT JOIN users u ON a.user_id=u.id WHERE DATE(a.created_at)>=? AND DATE(a.created_at)<=? ORDER BY a.created_at DESC',[$from,$to])->getResultArray();
    }
    public function sales(string $from, string $to): array
    {
        $db = $this->db();
        $where = 'DATE(orders.created_at) >= ? AND DATE(orders.created_at) <= ? AND orders.payment_status = \'paid\'';
        $summary = $db->query(
            "SELECT COUNT(*) orders, COALESCE(SUM(subtotal),0) gross_sales, COALESCE(SUM(discount),0) discounts, COALESCE(SUM(delivery_fee),0) delivery_fees, COALESCE(SUM(total),0) net_sales, COALESCE(AVG(total),0) average_order_value FROM orders WHERE {$where}",
            [$from, $to],
        )->getRowArray() ?? [];
        $daily = $db->query(
            "SELECT DATE(created_at) sale_date, COUNT(*) orders, COALESCE(SUM(total),0) total FROM orders WHERE DATE(created_at) >= ? AND DATE(created_at) <= ? AND payment_status='paid' GROUP BY DATE(created_at) ORDER BY sale_date",
            [$from, $to],
        )->getResultArray();
        $byType = $db->query("SELECT order_type label, COUNT(*) orders, COALESCE(SUM(total),0) total FROM orders WHERE {$where} GROUP BY order_type", [$from, $to])->getResultArray();
        $byPayment = $db->query("SELECT payment_method label, COUNT(*) orders, COALESCE(SUM(total),0) total FROM orders WHERE {$where} GROUP BY payment_method", [$from, $to])->getResultArray();
        $byChannel = $db->query("SELECT IF(cashier_id IS NULL,'online','pos') label, COUNT(*) orders, COALESCE(SUM(total),0) total FROM orders WHERE {$where} GROUP BY label", [$from, $to])->getResultArray();
        return compact('summary', 'daily', 'byType', 'byPayment', 'byChannel');
    }

    public function orders(string $from, string $to): array
    {
        $db = $this->db();
        $rows = $db->query('SELECT * FROM orders WHERE DATE(created_at) >= ? AND DATE(created_at) <= ? ORDER BY created_at DESC', [$from, $to])->getResultArray();
        $status = $db->query('SELECT status, COUNT(*) count FROM orders WHERE DATE(created_at) >= ? AND DATE(created_at) <= ? GROUP BY status', [$from, $to])->getResultArray();
        $type = $db->query('SELECT order_type, COUNT(*) count FROM orders WHERE DATE(created_at) >= ? AND DATE(created_at) <= ? GROUP BY order_type', [$from, $to])->getResultArray();
        $channel = $db->query("SELECT IF(cashier_id IS NULL,'online','pos') channel, COUNT(*) count FROM orders WHERE DATE(created_at) >= ? AND DATE(created_at) <= ? GROUP BY channel", [$from, $to])->getResultArray();
        $total = count($rows);
        $cancelled = count(array_filter($rows, static fn (array $row): bool => $row['status'] === 'cancelled'));
        $prep = $db->query(
            "SELECT AVG(TIMESTAMPDIFF(MINUTE, c.created_at, r.created_at)) avg_minutes FROM order_status_history c JOIN order_status_history r ON r.order_id=c.order_id AND r.to_status='ready_for_pickup' WHERE c.to_status='confirmed' AND DATE(c.created_at) >= ? AND DATE(c.created_at) <= ?",
            [$from, $to],
        )->getRowArray();
        $issues=(int)($db->query('SELECT COUNT(*) n FROM order_issues i JOIN orders o ON o.id=i.order_id WHERE DATE(o.created_at)>=? AND DATE(o.created_at)<=?',[$from,$to])->getRow('n')??0);
        return ['issueRate' => $total?round($issues/$total*100,2):0, 'rows' => $rows, 'byStatus' => $status, 'byType' => $type, 'byChannel' => $channel, 'cancellationRate' => $total ? ($cancelled / $total) * 100 : 0, 'averagePreparationMinutes' => (float) ($prep['avg_minutes'] ?? 0)];
    }

    public function inventory(string $from, string $to): array
    {
        $db = $this->db();
        $products = $db->query('SELECT id, name, sku, stock, reorder_level, price, stock * price stock_value FROM products WHERE deleted_at IS NULL ORDER BY name')->getResultArray();
        $movements = $db->query(
            "SELECT product_id, movement_type, SUM(quantity) quantity FROM inventory_movements WHERE DATE(created_at) >= ? AND DATE(created_at) <= ? GROUP BY product_id, movement_type",
            [$from, $to],
        )->getResultArray();
        $summary = [];
        foreach ($movements as $row) {
            $summary[(int) $row['product_id']][$row['movement_type']] = (int) $row['quantity'];
        }
        $openingRows = $db->query(
            'SELECT product_id, COALESCE(SUM(quantity),0) quantity FROM inventory_movements WHERE created_at < ? GROUP BY product_id',
            [$from . ' 00:00:00'],
        )->getResultArray();
        $opening = [];
        foreach ($openingRows as $row) $opening[(int) $row['product_id']] = (int) $row['quantity'];
        foreach ($products as &$product) {
            $id = (int) $product['id'];
            $periodMovement = array_sum($summary[$id] ?? []);
            $product['opening_stock'] = $opening[$id] ?? 0;
            $product['closing_stock'] = $product['opening_stock'] + $periodMovement;
        }
        unset($product);
        return [
            'products' => $products,
            'movements' => $summary,
            'lowStock' => array_values(array_filter($products, static fn (array $p): bool => (int) $p['stock'] > 0 && (int) $p['stock'] <= (int) $p['reorder_level'])),
            'outOfStock' => array_values(array_filter($products, static fn (array $p): bool => (int) $p['stock'] === 0)),
        ];
    }

    public function popular(string $from, string $to): array
    {
        $db = $this->db();
        $base = "FROM order_items JOIN orders ON orders.id=order_items.order_id WHERE DATE(orders.created_at)>=? AND DATE(orders.created_at)<=? AND orders.payment_status='paid'";
        $top = $db->query("SELECT product_name, SUM(quantity) quantity, SUM(line_total) revenue {$base} GROUP BY product_name ORDER BY quantity DESC LIMIT 10", [$from, $to])->getResultArray();
        $slow = $db->query("SELECT products.name product_name, COALESCE(SUM(CASE WHEN orders.payment_status='paid' AND DATE(orders.created_at)>=? AND DATE(orders.created_at)<=? THEN order_items.quantity ELSE 0 END),0) quantity, COALESCE(SUM(CASE WHEN orders.payment_status='paid' AND DATE(orders.created_at)>=? AND DATE(orders.created_at)<=? THEN order_items.line_total ELSE 0 END),0) revenue FROM products LEFT JOIN order_items ON order_items.product_id=products.id LEFT JOIN orders ON orders.id=order_items.order_id WHERE products.deleted_at IS NULL GROUP BY products.id,products.name ORDER BY quantity ASC,products.name LIMIT 10", [$from, $to, $from, $to])->getResultArray();
        $categories = $db->query("SELECT categories.name category, SUM(order_items.quantity) quantity, SUM(order_items.line_total) revenue FROM order_items JOIN orders ON orders.id=order_items.order_id JOIN products ON products.id=order_items.product_id JOIN categories ON categories.id=products.category_id WHERE DATE(orders.created_at)>=? AND DATE(orders.created_at)<=? AND orders.payment_status='paid' GROUP BY categories.id, categories.name ORDER BY revenue DESC", [$from, $to])->getResultArray();

        // Add-ons are stored as the immutable order-time JSON snapshot. Aggregate in PHP so the report works consistently across supported MySQL setups.
        $addonRows = $db->query("SELECT order_items.quantity, order_items.addons_json {$base} AND order_items.addons_json IS NOT NULL", [$from, $to])->getResultArray();
        $addonTotals = [];
        foreach ($addonRows as $row) {
            foreach (json_decode((string) $row['addons_json'], true) ?: [] as $addon) {
                $name = trim((string) ($addon['name'] ?? ''));
                if ($name === '') continue;
                $quantity = (int) $row['quantity'];
                $unitPrice = (float) ($addon['price'] ?? 0);
                $addonTotals[$name] ??= ['addon_name' => $name, 'quantity' => 0, 'revenue' => 0.0];
                $addonTotals[$name]['quantity'] += $quantity;
                $addonTotals[$name]['revenue'] += $quantity * $unitPrice;
            }
        }
        $addons = array_values($addonTotals);
        usort($addons, static fn (array $a, array $b): int => $b['quantity'] <=> $a['quantity']);
        $addons = array_slice($addons, 0, 10);
        return compact('top', 'slow', 'categories', 'addons');
    }

    public function deliveries(string $from, string $to): array
    {
        $db = $this->db();
        $riders = $db->query(
            "SELECT COALESCE(users.name,'Unassigned') rider, COUNT(*) total, SUM(orders.status='delivered') completed, SUM(orders.status='cancelled') cancelled, COALESCE(SUM(CASE WHEN orders.payment_status='paid' THEN orders.delivery_fee ELSE 0 END),0) delivery_fees, COALESCE(SUM(CASE WHEN orders.payment_status='paid' AND orders.payment_method='cash_on_delivery' THEN orders.total ELSE 0 END),0) cod_collected FROM orders LEFT JOIN users ON users.id=orders.rider_id WHERE orders.order_type='delivery' AND DATE(orders.created_at)>=? AND DATE(orders.created_at)<=? GROUP BY orders.rider_id, users.name ORDER BY completed DESC",
            [$from, $to],
        )->getResultArray();
        $avg = $db->query(
            "SELECT AVG(TIMESTAMPDIFF(MINUTE, o.created_at, c.created_at)) avg_minutes FROM order_status_history o JOIN order_status_history c ON c.order_id=o.order_id AND c.to_status='delivered' WHERE o.to_status='out_for_delivery' AND DATE(o.created_at)>=? AND DATE(o.created_at)<=?",
            [$from, $to],
        )->getRowArray();
        return ['riders' => $riders, 'averageDeliveryMinutes' => (float) ($avg['avg_minutes'] ?? 0)];
    }

    public function todaySummary(): array
    {
        $today = date('Y-m-d');
        $sales = $this->sales($today, $today)['summary'];
        $statuses = $this->db()->query('SELECT status, COUNT(*) count FROM orders WHERE DATE(created_at)=? GROUP BY status', [$today])->getResultArray();
        $low = (int) $this->db()->query('SELECT COUNT(*) count FROM products WHERE deleted_at IS NULL AND stock <= reorder_level')->getRow('count');
        return ['sales' => $sales, 'statuses' => $statuses, 'lowStockCount' => $low];
    }

    private function db(): BaseConnection
    {
        return $this->connection ?? db_connect();
    }
}
