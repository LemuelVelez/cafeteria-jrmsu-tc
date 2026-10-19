<?php

namespace App\Database\Seeds;

use App\Services\DemoProductRecipeSeedService;
use CodeIgniter\Database\Seeder;

/** Seeds only the kitchen-approved chicken-adobo-rice recipe; never guesses measurements. */
final class ChickenAdoboRiceSeeder extends Seeder
{
    public function run(): void
    {
        (new DemoProductRecipeSeedService($this->db))->seed('chicken-adobo-rice');
    }
}
