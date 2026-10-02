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
