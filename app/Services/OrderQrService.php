<?php

namespace App\Services;

class OrderQrService
{
    public function token(array $order): string
    {
        return SignedOrderQr::issue((int) ($order['id'] ?? 0),
            (string) ($order['request_token'] ?? ''), $this->key(), SignedOrderQr::KITCHEN);
    }

    /** Customer-only delivery QR, never accepted as a kitchen/dispatch ticket. */
    public function deliveryToken(array $order): string
    {
        return SignedOrderQr::issue((int) ($order['id'] ?? 0),
            (string) ($order['request_token'] ?? ''), $this->key(), SignedOrderQr::DELIVERY);
    }

    /** A customer delivery QR is valid only during the out-for-delivery stage. */
    public function verifyDeliveryToken(string $token): ?array
    {
        $claims = SignedOrderQr::claims($token, SignedOrderQr::DELIVERY);
        if ($claims === null) {
            return null;
        }
        $order = db_connect()->table('orders')->where([
            'id' => $claims['id'], 'order_type' => 'delivery', 'status' => 'out_for_delivery',
        ])->get()->getRowArray();
        return $order && SignedOrderQr::matches($token, (string) ($order['request_token'] ?? ''),
            $this->key(), SignedOrderQr::DELIVERY) ? $order : null;
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

    /** Old unversioned indefinite-life QR codes are deliberately not accepted. */
    public function verify(string $token): ?array
    {
        $claims = SignedOrderQr::claims($token, SignedOrderQr::KITCHEN);
        if ($claims === null) {
            return null;
        }
        $order = db_connect()->table('orders')->where('id', $claims['id'])->get()->getRowArray();
        if (! $order || in_array($order['status'] ?? '', ['cancelled'], true)) {
            return null;
        }
        return SignedOrderQr::matches($token, (string) ($order['request_token'] ?? ''),
            $this->key(), SignedOrderQr::KITCHEN) ? $order : null;
    }

    private function key(): string
    {
        // Fail closed; never sign with a hardcoded, publicly predictable key.
        // Use the same key as the field-encryption service, including a persisted
        // auto-generated key when encryption.key is not set in .env.
        $key = (string) config(\Config\Encryption::class)->key;
        if ($key === '') {
            throw new \RuntimeException('Configure encryption.key before using order verification.');
        }
        return $key;
    }
}
