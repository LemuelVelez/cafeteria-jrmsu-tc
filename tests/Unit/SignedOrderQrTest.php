<?php

namespace Tests\Unit;

use App\Services\SignedOrderQr;
use PHPUnit\Framework\TestCase;

final class SignedOrderQrTest extends TestCase
{
    private const PRIVATE_KEY = 'fixture-private-key-not-a-real-key';
    private const REQUEST_SECRET = 'a-bound-order-request-token';
    private const ISSUED_AT = 1790000000;

    public function testKitchenAndDeliveryQrAreDistinctAndBoundToTheOrder(): void
    {
        $kitchen = SignedOrderQr::issue(42, self::REQUEST_SECRET, self::PRIVATE_KEY,
            SignedOrderQr::KITCHEN, self::ISSUED_AT);
        $delivery = SignedOrderQr::issue(42, self::REQUEST_SECRET, self::PRIVATE_KEY,
            SignedOrderQr::DELIVERY, self::ISSUED_AT);
        self::assertTrue(SignedOrderQr::matches($kitchen, self::REQUEST_SECRET, self::PRIVATE_KEY,
            SignedOrderQr::KITCHEN, self::ISSUED_AT));
        self::assertTrue(SignedOrderQr::matches($delivery, self::REQUEST_SECRET, self::PRIVATE_KEY,
            SignedOrderQr::DELIVERY, self::ISSUED_AT));
        self::assertFalse(SignedOrderQr::matches($kitchen, self::REQUEST_SECRET, self::PRIVATE_KEY,
            SignedOrderQr::DELIVERY, self::ISSUED_AT));
        self::assertFalse(SignedOrderQr::matches($delivery, self::REQUEST_SECRET, self::PRIVATE_KEY,
            SignedOrderQr::KITCHEN, self::ISSUED_AT));
        self::assertFalse(SignedOrderQr::matches($kitchen, 'wrong-request-token', self::PRIVATE_KEY,
            SignedOrderQr::KITCHEN, self::ISSUED_AT));
    }

    public function testQrExpiryAndSignedIssuedTimestampAreEnforced(): void
    {
        $token = SignedOrderQr::issue(42, self::REQUEST_SECRET, self::PRIVATE_KEY,
            SignedOrderQr::KITCHEN, self::ISSUED_AT);
        self::assertTrue(SignedOrderQr::matches($token, self::REQUEST_SECRET, self::PRIVATE_KEY,
            SignedOrderQr::KITCHEN, self::ISSUED_AT + SignedOrderQr::VALID_FOR_SECONDS - 1));
        self::assertFalse(SignedOrderQr::matches($token, self::REQUEST_SECRET, self::PRIVATE_KEY,
            SignedOrderQr::KITCHEN, self::ISSUED_AT + SignedOrderQr::VALID_FOR_SECONDS));
        self::assertNull(SignedOrderQr::claims($token, SignedOrderQr::KITCHEN, self::ISSUED_AT - 61));
        $editedTime = str_replace((string) self::ISSUED_AT, (string) (self::ISSUED_AT + 1), $token);
        self::assertFalse(SignedOrderQr::matches($editedTime, self::REQUEST_SECRET, self::PRIVATE_KEY,
            SignedOrderQr::KITCHEN, self::ISSUED_AT + 1));
    }

    public function testTamperingAndLegacyUnversionedTokensAreRejected(): void
    {
        $token = SignedOrderQr::issue(42, self::REQUEST_SECRET, self::PRIVATE_KEY,
            SignedOrderQr::KITCHEN, self::ISSUED_AT);
        $tampered = substr($token, 0, -1) . ($token[-1] === 'a' ? 'b' : 'a');
        self::assertFalse(SignedOrderQr::matches($tampered, self::REQUEST_SECRET, self::PRIVATE_KEY,
            SignedOrderQr::KITCHEN, self::ISSUED_AT));
        self::assertNull(SignedOrderQr::claims('NDI.' . str_repeat('a', 64), SignedOrderQr::KITCHEN,
            self::ISSUED_AT));
        self::assertNull(SignedOrderQr::claims('o2.MDQ.' . self::ISSUED_AT . '.' . str_repeat('a', 64),
            SignedOrderQr::KITCHEN, self::ISSUED_AT)); // noncanonical base64 of 42
    }
}
