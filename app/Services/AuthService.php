<?php

namespace App\Services;

use App\Models\AuthTokenModel;
use App\Models\UserModel;
use Throwable;

class AuthService
{
    private ?string $failureReason = null;

    public function __construct(
        private readonly UserModel $users = new UserModel(),
        private readonly AuthTokenModel $tokens = new AuthTokenModel(),
    ) {
    }

    public function attempt(string $email, string $password): bool
    {
        $this->failureReason = null;
        $database = db_connect();
        $normalizedEmail = strtolower(trim($email));
        $database->transBegin();
        try {
            $user = $database->query('SELECT * FROM users WHERE email = ? FOR UPDATE', [$normalizedEmail])->getRowArray();
            if (! $user) {
                (new AuditLogService($database))->record('login_failure', 'user', null, ['reason' => 'invalid_credentials'], null);
                $database->transCommit();
                $this->failureReason = 'invalid_credentials';
                return false;
            }

            $now = time();
            $lockedUntil = ! empty($user['locked_until']) ? strtotime((string) $user['locked_until']) : false;
            if ($lockedUntil !== false && $lockedUntil > $now) {
                (new AuditLogService($database))->record('login_lockout', 'user', (int) $user['id'], ['locked_until' => $user['locked_until']], (int) $user['id']);
                $database->transCommit();
                $this->failureReason = 'account_locked';
                return false;
            }

            if (! password_verify($password, (string) $user['password_hash'])) {
                $attempts = (int) ($user['failed_login_attempts'] ?? 0) + 1;
                $lockUntil = $attempts >= 5 ? date('Y-m-d H:i:s', $now + 15 * MINUTE) : null;
                $database->table('users')->where('id', (int) $user['id'])->update([
                    'failed_login_attempts' => min($attempts, 255),
                    'locked_until' => $lockUntil,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                (new AuditLogService($database))->record($lockUntil ? 'login_lockout' : 'login_failure', 'user', (int) $user['id'], ['attempts' => $attempts], (int) $user['id']);
                if (! $database->transStatus()) {
                    throw new \RuntimeException('Unable to update login security state.');
                }
                $database->transCommit();
                $this->failureReason = $lockUntil ? 'account_locked' : 'invalid_credentials';
                return false;
            }

            if ($user['status'] !== 'active') {
                (new AuditLogService($database))->record('login_failure', 'user', (int) $user['id'], ['reason' => 'inactive_account'], (int) $user['id']);
                $database->transCommit();
                $this->failureReason = 'inactive_account';
                return false;
            }

            if ($user['role'] === 'customer' && (bool) ($user['requires_email_verification'] ?? false) && empty($user['email_verified_at'])) {
                $database->transCommit();
                $this->failureReason = 'email_verification_required';
                return false;
            }

            $database->table('users')->where('id', (int) $user['id'])->update([
                'failed_login_attempts' => 0,
                'locked_until' => null,
                'last_login_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            (new AuditLogService($database))->record('login_success', 'user', (int) $user['id'], [], (int) $user['id']);
            if (! $database->transStatus()) {
                throw new \RuntimeException('Unable to complete sign in.');
            }
            $database->transCommit();

            session()->regenerate(true);
            session()->set('user', [
                'id' => (int) $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $user['role'],
                'status' => $user['status'],
                'avatar' => $user['avatar'] ?? null,
                'session_version' => (int) ($user['session_version'] ?? 1),
            ]);
            session()->set('last_activity', time());
            return true;
        } catch (Throwable $exception) {
            $database->transRollback();
            throw $exception;
        }
    }

    public function failureReason(): ?string
    {
        return $this->failureReason;
    }

    public function registerCustomer(array $data): array|false
    {
        $database = db_connect();
        $database->transBegin();
        try {
            $userId = $this->users->insert([
                'name' => trim($data['name']),
                'email' => strtolower(trim($data['email'])),
                'phone' => trim($data['phone'] ?? ''),
                'address' => trim($data['address'] ?? ''),
                'avatar' => $data['avatar'] ?? null,
                'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
                'role' => 'customer',
                'status' => 'active',
                'requires_email_verification' => 1,
                'email_verified_at' => null,
                'session_version' => 1,
            ], true);
            if (! $userId) {
                throw new \RuntimeException('Unable to create the customer account.');
            }
            $token = $this->tokens->issue((int) $userId, AuthTokenModel::EMAIL_VERIFICATION, DAY);
            $user = $this->users->find((int) $userId);
            if (! $user || ! $database->transStatus()) {
                throw new \RuntimeException('Unable to complete customer registration.');
            }
            $database->transCommit();
            return ['user' => $user, 'token' => $token];
        } catch (Throwable $exception) {
            $database->transRollback();
            log_message('error', 'Customer registration transaction failed: {message}', ['message' => $exception->getMessage()]);
            return false;
        }
    }

    public function issueVerificationToken(array $user): string
    {
        return $this->tokens->issue((int) $user['id'], AuthTokenModel::EMAIL_VERIFICATION, DAY);
    }

    public function verifyEmail(string $plainToken): bool
    {
        $database = db_connect();
        $database->transBegin();
        try {
            $token = $this->tokens->findValidForUpdate($plainToken, AuthTokenModel::EMAIL_VERIFICATION);
            if (! $token) {
                $database->transRollback();
                return false;
            }
            $updated = $this->users->update((int) $token['user_id'], ['requires_email_verification' => 0, 'email_verified_at' => date('Y-m-d H:i:s')]);
            $used = $this->tokens->markUsed((int) $token['id']);
            if (! $updated || ! $used || ! $database->transStatus()) {
                throw new \RuntimeException('Unable to verify the email address.');
            }
            $database->transCommit();
            return true;
        } catch (Throwable $exception) {
            $database->transRollback();
            log_message('error', 'Email verification transaction failed: {message}', ['message' => $exception->getMessage()]);
            return false;
        }
    }

    public function issuePasswordResetToken(array $user): string
    {
        return $this->tokens->issue((int) $user['id'], AuthTokenModel::PASSWORD_RESET, HOUR);
    }

    public function isPasswordResetTokenValid(string $plainToken): bool
    {
        return $this->tokens->findValid($plainToken, AuthTokenModel::PASSWORD_RESET) !== null;
    }

    public function resetPassword(string $plainToken, string $password): bool
    {
        $database = db_connect();
        $database->transBegin();
        try {
            $token = $this->tokens->findValidForUpdate($plainToken, AuthTokenModel::PASSWORD_RESET);
            if (! $token) {
                $database->transRollback();
                return false;
            }
            $userId = (int) $token['user_id'];
            $user = $database->query('SELECT id, session_version FROM users WHERE id=? FOR UPDATE', [$userId])->getRowArray();
            if (! $user) {
                $database->transRollback();
                return false;
            }
            $updated = $this->users->update($userId, [
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'failed_login_attempts' => 0,
                'locked_until' => null,
                'session_version' => (int) ($user['session_version'] ?? 1) + 1,
            ]);
            $used = $this->tokens->markUsed((int) $token['id']);
            (new AuditLogService($database))->record('password_change', 'user', $userId, ['source' => 'password_reset'], $userId);
            if (! $updated || ! $used || ! $database->transStatus()) {
                throw new \RuntimeException('Unable to reset the password.');
            }
            $database->transCommit();
            return true;
        } catch (Throwable $exception) {
            $database->transRollback();
            log_message('error', 'Password reset transaction failed: {message}', ['message' => $exception->getMessage()]);
            return false;
        }
    }

    public function logout(): void
    {
        session()->destroy();
    }
}
