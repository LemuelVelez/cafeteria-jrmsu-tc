<?php

namespace App\Services;

/**
 * Stateless, expiring, purpose-bound order QR proof.
 *
 * The signed timestamp is part of the HMAC, so changing the expiry or moving
 * a QR between the kitchen and customer-delivery stages invalidates it.
 */
final class SignedOrderQr
{
    public const KITCHEN = 'kitchen';
    public const DELIVERY = 'delivery';
    public const VALID_FOR_SECONDS = 48 * 60 * 60;
    private const FUTURE_SKEW_SECONDS = 60;

    public static function issue(int $orderId, string $requestToken, string $key, string $purpose, ?int $issuedAt = null): string
    {
        self::prefix($purpose);
        if ($orderId < 1 || $requestToken === '' || $key === '') {
            throw new \InvalidArgumentException('Order cannot be signed.');
        }
        $issuedAt ??= time();
        if ($issuedAt <= 0 || strlen((string) $issuedAt) !== 10) {
            throw new \InvalidArgumentException('Invalid QR issue time.');
        }
        $id = self::base64Url((string) $orderId);
        $message = self::message($purpose, $orderId, $issuedAt, $requestToken);
        return self::prefix($purpose) . '.' . $id . '.' . $issuedAt . '.'
            . hash_hmac('sha256', $message, $key);
    }

    /**
     * Parse the public portion of a token before loading the order. This does
     * NOT authenticate the order; call matches() with its private request token.
     *
     * @return array{id:int,issued_at:int}|null
     */
    public static function claims(string $token, string $purpose, ?int $now = null): ?array
    {
        $prefix = self::prefix($purpose);
        if (! preg_match('/^' . $prefix . '\.([A-Za-z0-9_-]{1,30})\.([0-9]{10})\.([a-f0-9]{64})$/D', trim($token), $parts)) {
            return null;
        }
        $encodedId = $parts[1];
        $padded = strtr($encodedId, '-_', '+/') . str_repeat('=', (4 - strlen($encodedId) % 4) % 4);
        $decoded = base64_decode($padded, true);
        if ($decoded === false || ! ctype_digit($decoded) || $decoded[0] === '0'
            || strlen($decoded) > strlen((string) PHP_INT_MAX)
            || (int) $decoded < 1 || (string) (int) $decoded !== $decoded
            || self::base64Url($decoded) !== $encodedId) {
            return null;
        }
        $issuedAt = (int) $parts[2];
        $now ??= time();
        if ($issuedAt > $now + self::FUTURE_SKEW_SECONDS
            || $issuedAt <= 0 || $now >= $issuedAt + self::VALID_FOR_SECONDS) {
            return null;
        }
        return ['id' => (int) $decoded, 'issued_at' => $issuedAt];
    }

    /** Verify a complete token after looking up the request_token of the claimed order. */
    public static function matches(string $token, string $requestToken, string $key, string $purpose, ?int $now = null): bool
    {
        $claims = self::claims($token, $purpose, $now);
        if ($claims === null || $requestToken === '' || $key === '') {
            return false;
        }
        $expected = self::issue($claims['id'], $requestToken, $key, $purpose, $claims['issued_at']);
        return hash_equals($expected, trim($token));
    }

    private static function prefix(string $purpose): string
    {
        return match ($purpose) {
            self::KITCHEN => 'o2',
            self::DELIVERY => 'd2',
            default => throw new \InvalidArgumentException('Invalid QR purpose.'),
        };
    }

    private static function message(string $purpose, int $orderId, int $issuedAt, string $requestToken): string
    {
        // Length-delimited and domain-separated, with no concatenation ambiguity.
        return 'cafeteria-order-qr-v2:' . $purpose . ':' . $orderId . ':' . $issuedAt
            . ':' . strlen($requestToken) . ':' . $requestToken;
    }

    private static function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
