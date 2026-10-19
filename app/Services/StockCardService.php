<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

/** Every balance comes from the movement ledger, not the current stock column. */
final class StockCardService
{
    public function __construct(private readonly ?BaseConnection $connection = null) {}
    private function db(): BaseConnection { return $this->connection ?? db_connect(); }

    public function get(string $ledger, int $id, string $from, string $to, string $type='', int $page=1, int $perPage=25): array
    {
        if (! in_array($ledger,['product','ingredient'],true)) throw new \DomainException('Unsupported stock card.');
        if ($id < 0 || $from > $to || $perPage<1 || $perPage>50000) throw new \DomainException('Invalid stock card request.');
        // A fresh database can have no ingredients yet; show an empty stock card
        // instead of making both admin and cashier inventory pages crash.
        if ($id === 0) return [
            'rows' => [], 'opening' => 0.0, 'closing' => 0.0, 'total' => 0,
            'page' => 1, 'perPage' => $perPage, 'from' => $from, 'to' => $to,
            'type' => $type, 'ledger' => $ledger, 'id' => $id,
        ];
        if ($type !== '' && !preg_match('/^[a-z_]{1,32}$/',$type)) throw new \DomainException('Invalid movement type.');
        $table=$ledger==='product'?'inventory_movements':'ingredient_movements';
        $column=$ledger==='product'?'product_id':'ingredient_id';
        $db=$this->db();
        $begin=$from.' 00:00:00'; $end=$to.' 23:59:59';
        $opening=(float)($db->query("SELECT COALESCE(SUM(quantity),0) q FROM {$table} WHERE {$column}=? AND created_at < ?",[$id,$begin])->getRow('q') ?? 0);
        $closing=(float)($db->query("SELECT COALESCE(SUM(quantity),0) q FROM {$table} WHERE {$column}=? AND created_at <= ?",[$id,$end])->getRow('q') ?? 0);
        $conditions="m.{$column}=? AND m.created_at>=? AND m.created_at<=?";
        $params=[$id,$begin,$end];
        if ($type!=='') { $conditions.=' AND m.movement_type=?'; $params[]=$type; }
        $total=(int)($db->query("SELECT COUNT(*) n FROM {$table} m WHERE {$conditions}",$params)->getRow('n')??0);
        $page=max(1,min($page,max(1,(int)ceil($total/$perPage))));
        $offset=($page-1)*$perPage;
        $rows=$db->query("SELECT x.* FROM (
                SELECT m.*,u.name user_name,
                  SUM(m.quantity) OVER (PARTITION BY m.{$column} ORDER BY m.created_at,m.id ROWS UNBOUNDED PRECEDING) running_balance
                FROM {$table} m LEFT JOIN users u ON u.id=m.user_id
                WHERE m.{$column}=? AND m.created_at<=?
            ) x WHERE x.created_at >= ?" . ($type!==''?' AND x.movement_type=?':'') .
            " ORDER BY x.created_at DESC,x.id DESC LIMIT {$perPage} OFFSET {$offset}",
            $type!==''?[$id,$end,$begin,$type]:[$id,$end,$begin])->getResultArray();
        return compact('rows','opening','closing','total','page','perPage','from','to','type','ledger','id');
    }
}
