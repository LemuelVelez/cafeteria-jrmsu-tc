<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $categories = array_column($this->db->table('categories')->get()->getResultArray(), 'id', 'slug');
        $now = date('Y-m-d H:i:s');
        $rows = [
            ['category_id'=>$categories['rice-meals'],'name'=>'Chicken Adobo Rice','slug'=>'chicken-adobo-rice','description'=>'Tender chicken adobo with steamed rice and vegetables.','price'=>95,'stock'=>40,'sku'=>'RICE-ADOBO','barcode'=>'2000000000015','reorder_level'=>8,'serving_size'=>'1 plate (350 g)','allergens'=>'soy','is_healthy_choice'=>0,'is_featured'=>1],
            ['category_id'=>$categories['rice-meals'],'name'=>'Pork Sisig Rice','slug'=>'pork-sisig-rice','description'=>'Sizzling-style pork sisig served with rice.','price'=>105,'stock'=>35,'sku'=>'RICE-SISIG','barcode'=>'2000000000022','reorder_level'=>7,'serving_size'=>'1 plate (330 g)','allergens'=>'soy, egg','is_healthy_choice'=>0,'is_featured'=>1],
            ['category_id'=>$categories['rice-meals'],'name'=>'Fried Chicken Meal','slug'=>'fried-chicken-meal','description'=>'Crispy fried chicken, gravy, and steamed rice.','price'=>110,'stock'=>30,'sku'=>'RICE-FCHICK','barcode'=>'2000000000039','reorder_level'=>7,'serving_size'=>'1 plate (360 g)','allergens'=>'wheat, milk, egg','is_healthy_choice'=>0,'is_featured'=>1],
            ['category_id'=>$categories['snacks'],'name'=>'Cheese Burger','slug'=>'cheese-burger','description'=>'Beef patty, cheese, lettuce, and house dressing.','price'=>75,'stock'=>25,'sku'=>'SNACK-BURGER','barcode'=>'2000000000046','reorder_level'=>5,'serving_size'=>'1 burger (190 g)','allergens'=>'wheat, milk, egg','is_healthy_choice'=>0,'is_featured'=>0],
            ['category_id'=>$categories['snacks'],'name'=>'Crispy Fries','slug'=>'crispy-fries','description'=>'Golden fries with ketchup or cheese dip.','price'=>55,'stock'=>50,'sku'=>'SNACK-FRIES','barcode'=>'2000000000053','reorder_level'=>10,'serving_size'=>'120 g','allergens'=>null,'is_healthy_choice'=>0,'is_featured'=>0],
            ['category_id'=>$categories['coffee'],'name'=>'Iced Spanish Latte','slug'=>'iced-spanish-latte','description'=>'Espresso, creamy milk, and lightly sweetened condensed milk.','price'=>85,'stock'=>45,'sku'=>'COF-SPANISH','barcode'=>'2000000000060','reorder_level'=>8,'serving_size'=>'16 fl oz','allergens'=>'milk','is_healthy_choice'=>0,'is_featured'=>1],
            ['category_id'=>$categories['coffee'],'name'=>'Hot Americano','slug'=>'hot-americano','description'=>'Bold espresso with hot water.','price'=>60,'stock'=>45,'sku'=>'COF-AMER','barcode'=>'2000000000077','reorder_level'=>8,'serving_size'=>'12 fl oz','allergens'=>null,'is_healthy_choice'=>0,'is_featured'=>0],
            ['category_id'=>$categories['cold-drinks'],'name'=>'Calamansi Juice','slug'=>'calamansi-juice','description'=>'Fresh local calamansi juice served chilled.','price'=>45,'stock'=>60,'sku'=>'DRINK-CALAM','barcode'=>'2000000000084','reorder_level'=>10,'serving_size'=>'12 fl oz','allergens'=>null,'is_healthy_choice'=>0,'is_featured'=>0],
        ];
        $catalog = config(\Config\MenuImages::class)->products;
        foreach ($rows as &$row) {
            $row['image'] = $catalog[$row['slug']]['url'];
        }
        unset($row);
        $productTable = $this->db->table('products');
        $allergenService = new \App\Services\AllergenService($this->db);
        $knownAllergens = array_column($allergenService->options(), 'id', 'code');
        foreach ($rows as $row) {
            if ($productTable->where('slug', $row['slug'])->countAllResults() > 0) continue;
            $row += ['nutrition_source'=>'unsourced','nutrition_status'=>'estimated','image'=>null,'is_available'=>1,'created_at'=>$now,'updated_at'=>$now];
            $openingStock = (int) $row['stock'];
            $row['stock'] = 0;
            $this->db->transBegin();
            try {
                $productTable->insert($row);
                $productId = (int) $this->db->insertID();
                (new \App\Services\InventoryService($this->db))->opening($productId, $openingStock);
                // The migration imports old data, but a fresh migrate-before-seed database
                // needs its pivot populated at insert time as well.
                $allergenIds = [];
                foreach (explode(',', (string) ($row['allergens'] ?? '')) as $token) {
                    if (trim($token) === '') continue;
                    $code = \App\Database\Migrations\CreateStructuredAllergens::normalize($token);
                    if ($code === null || !isset($knownAllergens[$code])) {
                        throw new \RuntimeException('Unrecognized seeded allergen: ' . $token);
                    }
                    $allergenIds[] = (int) $knownAllergens[$code];
                }
                $allergenService->sync($productId, $allergenIds);
                if (! $this->db->transStatus()) throw new \RuntimeException('Could not seed product stock.');
                $this->db->transCommit();
            } catch (\Throwable $error) {
                $this->db->transRollback();
                throw $error;
            }
        }

        $allProducts = $this->db->table('products')->get()->getResultArray();
        $products = array_column($allProducts, 'id', 'slug');
        // Existing products with no opening ledger must be reconciled explicitly by an admin.
        // Never silently write a synthetic ledger movement for arbitrary existing stock.

        $addons = [
            ['product_id'=>$products['chicken-adobo-rice'],'name'=>'Extra Rice','price'=>15],
            ['product_id'=>$products['pork-sisig-rice'],'name'=>'Extra Egg','price'=>15],
            ['product_id'=>$products['iced-spanish-latte'],'name'=>'Extra Espresso Shot','price'=>25],
            ['product_id'=>$products['crispy-fries'],'name'=>'Cheese Dip','price'=>15],
        ];
        $addonTable = $this->db->table('product_addons');
        foreach ($addons as $addon) {
            if ($addonTable->where('product_id',$addon['product_id'])->where('name',$addon['name'])->countAllResults()>0) continue;
            $addon += ['is_active'=>1,'created_at'=>$now,'updated_at'=>$now]; $addonTable->insert($addon);
        }
    }
}
