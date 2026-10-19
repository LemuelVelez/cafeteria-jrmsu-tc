<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AssignMenuImageUrls extends Migration
{
    public function up(): void
    {
        foreach (config(\Config\MenuImages::class)->products as $slug => $entry) {
            $this->db->table('products')->where('slug', $slug)
                ->groupStart()->where('image', null)->orWhere('image', '')->groupEnd()
                ->update(['image' => $entry['url']]);
        }
    }

    public function down(): void
    {
        foreach (config(\Config\MenuImages::class)->products as $slug => $entry) {
            $this->db->table('products')->where('slug', $slug)->where('image', $entry['url'])
                ->update(['image' => null]);
        }
    }
}
