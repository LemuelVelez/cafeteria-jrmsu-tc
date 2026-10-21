<?php
namespace Tests\Database;

use App\Database\Seeds\CounterItemCatalogSeeder;
use App\Services\DailyInventoryService;
use App\Services\InventoryService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\DatabaseTestCase;

#[Group('database')]
final class CounterFormsDatabaseTest extends DatabaseTestCase
{
    public function testCatalogIsExplicitAndIdempotentAndConfirmItemsHidden(): void
    {
        $db=db_connect('tests');
        self::assertNotContains('CounterItemCatalogSeeder',\App\Database\Seeds\DatabaseSeeder::SEEDERS);
        $seeder=\Config\Database::seeder('tests');
        $seeder->call(CounterItemCatalogSeeder::class);
        $first=(int)$db->table('products')->whereIn('slug',['healthika','banana-turon','torta','lumpia'])->countAllResults();
        $seeder->call(CounterItemCatalogSeeder::class);
        self::assertSame($first,(int)$db->table('products')->whereIn('slug',['healthika','banana-turon','torta','lumpia'])->countAllResults());
        self::assertSame(4,$first);
        foreach(['healthika','banana-turon','hotdog','tinola-isda','pinakbet'] as $slug) {
            self::assertSame(0,(int)$db->table('products')->where('slug',$slug)->get()->getRow('is_available'));
        }
        $lumpia=$db->table('products')->where('slug','lumpia')->get()->getRowArray();
        self::assertSame(0,(int)$lumpia['stock']);self::assertSame(3,(int)$lumpia['bundle_quantity']);
        self::assertSame('20.00',$lumpia['bundle_price']);
        self::assertNull($lumpia['standard_unit_cost']);
    }
    public function testPhysicalCountNotAdditionalOrWithdrawalAndLedgerVarianceVisible(): void
    {
        $db=db_connect('tests');
        $categoryId=(int)$db->table('categories')->get(1)->getRow('id');
        $slug='daily-ledger-fixture-'.bin2hex(random_bytes(4));
        $db->table('products')->insert(['category_id'=>$categoryId,'name'=>'Daily Ledger Fixture','slug'=>$slug,'price'=>10,'standard_unit_cost'=>6,'stock_unit'=>'pcs','stock'=>0,'is_available'=>1,'reorder_level'=>0,'max_stock'=>200]);
        $id=(int)$db->insertID();
        $stock=new InventoryService($db);
        $stock->opening($id,150);
        // An opening movement prior to the tested business day is the 00:00 balance.
        $today=date('Y-m-d');
        $db->table('inventory_movements')->where('product_id',$id)->where('movement_type','opening')->update(['created_at'=>date('Y-m-d 12:00:00',strtotime('-1 day'))]);
        $stock->stockIn($id,20,null,'FIXTURE','New delivery');
        $stock->stockOut($id,5,null,'expired','past date');
        $db->table('orders')->insert(['order_number'=>'COUNTER-'.bin2hex(random_bytes(5)), 'order_type'=>'pickup',
            'status'=>'completed','payment_method'=>'cash_on_pickup','payment_status'=>'paid','subtotal'=>1250,'total'=>1250,
            'created_at'=>date('Y-m-d H:i:s')]);
        $orderId=(int)$db->insertID();
        $stock->deductForOrder($id,125,$orderId,null);
        $stock->count($id,35,null,'Closing count');
        $report=(new DailyInventoryService($db))->report($today);
        $matches=[];
        foreach($report['groups'] as $group)foreach($group['rows'] as $row)if($row['product_id']===$id)$matches[]=$row;
        self::assertCount(1,$matches);$row=$matches[0];
        self::assertSame(150,$row['beginning']);self::assertSame(20,$row['additional']);
        self::assertSame(5,$row['withdrawn']);self::assertSame(35,$row['ending']);
        self::assertSame('counted',$row['ending_source']);self::assertSame(130,$row['sold']);
        self::assertSame(5,$row['variance']);self::assertSame(1300.0,$row['amount']);
        self::assertSame(780.0,$row['total_cost']);self::assertSame(520.0,$row['profit']);
        self::assertStringContainsString('expired: past date',$row['remarks']);
    }
}
