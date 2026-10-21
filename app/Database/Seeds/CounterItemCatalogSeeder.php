<?php
namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/** Explicit only: php spark db:seed CounterItemCatalogSeeder. Not registered in DatabaseSeeder. */
class CounterItemCatalogSeeder extends Seeder
{
    public const CATALOG = [
        'Soft Drinks'=>[['Swakto','pcs',18],['Mismo','pcs',25],['Litro (1 L)','pcs',50],['Mountain Dew','pcs',25]],
        'Bottled Water'=>[['330 ml','pcs',10],['500 ml','pcs',15]],
        'Energy Drink'=>[['Sting','pcs',25],['Healthika','pcs',17,true]],
        'Juices'=>[['Bottled Calamansi','pcs',30],['C2','pcs',20]],
        'Other Drinks'=>[['Yakult','pcs',16]],
        'Snack Items'=>[['Doughnut','pcs',10],['Banana Turon','pcs',5,true],['Torta','pcs',15],['Butchi','pcs',null]],
        'Lunch Items'=>[['Bihon','servings',20],['Hotdog','pcs',10,true],['Fried Chicken','pcs',35],['Chicken Menudo','servings',null],['Fried Fish','servings',null],['Tinola Isda','servings',30,true],['Pinakbet','servings',20,true],['Ampalaya with Egg','servings',null],['Ginataang Gabi','servings',null],['Atay with Egg','servings',35],['Breaded Chicken','pcs',35],['Lumpia','pcs',null],['Sunny Side-Up','pcs',15],['Kaldereta','servings',35]],
        'Consignment'=>[["Cath's Sweets",'pcs',null],["Kezhia's Desserts",'pcs',null]],
    ];
    public function run(): void
    {
        $db=$this->db;$db->transBegin();
        try {
            foreach(self::CATALOG as $category=>$items) {
                $slug=url_title($category,'-',true);
                $existing=$db->table('categories')->where('slug',$slug)->get()->getRowArray();
                if(!$existing) {
                    $db->table('categories')->insert(['name'=>$category,'slug'=>$slug,'sort_order'=>array_search($category,array_keys(self::CATALOG),true)+1,'is_active'=>1,'created_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s')]);
                    $categoryId=(int)$db->insertID();
                } else $categoryId=(int)$existing['id'];
                foreach($items as $item) {
                    [$name,$unit,$price]=$item;
                    // Explicit matching by slug; never overwrite existing prices, stock or cost.
                    $itemSlug=url_title($name,'-',true);
                    if($db->table('products')->where('slug',$itemSlug)->countAllResults())continue;
                    $confirm=(bool)($item[3]??false);
                    $data=['category_id'=>$categoryId,'name'=>$name,'slug'=>$itemSlug,'description'=>'',
                        'price'=>$price??0,'stock_unit'=>$unit,'is_available'=>($price!==null&&!$confirm)?1:0,
                        'reorder_level'=>0,'max_stock'=>50,'created_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s')];
                    if($name==='Lumpia') {$data['bundle_quantity']=3;$data['bundle_price']=20;$data['price']=0;$data['is_available']=1;}
                    $db->table('products')->insert($data);
                }
            }
            if(!$db->transStatus())throw new \RuntimeException('Counter catalog insert failed.');
            $db->transCommit();
        }catch(\Throwable $e){$db->transRollback();throw $e;}
    }
}
