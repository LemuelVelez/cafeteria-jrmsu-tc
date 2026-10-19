<?php

namespace App\Database\Seeds;

use App\Services\DemoProductRecipeSeedService;
use CodeIgniter\Database\Seeder;

/** Seeds only the kitchen-approved fried-chicken-meal recipe; never guesses measurements. */
final class FriedChickenMealSeeder extends Seeder
{
    public function run(): void
    {
        (new DemoProductRecipeSeedService($this->db))->seed('fried-chicken-meal');
    }
}
