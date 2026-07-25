<?php

namespace App\Controllers\Auth;

use App\Controllers\BaseController;
use App\Models\UserModel;
use App\Services\AccountEmailService;
use App\Services\AuthService;
use Throwable;

class EmailVerificationController extends BaseController
{
    public function index(): string
    {
        return $this->render('auth/email_verification', [
            'title' => 'Verify email',
            'email' => (string) (session()->getFlashdata('verification_email') ?? old('email', '')),
        ]);
    }

    public function verify()
    {
        $token = trim((string) $this->request->getGet('token'));
        if ($token === '' || ! (new AuthService())->verifyEmail($token)) {
            return redirect()->to('/email-verification')->with('error', 'The verification link is invalid or has expired. Request a new link below.');
        }

        return redirect()->to('/login')->with('success', 'Your email address has been verified. You may now sign in.');
    }

    public function resend()
    {
        $email = strtolower(trim((string) $this->request->getPost('email')));
        $key = 'verification-resend-' . hash('sha256', $this->request->getIPAddress() . '|' . $email);
        if (! service('throttler')->check($key, 3, MINUTE)) {
            return redirect()->to('/email-verification')->withInput()->with('error', 'Too many verification requests. Please try again in one minute.');
        }

        if (! $this->validate(['email' => 'required|valid_email|max_length[160]'])) {
            return redirect()->to('/email-verification')->withInput()->with('errors', $this->validator->getErrors());
        }

        $user = (new UserModel())->findByEmail($email);
        if (
            $user
            && $user['role'] === 'customer'
            && $user['status'] === 'active'
            && (bool) ($user['requires_email_verification'] ?? false)
            && empty($user['email_verified_at'])
        ) {
            try {
                $token = (new AuthService())->issueVerificationToken($user);
                (new AccountEmailService())->sendVerification($user, $token);
            } catch (Throwable $exception) {
                log_message('error', 'Verification email resend failed for user {userId}: {message}', [
                    'userId' => $user['id'],
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return redirect()->to('/email-verification')->with('success', 'If that account requires verification, a new link has been sent.');
    }
}
