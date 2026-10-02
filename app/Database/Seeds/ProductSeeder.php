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
            ['category_id'=>$categories['rice-meals'],'name'=>'Chicken Adobo Rice','slug'=>'chicken-adobo-rice','description'=>'Tender chicken adobo with steamed rice and vegetables.','price'=>95,'stock'=>40,'sku'=>'RICE-ADOBO','barcode'=>'480000000101','reorder_level'=>8,'serving_size'=>'1 plate (350 g)','calories'=>480,'protein_g'=>27,'carbohydrates_g'=>58,'fat_g'=>15,'sugar_g'=>6,'fiber_g'=>4,'sodium_mg'=>760,'allergens'=>'soy','is_healthy_choice'=>1,'is_featured'=>1],
            ['category_id'=>$categories['rice-meals'],'name'=>'Pork Sisig Rice','slug'=>'pork-sisig-rice','description'=>'Sizzling-style pork sisig served with rice.','price'=>105,'stock'=>35,'sku'=>'RICE-SISIG','barcode'=>'480000000102','reorder_level'=>7,'serving_size'=>'1 plate (330 g)','calories'=>610,'protein_g'=>25,'carbohydrates_g'=>56,'fat_g'=>31,'sugar_g'=>4,'fiber_g'=>3,'sodium_mg'=>890,'allergens'=>'soy, egg','is_healthy_choice'=>0,'is_featured'=>1],
            ['category_id'=>$categories['rice-meals'],'name'=>'Fried Chicken Meal','slug'=>'fried-chicken-meal','description'=>'Crispy fried chicken, gravy, and steamed rice.','price'=>110,'stock'=>30,'sku'=>'RICE-FCHICK','barcode'=>'480000000103','reorder_level'=>7,'serving_size'=>'1 plate (360 g)','calories'=>650,'protein_g'=>32,'carbohydrates_g'=>64,'fat_g'=>29,'sugar_g'=>3,'fiber_g'=>3,'sodium_mg'=>980,'allergens'=>'wheat, milk, egg','is_healthy_choice'=>0,'is_featured'=>1],
            ['category_id'=>$categories['snacks'],'name'=>'Cheese Burger','slug'=>'cheese-burger','description'=>'Beef patty, cheese, lettuce, and house dressing.','price'=>75,'stock'=>25,'sku'=>'SNACK-BURGER','barcode'=>'480000000104','reorder_level'=>5,'serving_size'=>'1 burger (190 g)','calories'=>430,'protein_g'=>21,'carbohydrates_g'=>38,'fat_g'=>22,'sugar_g'=>7,'fiber_g'=>2,'sodium_mg'=>720,'allergens'=>'wheat, milk, egg','is_healthy_choice'=>0,'is_featured'=>0],
            ['category_id'=>$categories['snacks'],'name'=>'Crispy Fries','slug'=>'crispy-fries','description'=>'Golden fries with ketchup or cheese dip.','price'=>55,'stock'=>50,'sku'=>'SNACK-FRIES','barcode'=>'480000000105','reorder_level'=>10,'serving_size'=>'120 g','calories'=>365,'protein_g'=>5,'carbohydrates_g'=>52,'fat_g'=>15,'sugar_g'=>1,'fiber_g'=>5,'sodium_mg'=>310,'allergens'=>null,'is_healthy_choice'=>0,'is_featured'=>0],
            ['category_id'=>$categories['coffee'],'name'=>'Iced Spanish Latte','slug'=>'iced-spanish-latte','description'=>'Espresso, creamy milk, and lightly sweetened condensed milk.','price'=>85,'stock'=>45,'sku'=>'COF-SPANISH','barcode'=>'480000000106','reorder_level'=>8,'serving_size'=>'16 fl oz','calories'=>210,'protein_g'=>7,'carbohydrates_g'=>32,'fat_g'=>6,'sugar_g'=>28,'fiber_g'=>0,'sodium_mg'=>120,'allergens'=>'milk','is_healthy_choice'=>0,'is_featured'=>1],
            ['category_id'=>$categories['coffee'],'name'=>'Hot Americano','slug'=>'hot-americano','description'=>'Bold espresso with hot water.','price'=>60,'stock'=>45,'sku'=>'COF-AMER','barcode'=>'480000000107','reorder_level'=>8,'serving_size'=>'12 fl oz','calories'=>10,'protein_g'=>1,'carbohydrates_g'=>2,'fat_g'=>0,'sugar_g'=>0,'fiber_g'=>0,'sodium_mg'=>10,'allergens'=>null,'is_healthy_choice'=>1,'is_featured'=>0],
            ['category_id'=>$categories['cold-drinks'],'name'=>'Calamansi Juice','slug'=>'calamansi-juice','description'=>'Fresh local calamansi juice served chilled.','price'=>45,'stock'=>60,'sku'=>'DRINK-CALAM','barcode'=>'480000000108','reorder_level'=>10,'serving_size'=>'12 fl oz','calories'=>95,'protein_g'=>1,'carbohydrates_g'=>24,'fat_g'=>0,'sugar_g'=>20,'fiber_g'=>1,'sodium_mg'=>8,'allergens'=>null,'is_healthy_choice'=>1,'is_featured'=>0],
        ];
        $productTable = $this->db->table('products');
        foreach ($rows as $row) {
            if ($productTable->where('slug', $row['slug'])->countAllResults() > 0) continue;
            $row += ['image'=>null,'is_available'=>1,'created_at'=>$now,'updated_at'=>$now];
            $productTable->insert($row);
        }

        $allProducts = $this->db->table('products')->get()->getResultArray();
        $products = array_column($allProducts, 'id', 'slug');
        $movementTable = $this->db->table('inventory_movements');
        foreach ($allProducts as $product) {
            if ($movementTable->where('product_id', $product['id'])->countAllResults() > 0) continue;
            $stock = (int)$product['stock'];
            $movementTable->insert(['product_id'=>$product['id'],'movement_type'=>'opening','quantity'=>$stock,'stock_before'=>0,'stock_after'=>$stock,'order_id'=>null,'user_id'=>null,'reference'=>'seed','note'=>'Opening balance from product seeder','created_at'=>$now]);
        }

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
