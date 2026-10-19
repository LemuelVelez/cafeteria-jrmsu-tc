<?php

namespace Tests\Unit;

use App\Services\WebPushService;
use PHPUnit\Framework\TestCase;

final class WebPushEndpointPolicyTest extends TestCase
{
    public function testOnlyRecognizedHttpsPushEndpointsAreAllowed(): void
    {
        foreach ([
            'https://fcm.googleapis.com/fcm/send/token',
            'https://updates.push.services.mozilla.com/wpush/v2/token',
            'https://web.push.apple.com/Q/abc',
            'https://region1.notify.windows.com/push/xyz',
        ] as $url) {
            WebPushService::assertTrustedEndpoint($url);
            self::assertTrue(true, $url);
        }
    }

    public function testEndpointPolicyRejectsPrivateServersSpoofedHostsAndNonDefaultPorts(): void
    {
        foreach ([
            'https://127.0.0.1/admin',
            'https://localhost/push',
            'https://fcm.googleapis.com.example.net/push',
            'https://fcm.googleapis.com@127.0.0.1/push',
            'https://user:pass@fcm.googleapis.com/push',
            'http://fcm.googleapis.com/push',
            'https://fcm.googleapis.com:8443/push',
            'https://fcm.googleapis.com/push#fragment',
            'https://evil.push.apple.com.example.org/push',
            '',
        ] as $url) {
            try {
                WebPushService::assertTrustedEndpoint($url);
                self::fail('Unexpectedly accepted endpoint: ' . $url);
            } catch (\DomainException) {
                self::assertTrue(true, 'Blocked endpoint: ' . $url);
            }
        }
    }
}
