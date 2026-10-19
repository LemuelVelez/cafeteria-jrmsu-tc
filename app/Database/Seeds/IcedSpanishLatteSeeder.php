<?php

namespace App\Database\Seeds;

use App\Services\DemoProductRecipeSeedService;
use CodeIgniter\Database\Seeder;

/** Seeds only the kitchen-approved iced-spanish-latte recipe; never guesses measurements. */
final class IcedSpanishLatteSeeder extends Seeder
{
    public function run(): void
    {
        (new DemoProductRecipeSeedService($this->db))->seed('iced-spanish-latte');
    }
}
