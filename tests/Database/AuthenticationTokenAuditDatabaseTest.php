<?php

namespace Tests\Database;

use App\Models\AuthTokenModel;
use App\Services\AuthService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\DatabaseTestCase;

/** Real-MySQL acceptance: token invalidation and audit share one transaction. */
#[Group('database')]
final class AuthenticationTokenAuditDatabaseTest extends DatabaseTestCase
{
    public function testVerificationResendInvalidatesPreviousLinkAndAuditsMetadataOnly(): void
    {
        $db = db_connect('tests');
        $id = $this->makeCustomer(['requires_email_verification' => 1]);
        $service = new AuthService();
        $first = $service->issueVerificationToken(['id' => $id]);
        $second = $service->issueVerificationToken(['id' => $id]);

        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $second);
        self::assertNotSame($first, $second);
        self::assertNull((new AuthTokenModel())->findValid($first, AuthTokenModel::EMAIL_VERIFICATION));
        self::assertNotNull((new AuthTokenModel())->findValid($second, AuthTokenModel::EMAIL_VERIFICATION));
        $events = $db->table('audit_logs')->where(['entity_type' => 'user', 'entity_id' => $id,
            'action' => 'token_issue'])->orderBy('id', 'ASC')->get()->getResultArray();
        self::assertCount(2, $events);
        foreach ($events as $event) {
            self::assertNull($event['user_id'], 'Guest token issuance must not be attributed to the account owner.');
            $details = json_decode((string) $event['details_json'], true, 512, JSON_THROW_ON_ERROR);
            self::assertSame(['purpose' => AuthTokenModel::EMAIL_VERIFICATION], $details);
            self::assertStringNotContainsString($first, (string) $event['details_json']);
            self::assertStringNotContainsString($second, (string) $event['details_json']);
        }
        self::assertTrue($service->verifyEmail($second));
        self::assertFalse($service->verifyEmail($second));
        self::assertSame(1, $db->table('audit_logs')->where([
            'action' => 'email_verified', 'entity_id' => $id])->countAllResults());
    }

    public function testPasswordResetIssuanceAndConsumptionAreAuditedOnceEach(): void
    {
        $db = db_connect('tests');
        $id = $this->makeCustomer();
        $before = $db->table('users')->where('id', $id)->get()->getRowArray();
        $service = new AuthService();
        $token = $service->issuePasswordResetToken(['id' => $id]);
        self::assertTrue($service->resetPassword($token, 'New#StrongPassword42'));
        self::assertFalse($service->resetPassword($token, 'New#StrongPassword43'));
        $after = $db->table('users')->where('id', $id)->get()->getRowArray();
        self::assertTrue(password_verify('New#StrongPassword42', $after['password_hash']));
        self::assertSame((int) $before['session_version'] + 1, (int) $after['session_version']);
        self::assertSame(1, $db->table('audit_logs')->where([
            'action' => 'token_issue', 'entity_id' => $id])->countAllResults());
        self::assertSame(1, $db->table('audit_logs')->where([
            'action' => 'password_change', 'entity_id' => $id])->countAllResults());
        self::assertNull($db->table('audit_logs')->where([
            'action' => 'password_change', 'entity_id' => $id])->get()->getRow('user_id'));
    }

    public function testInvalidUserCannotIssueOrAuditResetToken(): void
    {
        $db = db_connect('tests');
        $service = new AuthService();
        try {
            $service->issuePasswordResetToken(['id' => 2147483647]);
            self::fail('Issuing a token for a missing user must fail.');
        } catch (\DomainException $expected) {
            self::assertSame('Account not found.', $expected->getMessage());
        }
        self::assertSame(0, $db->table('audit_logs')->where([
            'action' => 'token_issue', 'entity_id' => 2147483647])->countAllResults());
    }
}
