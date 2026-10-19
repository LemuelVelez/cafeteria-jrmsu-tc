<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Remap demo codes only when both the stock slug and original code match. */
final class ReassignDemoBarcodes extends Migration
{
    private const CODES = [
        'chicken-adobo-rice' => ['480000000101', '2000000000015'],
        'pork-sisig-rice' => ['480000000102', '2000000000022'],
        'fried-chicken-meal' => ['480000000103', '2000000000039'],
        'cheese-burger' => ['480000000104', '2000000000046'],
        'crispy-fries' => ['480000000105', '2000000000053'],
        'iced-spanish-latte' => ['480000000106', '2000000000060'],
        'hot-americano' => ['480000000107', '2000000000077'],
        'calamansi-juice' => ['480000000108', '2000000000084'],
    ];

    public function up(): void
    {
        foreach (self::CODES as $slug => [$old, $new]) {
            $this->db->table('products')->where('slug', $slug)->where('barcode', $old)->update(['barcode' => $new]);
        }
    }

    public function down(): void
    {
        foreach (self::CODES as $slug => [$old, $new]) {
            $this->db->table('products')->where('slug', $slug)->where('barcode', $new)->update(['barcode' => $old]);
        }
    }
}
