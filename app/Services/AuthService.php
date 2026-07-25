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
        $user = $this->users->findByEmail($email);

        if (! $user || ! password_verify($password, $user['password_hash'])) {
            $this->failureReason = 'invalid_credentials';
            return false;
        }

        if ($user['status'] !== 'active') {
            $this->failureReason = 'inactive_account';
            return false;
        }

        if (
            $user['role'] === 'customer'
            && (bool) ($user['requires_email_verification'] ?? false)
            && empty($user['email_verified_at'])
        ) {
            $this->failureReason = 'email_verification_required';
            return false;
        }

        session()->regenerate(true);
        session()->set('user', [
            'id' => (int) $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
            'status' => $user['status'],
            'avatar' => $user['avatar'] ?? null,
        ]);
        $this->users->update($user['id'], ['last_login_at' => date('Y-m-d H:i:s')]);
        return true;
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
            log_message('error', 'Customer registration transaction failed: {message}', [
                'message' => $exception->getMessage(),
            ]);
            return false;
        }
    }

    public function issueVerificationToken(array $user): string
    {
        return $this->tokens->issue((int) $user['id'], AuthTokenModel::EMAIL_VERIFICATION, DAY);
    }

    public function verifyEmail(string $plainToken): bool
    {
        $token = $this->tokens->findValid($plainToken, AuthTokenModel::EMAIL_VERIFICATION);
        if (! $token) {
            return false;
        }

        $database = db_connect();
        $database->transBegin();

        try {
            $updated = $this->users->update((int) $token['user_id'], [
                'requires_email_verification' => 0,
                'email_verified_at' => date('Y-m-d H:i:s'),
            ]);
            $used = $this->tokens->markUsed((int) $token['id']);

            if (! $updated || ! $used || ! $database->transStatus()) {
                throw new \RuntimeException('Unable to verify the email address.');
            }

            $database->transCommit();
            return true;
        } catch (Throwable $exception) {
            $database->transRollback();
            log_message('error', 'Email verification transaction failed: {message}', [
                'message' => $exception->getMessage(),
            ]);
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
        $token = $this->tokens->findValid($plainToken, AuthTokenModel::PASSWORD_RESET);
        if (! $token) {
            return false;
        }

        $database = db_connect();
        $database->transBegin();

        try {
            $updated = $this->users->update((int) $token['user_id'], [
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ]);
            $used = $this->tokens->markUsed((int) $token['id']);

            if (! $updated || ! $used || ! $database->transStatus()) {
                throw new \RuntimeException('Unable to reset the password.');
            }

            $database->transCommit();
            return true;
        } catch (Throwable $exception) {
            $database->transRollback();
            log_message('error', 'Password reset transaction failed: {message}', [
                'message' => $exception->getMessage(),
            ]);
            return false;
        }
    }

    public function logout(): void
    {
        session()->destroy();
    }
}
