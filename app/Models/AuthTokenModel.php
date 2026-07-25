<?php

namespace App\Models;

use CodeIgniter\Model;
use InvalidArgumentException;
use RuntimeException;

class AuthTokenModel extends Model
{
    public const EMAIL_VERIFICATION = 'email_verification';
    public const PASSWORD_RESET = 'password_reset';

    protected $table = 'user_auth_tokens';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = false;
    protected $protectFields = true;
    protected $allowedFields = ['user_id', 'purpose', 'token_hash', 'expires_at', 'used_at', 'created_at'];

    public function issue(int $userId, string $purpose, int $lifetimeSeconds): string
    {
        if (! in_array($purpose, [self::EMAIL_VERIFICATION, self::PASSWORD_RESET], true) || $lifetimeSeconds < 1) {
            throw new InvalidArgumentException('Invalid authentication token parameters.');
        }

        $this->where('user_id', $userId)
            ->where('purpose', $purpose)
            ->where('used_at', null)
            ->set(['used_at' => date('Y-m-d H:i:s')])
            ->update();

        $plainToken = bin2hex(random_bytes(32));
        $inserted = $this->insert([
            'user_id' => $userId,
            'purpose' => $purpose,
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => date('Y-m-d H:i:s', time() + $lifetimeSeconds),
            'created_at' => date('Y-m-d H:i:s'),
        ], true);

        if (! $inserted) {
            throw new RuntimeException('Unable to create the authentication token.');
        }

        return $plainToken;
    }

    public function findValid(string $plainToken, string $purpose): ?array
    {
        if (! preg_match('/^[a-f0-9]{64}$/', $plainToken)) {
            return null;
        }

        return $this->where('token_hash', hash('sha256', $plainToken))
            ->where('purpose', $purpose)
            ->where('used_at', null)
            ->where('expires_at >=', date('Y-m-d H:i:s'))
            ->first();
    }

    public function markUsed(int $tokenId): bool
    {
        $updated = $this->where('id', $tokenId)
            ->where('used_at', null)
            ->set(['used_at' => date('Y-m-d H:i:s')])
            ->update();

        return (bool) $updated;
    }
}
