<?php
namespace App\Services;
/** RFC 6238 SHA-1 TOTP at 30-second intervals. */
final class TotpService
{
    public static function generateSecret(): string
    {
        $alphabet='ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';$bytes=random_bytes(20);$bits='';
        foreach(str_split($bytes) as $byte)$bits.=str_pad(decbin(ord($byte)),8,'0',STR_PAD_LEFT);
        $secret='';foreach(str_split($bits,5) as $piece){if(strlen($piece)===5)$secret.=$alphabet[bindec($piece)];}
        return $secret;
    }
    public static function code(string $secret,int $counter): string
    {
        $alphabet='ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';$bits='';
        foreach(str_split(strtoupper($secret)) as $char){$index=strpos($alphabet,$char);if($index===false)throw new \DomainException('Invalid TOTP secret.');$bits.=str_pad(decbin($index),5,'0',STR_PAD_LEFT);}
        $binary='';foreach(str_split($bits,8) as $block){if(strlen($block)===8)$binary.=chr(bindec($block));}
        $message=pack('N2',intdiv($counter,4294967296),$counter%4294967296);
        $digest=hash_hmac('sha1',$message,$binary,true);$offset=ord($digest[19])&0x0f;
        $num=(unpack('N',substr($digest,$offset,4))[1]&0x7fffffff)%1000000;
        return str_pad((string)$num,6,'0',STR_PAD_LEFT);
    }
    public static function verify(string $secret,string $input,?int $now=null): bool
    {
        if (!preg_match('/^[0-9]{6}$/',$input))return false;
        $counter=intdiv($now??time(),30);
        foreach([-1,0,1] as $step)if(hash_equals(self::code($secret,$counter+$step),$input))return true;
        return false;
    }
    public static function recoveryCodes(): array
    {
        $out=[];for($i=0;$i<8;$i++)$out[]=strtoupper(bin2hex(random_bytes(5)));
        return $out;
    }
    public static function consumeRecovery(int $userId,string $input): bool
    {
        $db=db_connect();$db->transBegin();
        try {
            $row=$db->query('SELECT totp_recovery_hashes FROM users WHERE id=? FOR UPDATE',[$userId])->getRowArray();
            if (!$row){$db->transRollback();return false;}
            $hashes=json_decode($row['totp_recovery_hashes']??'[]',true)?:[];
            foreach($hashes as $i=>$hash){
                if(password_verify(strtoupper(trim($input)),$hash)){
                    unset($hashes[$i]);
                    $db->table('users')->where('id',$userId)->update(['totp_recovery_hashes'=>json_encode(array_values($hashes))]);
                    $db->transCommit();return true;
                }
            }
            $db->transRollback();return false;
        }catch(\Throwable $e){$db->transRollback();throw $e;}
    }
}
