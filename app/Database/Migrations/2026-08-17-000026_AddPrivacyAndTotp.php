<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;
use App\Services\FieldEncryption;
class AddPrivacyAndTotp extends Migration
{
    public function up(): void
    {
        $this->db->query('ALTER TABLE users MODIFY phone TEXT NULL');
        $this->forge->addColumn('users',[
            'privacy_consent_at'=>['type'=>'DATETIME','null'=>true],
            'deletion_requested_at'=>['type'=>'DATETIME','null'=>true],
            'totp_secret'=>['type'=>'TEXT','null'=>true],
            'totp_recovery_hashes'=>['type'=>'TEXT','null'=>true],
        ]);
        foreach ($this->db->table('users')->select('id,phone,address')->get()->getResultArray() as $u) {
            $this->db->table('users')->where('id',$u['id'])->update(['phone'=>FieldEncryption::seal($u['phone']),'address'=>FieldEncryption::seal($u['address'])]);
        }
        foreach ($this->db->table('orders')->select('id,delivery_address')->get()->getResultArray() as $order) {
            if (!empty($order['delivery_address'])) $this->db->table('orders')->where('id',$order['id'])->update(['delivery_address'=>FieldEncryption::seal($order['delivery_address'])]);
        }
    }
    public function down(): void
    {
        foreach ($this->db->table('users')->select('id,phone,address')->get()->getResultArray() as $u) {
            $this->db->table('users')->where('id',$u['id'])->update(['phone'=>FieldEncryption::open($u['phone']),'address'=>FieldEncryption::open($u['address'])]);
        }
        foreach ($this->db->table('orders')->select('id,delivery_address')->get()->getResultArray() as $order) {
            if (!empty($order['delivery_address'])) $this->db->table('orders')->where('id',$order['id'])->update(['delivery_address'=>FieldEncryption::open($order['delivery_address'])]);
        }
        $this->forge->dropColumn('users',['privacy_consent_at','deletion_requested_at','totp_secret','totp_recovery_hashes']);
        $this->db->query('ALTER TABLE users MODIFY phone VARCHAR(30) NULL');
    }
}
