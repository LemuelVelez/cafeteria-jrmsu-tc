<?php

namespace App\Services;

class OrderQrService
{
    public function token(array $order): string
    {
        $id = (int) ($order['id'] ?? 0);
        $requestToken = (string) ($order['request_token'] ?? '');
        if ($id < 1 || $requestToken === '') {
            throw new \InvalidArgumentException('Order cannot be signed.');
        }
        $payload = $id . ':' . $requestToken;
        $signature = hash_hmac('sha256', $payload, $this->key());
        return rtrim(strtr(base64_encode((string) $id), '+/', '-_'), '=') . '.' . $signature;
    }

    public function pickupCode(array $order): string
    {
        // Stable four-digit code; server verifies against the bound order and rate-limits attempts.
        $hash = hash_hmac('sha256', (string)($order['id']??'') . ':' . (string)($order['request_token']??''), $this->key());
        return str_pad((string)(hexdec(substr($hash,0,8)) % 10000),4,'0',STR_PAD_LEFT);
    }

    /** A stable, order-bound code that is stored only as a hash while in transit. */
    public function deliveryCode(array $order): string
    {
        $id=(int)($order['id']??0);
        $request=(string)($order['request_token']??'');
        if ($id<1 || $request==='') throw new \DomainException('Order cannot have a delivery code.');
        $hash=hash_hmac('sha256','delivery:'.$id.':'.$request,$this->key());
        return str_pad((string)(hexdec(substr($hash,0,8))%10000),4,'0',STR_PAD_LEFT);
    }

    public function verify(string $token): ?array
    {
        [$encodedId, $signature] = array_pad(explode('.', trim($token), 2), 2, null);
        if (! is_string($encodedId) || ! is_string($signature) || ! preg_match('/^[a-f0-9]{64}$/', $signature)) {
            return null;
        }
        $decoded = base64_decode(strtr($encodedId, '-_', '+/'), true);
        if ($decoded === false || ! ctype_digit($decoded)) {
            return null;
        }
        $order = db_connect()->table('orders')->where('id', (int) $decoded)->get()->getRowArray();
        if (! $order || empty($order['request_token'])) {
            return null;
        }
        if (($order['status'] ?? '') === 'cancelled') {
            return null;
        }
        if (in_array($order['status'] ?? '', ['completed', 'delivered'], true)) {
            $at = db_connect()->table('order_status_history')->select('created_at')->where('order_id', (int) $order['id'])->where('to_status', $order['status'])->orderBy('id', 'DESC')->get()->getRow('created_at');
            if ($at && strtotime((string) $at) < time() - 48 * HOUR) {
                return null;
            }
        }
        $expected = hash_hmac('sha256', (int) $order['id'] . ':' . $order['request_token'], $this->key());
        return hash_equals($expected, $signature) ? $order : null;
    }

    private function key(): string
    {
        $key = (string) env('encryption.key', '');
        if ($key === '') {
            $key = (string) env('APP_ENCRYPTION_KEY', 'jrmsu-cafeteria-local-development-key');
        }
        return $key;
    }
}
