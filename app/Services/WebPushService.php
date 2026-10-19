<?php
namespace App\Services;

use App\Models\SettingModel;
use CodeIgniter\Database\BaseConnection;

/** RFC 8291 encrypted push payload, RFC 8292 VAPID, no new environment variables. */
final class WebPushService
{
    /**
     * Tests can supply an HTTP transport without contacting push providers.
     * The transport receives decrypted subscription data and payload, and
     * returns an HTTP response status. Production always uses the VAPID sender.
     *
     * @param null|\Closure(array<string,mixed>, string):int $transport
     */
    public function __construct(
        private readonly ?BaseConnection $connection = null,
        private readonly ?\Closure $transport = null,
    ) {}
    private function db(): BaseConnection {return $this->connection ?? db_connect();}
    private static function encode(string $raw): string {return rtrim(strtr(base64_encode($raw),'+/','-_'),'=');}
    private static function decode(string $text): string
    {
        $result=base64_decode(strtr($text,'-_','+/'),true);
        if($result===false)throw new \DomainException('Invalid subscription key.');
        return $result;
    }
    public function enabled(): bool
    {
        return (string)($this->db()->table('settings')->where('setting_key','web_push_enabled')->get()->getRow('setting_value')??'0')==='1';
    }
    /** Generate and encrypt a P-256 key once, using the existing encryption.key setting. */
    public function publicKey(): string
    {
        $db=$this->db();
        $row=$db->table('settings')->where('setting_key','webpush_public_key')->get()->getRow('setting_value');
        if($row)return (string)$row;
        $db->transBegin();
        try{
            $db->query('SELECT id FROM settings WHERE setting_key=? FOR UPDATE',['web_push_enabled']);
            $row=$db->table('settings')->where('setting_key','webpush_public_key')->get()->getRow('setting_value');
            if($row){$db->transCommit();return (string)$row;}
            $pair=openssl_pkey_new(['private_key_type'=>OPENSSL_KEYTYPE_EC,'curve_name'=>'prime256v1']);
            if(!$pair)throw new \RuntimeException('OpenSSL EC key generation failed.');
            $secret='';openssl_pkey_export($pair,$secret);
            $details=openssl_pkey_get_details($pair);
            if(!isset($details['ec']['x'],$details['ec']['y']))throw new \RuntimeException('Unexpected OpenSSL P-256 key format.');
            $pub=self::encode("\x04".$details['ec']['x'].$details['ec']['y']);
            $private=base64_encode(service('encrypter')->encrypt($secret));
            $settings=new SettingModel();
            $settings->setValue('webpush_private_encrypted',$private);
            $settings->setValue('webpush_public_key',$pub);
            if(!$db->transStatus())throw new \RuntimeException('Unable to save VAPID keys.');
            $db->transCommit();return $pub;
        }catch(\Throwable $e){$db->transRollback();throw $e;}
    }
    public function subscribe(int $userId,array $subscription): void
    {
        if(!$this->enabled())throw new \DomainException('Web Push is disabled.');
        $endpoint=(string)($subscription['endpoint']??'');
        self::assertTrustedEndpoint($endpoint);
        $keys=$subscription['keys']??[];
        $public=self::decode((string)($keys['p256dh']??''));$auth=self::decode((string)($keys['auth']??''));
        if(strlen($public)!==65||ord($public[0])!==4||strlen($auth)!==16)throw new \DomainException('Invalid push subscription keys.');
        $cipher=base64_encode(service('encrypter')->encrypt(json_encode(['endpoint'=>$endpoint,'keys'=>['p256dh'=>$keys['p256dh'],'auth'=>$keys['auth']]],JSON_THROW_ON_ERROR)));
        $hash=hash('sha256',$endpoint);
        $db=$this->db();$db->transBegin();
        try{
            $previous=$db->query('SELECT id,user_id FROM push_subscriptions WHERE endpoint_hash=? FOR UPDATE',[$hash])->getRowArray();
            if($previous){$db->table('push_subscriptions')->where('id',$previous['id'])->update(['user_id'=>$userId,'subscription_cipher'=>$cipher]);}
            else $db->table('push_subscriptions')->insert(['user_id'=>$userId,'endpoint_hash'=>$hash,'subscription_cipher'=>$cipher,'created_at'=>date('Y-m-d H:i:s')]);
            (new AuditLogService($db))->record('push_subscribed','user',$userId);
            if(!$db->transStatus())throw new \RuntimeException('Failed to register push subscription.');
            $db->transCommit();
        }catch(\Throwable $e){$db->transRollback();throw $e;}
    }
    public function unsubscribe(int $userId,string $endpoint): void
    {
        $db=$this->db();$db->transBegin();
        try{
            $db->table('push_subscriptions')->where(['user_id'=>$userId,'endpoint_hash'=>hash('sha256',$endpoint)])->delete();
            (new AuditLogService($db))->record('push_unsubscribed','user',$userId);
            if (! $db->transStatus()) throw new \RuntimeException('Unable to remove push subscription.');
            $db->transCommit();
        }catch(\Throwable $e){$db->transRollback();throw $e;}
    }
    public function enqueue(int $userId,int $notificationId): void
    {
        if(!$this->enabled())return;
        if($this->db()->table('push_subscriptions')->where('user_id',$userId)->countAllResults()===0)return;
        $this->db()->table('push_outbox')->insert(['notification_id'=>$notificationId,'attempts'=>0,'created_at'=>date('Y-m-d H:i:s')]);
    }
    /** Send queued notifications after order transactions have been committed. */
    public function flush(int $limit=40): array
    {
        if(!$this->enabled())return ['sent'=>0,'failed'=>0];
        $db=$this->db();
        // A database advisory lock prevents overlapping cron invocations from sending twice.
        $held=(int)$db->query("SELECT GET_LOCK('cafeteria.webpush-dispatch',0) held")->getRow('held');
        if($held!==1)return ['sent'=>0,'failed'=>0];
        try {
        $rows=$db->table('push_outbox q')->select('q.id,q.notification_id,q.attempts,n.user_id,n.title,n.message,n.link')->join('notifications n','n.id=q.notification_id')->where('q.delivered_at',null)->where('q.attempts <',5)->orderBy('q.id')->limit(max(1,min(100,$limit)))->get()->getResultArray();
        $sent=0;$failed=0;
        foreach ($rows as $row) {
            $all = true;
            $retired = [];
            $successful = [];
            $subs = $db->table('push_subscriptions')->where('user_id', $row['user_id'])
                ->orderBy('id', 'ASC')->get()->getResultArray();
            $completed = $db->table('push_delivery_receipts')->select('subscription_id')
                ->where('notification_id', $row['notification_id'])->get()->getResultArray();
            $completedIds = array_fill_keys(array_map('intval', array_column($completed, 'subscription_id')), true);
            $payload = json_encode([
                'title' => $row['title'], 'body' => $row['message'],
                'url' => (string) ($row['link'] ?? '/notifications'),
            ], JSON_THROW_ON_ERROR);
            foreach ($subs as $sub) {
                if (isset($completedIds[(int) $sub['id']])) continue;
                try {
                    $subscription = json_decode(
                        service('encrypter')->decrypt(base64_decode($sub['subscription_cipher'], true)),
                        true, 512, JSON_THROW_ON_ERROR,
                    );
                    if (! is_array($subscription)) throw new \DomainException('Invalid push subscription.');
                    // Old/imported records must be checked again at send time;
                    // subscription validation alone cannot prevent SSRF.
                    self::assertTrustedEndpoint((string) ($subscription['endpoint'] ?? ''));
                    $code = $this->transport
                        ? ($this->transport)($subscription, $payload)
                        : $this->post($subscription, $payload);
                    if (in_array($code, [404, 410], true)) {
                        $retired[] = (int) $sub['id'];
                    } elseif ($code >= 200 && $code < 300) {
                        $successful[] = (int) $sub['id'];
                    } else {
                        $all = false;
                    }
                } catch (\Throwable $error) {
                    $all = false;
                    log_message('warning', 'Push delivery failed for notification {id}.',
                        ['id' => (int) $row['notification_id']]);
                }
            }

            // Send outside the transaction, then commit receipts, subscriber
            // cleanup, retry count and audit atomically. The advisory lock
            // prevents competing dispatchers from delivering the same row.
            $db->transBegin();
            try {
                foreach ($retired as $id) {
                    $db->table('push_subscriptions')->where('id', $id)->delete();
                }
                foreach ($successful as $id) {
                    $db->query('INSERT IGNORE INTO push_delivery_receipts (notification_id, subscription_id, delivered_at) VALUES (?,?,?)',
                        [(int) $row['notification_id'], $id, date('Y-m-d H:i:s')]);
                }
                $db->table('push_outbox')->where('id', $row['id'])->update([
                    'attempts' => (int) $row['attempts'] + 1,
                    'delivered_at' => $all ? date('Y-m-d H:i:s') : null,
                ]);
                (new AuditLogService($db))->record('push_dispatch_attempt', 'notification',
                    (int) $row['notification_id'], [
                        'sent_devices' => count($successful), 'retired_devices' => count($retired),
                        'retry_pending' => ! $all,
                    ]);
                if (! $db->transStatus()) throw new \RuntimeException('Push delivery state could not be saved.');
                $db->transCommit();
            } catch (\Throwable $error) {
                $db->transRollback();
                throw $error;
            }
            if ($all) $sent++; else $failed++;
        }
        return compact('sent','failed');
        } finally {
            $db->query("SELECT RELEASE_LOCK('cafeteria.webpush-dispatch')");
        }
    }

    /** Fail closed for both newly subscribed and previously stored endpoints. */
    public static function assertTrustedEndpoint(string $endpoint): void
    {
        $url = parse_url($endpoint);
        if (! is_array($url) || ($url['scheme'] ?? '') !== 'https'
            || ! isset($url['host']) || strlen($endpoint) > 2048
            || isset($url['user'], $url['pass']) || isset($url['user'])
            || isset($url['fragment']) || (isset($url['port']) && $url['port'] !== 443)) {
            throw new \DomainException('Unsupported push provider endpoint.');
        }
        $host = strtolower($url['host']);
        $exact = ['fcm.googleapis.com', 'updates.push.services.mozilla.com',
            'push.services.mozilla.com', 'web.push.apple.com'];
        $suffixes = ['.push.apple.com', '.notify.windows.com', '.push.services.mozilla.com'];
        foreach ($suffixes as $suffix) {
            if (str_ends_with($host, $suffix)) return;
        }
        if (! in_array($host, $exact, true)) {
            throw new \DomainException('Unsupported push provider endpoint.');
        }
    }

    private function post(array $subscription,string $message): int
    {
        $endpoint=(string)$subscription['endpoint'];
        self::assertTrustedEndpoint($endpoint);
        $url=parse_url($endpoint);
        $origin='https://'.$url['host'].(isset($url['port'])?':'.$url['port']:'');
        $private=base64_decode((string)$this->db()->table('settings')->where('setting_key','webpush_private_encrypted')->get()->getRow('setting_value'),true);
        if(!$private)throw new \RuntimeException('VAPID private key is missing.');
        $pem=service('encrypter')->decrypt($private);
        $key=openssl_pkey_get_private($pem);
        if(!$key)throw new \RuntimeException('VAPID private key could not be loaded.');
        $header=self::encode(json_encode(['typ'=>'JWT','alg'=>'ES256'],JSON_THROW_ON_ERROR));
        $claim=self::encode(json_encode(['aud'=>$origin,'exp'=>time()+3600,'sub'=>'mailto:'.(string)env('GMAIL_USER','admin@jrmsu.edu.ph')],JSON_THROW_ON_ERROR));
        $data=$header.'.'.$claim;
        openssl_sign($data,$signatureDer,$key,OPENSSL_ALGO_SHA256);
        $signature=self::derToRaw($signatureDer);
        $token=$data.'.'.self::encode($signature);
        $public=$this->publicKey();
        $body=$this->encrypt($subscription['keys'],$message);
        $request=service('curlrequest');
        $response=$request->post($endpoint,['headers'=>['TTL'=>'120','Content-Type'=>'application/octet-stream','Content-Encoding'=>'aes128gcm','Authorization'=>'vapid t='.$token.', k='.$public],'body'=>$body,'timeout'=>6,'http_errors'=>false]);
        return $response->getStatusCode();
    }

    private static function derToRaw(string $der): string
    {
        if(ord($der[0])!==0x30)throw new \RuntimeException('Invalid ECDSA signature.');
        $pos=2; $out='';
        for($i=0;$i<2;$i++){
            if(ord($der[$pos++])!==2)throw new \RuntimeException('Invalid ECDSA integer.');
            $length=ord($der[$pos++]);$bytes=substr($der,$pos,$length);$pos+=$length;
            $out.=str_pad(ltrim($bytes,"\0"),32,"\0",STR_PAD_LEFT);
        }
        if(strlen($out)!==64)throw new \RuntimeException('Unexpected ECDSA signature length.');
        return $out;
    }

    private function encrypt(array $keys,string $payload): string
    {
        $ua=self::decode((string)$keys['p256dh']);$auth=self::decode((string)$keys['auth']);
        $spki=hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200').$ua;
        $peer=openssl_pkey_get_public("-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($spki),64,"\n")."-----END PUBLIC KEY-----\n");
        if(!$peer)throw new \RuntimeException('Unrecognized push receiver key.');
        $local=openssl_pkey_new(['private_key_type'=>OPENSSL_KEYTYPE_EC,'curve_name'=>'prime256v1']);
        $details=openssl_pkey_get_details($local);
        $server="\x04".$details['ec']['x'].$details['ec']['y'];
        $shared=openssl_pkey_derive($peer,$local,32);
        if($shared===false)throw new \RuntimeException('ECDH key exchange failed.');
        $ikm=hash_hkdf('sha256',$shared,32,"WebPush: info\0".$ua.$server,$auth);
        $salt=random_bytes(16);
        $key=hash_hkdf('sha256',$ikm,16,"Content-Encoding: aes128gcm\0",$salt);
        $nonce=hash_hkdf('sha256',$ikm,12,"Content-Encoding: nonce\0",$salt);
        $encrypted=openssl_encrypt($payload."\x02",'aes-128-gcm',$key,OPENSSL_RAW_DATA,$nonce,$tag);
        if($encrypted===false)throw new \RuntimeException('Web Push encryption failed.');
        return $salt.pack('N',4096).chr(strlen($server)).$server.$encrypted.$tag;
    }
}
