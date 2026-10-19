<?php

namespace App\Database\Seeds;

use App\Services\DemoProductRecipeSeedService;
use CodeIgniter\Database\Seeder;

/** Seeds only the kitchen-approved pork-sisig-rice recipe; never guesses measurements. */
final class PorkSisigRiceSeeder extends Seeder
{
    public function run(): void
    {
        (new DemoProductRecipeSeedService($this->db))->seed('pork-sisig-rice');
    }
}
