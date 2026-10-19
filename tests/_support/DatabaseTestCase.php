<?php

namespace Tests\Support;

use App\Database\Seeds\DatabaseSeeder;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/** CI4 migrates and refreshes only the dedicated <development>_test database. */
abstract class DatabaseTestCase extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = true;
    protected $refresh = true;
    protected $namespace = null;
    protected $seed = DatabaseSeeder::class;

    protected function setUp(): void
    {
        if (! extension_loaded('mysqli') || ! getenv('MySQL_Database_URL')) {
            $this->markTestSkipped('MySQLi and MySQL_Database_URL are required; database acceptance not verified.');
        }
        try {
            $db = db_connect('tests');
            $db->initialize();
            if ($db->database === '') throw new \RuntimeException('Empty test database.');
        } catch (\Throwable $error) {
            $this->markTestSkipped('Cannot connect to MySQL test database: ' . $error->getMessage());
        }
        parent::setUp();
    }

    protected function makeCustomer(array $overrides=[]): int
    {
        return $this->makeUser('customer',$overrides);
    }

    protected function makeRider(array $overrides=[]): int
    {
        return $this->makeUser('rider',$overrides);
    }

    private function makeUser(string $role,array $overrides): int
    {
        $db=db_connect('tests');
        $id=bin2hex(random_bytes(5));
        $row=['name'=>'Fixture '.$role,'email'=>$id.'@example.test',
            'password_hash'=>password_hash('Test#Password42',PASSWORD_DEFAULT),
            'role'=>$role,'status'=>'active', 'created_at'=>date('Y-m-d H:i:s')];
        $db->table('users')->insert(array_replace($row,$overrides));
        return (int)$db->insertID();
    }

    protected function actor(string $role): array
    {
        return ['id'=>$this->makeUser($role,[]),'role'=>$role];
    }

    protected function placeOrder(array $payload, array $actor): mixed
    {
        return (new \App\Services\OrderService())->create($payload,$actor);
    }
}
