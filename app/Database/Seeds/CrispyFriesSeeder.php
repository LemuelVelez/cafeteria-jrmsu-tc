<?php

namespace App\Database\Seeds;

use App\Services\DemoProductRecipeSeedService;
use CodeIgniter\Database\Seeder;

/** Seeds only the kitchen-approved crispy-fries recipe; never guesses measurements. */
final class CrispyFriesSeeder extends Seeder
{
    public function run(): void
    {
        (new DemoProductRecipeSeedService($this->db))->seed('crispy-fries');
    }
}
