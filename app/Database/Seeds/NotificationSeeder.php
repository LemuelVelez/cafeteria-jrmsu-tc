<?php
namespace App\Database\Seeds;
use CodeIgniter\Database\Seeder;
class NotificationSeeder extends Seeder
{
    public function run(): void
    {
        $users=$this->db->table('users')->select('id,role')->get()->getResultArray();
        $table=$this->db->table('notifications'); $now=date('Y-m-d H:i:s');
        foreach($users as $user){
            if($table->where('user_id',$user['id'])->where('type','demo_ready')->countAllResults()>0) continue;
            $table->insert(['user_id'=>$user['id'],'order_id'=>null,'type'=>'demo_ready','title'=>'Notifications ready','message'=>'In-app notifications are configured for this account.','link'=>'notifications','read_at'=>null,'created_at'=>$now]);
        }
    }
}
