<?php

namespace Tests\Feature;

use CodeIgniter\Test\FeatureTestTrait;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\DatabaseTestCase;

/** HTTP-level proof of API 401/403 responses, including non-AJAX requests. */
#[Group('database')]
final class ApiAuthorizationFeatureTest extends DatabaseTestCase
{
    use FeatureTestTrait;

    public function testAnonymousApiRequestReceivesJsonUnauthorizedInsteadOfLoginRedirect(): void
    {
        $response = $this->withSession([])->get('/api/orders/pending-count');
        $response->assertStatus(401);
        self::assertStringContainsString('Authentication required', json_encode($response->getJSON(), JSON_THROW_ON_ERROR));
    }

    public function testCustomerCannotAccessStaffApiEvenWithoutAjaxHeader(): void
    {
        $userId = $this->makeCustomer(['requires_email_verification' => 0]);
        $response = $this->withSession(['user' => [
            'id' => $userId, 'role' => 'customer', 'status' => 'active', 'session_version' => 1,
        ], 'last_activity' => time()])->get('/api/orders/pending-count');
        $response->assertStatus(403);
        self::assertStringContainsString('not allowed', json_encode($response->getJSON(), JSON_THROW_ON_ERROR));
    }

    public function testSessionVersionChangeInvalidatesAuthenticationForApiCalls(): void
    {
        $userId = $this->makeCustomer(['requires_email_verification' => 0]);
        db_connect('tests')->table('users')->where('id', $userId)->update(['session_version' => 2]);
        $response = $this->withSession(['user' => [
            'id' => $userId, 'role' => 'customer', 'status' => 'active', 'session_version' => 1,
        ], 'last_activity' => time()])->get('/api/notifications');
        $response->assertStatus(401);
        self::assertStringContainsString('no longer valid', json_encode($response->getJSON(), JSON_THROW_ON_ERROR));
    }
}
