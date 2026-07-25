<?php

namespace App\Controllers\Auth;

use App\Controllers\BaseController;
use App\Models\UserModel;
use App\Services\AccountEmailService;
use App\Services\AuthService;
use Throwable;

class ForgotPasswordController extends BaseController
{
    public function index(): string
    {
        return $this->render('auth/forgot_password', ['title' => 'Forgot password']);
    }

    public function store()
    {
        $email = strtolower(trim((string) $this->request->getPost('email')));
        $key = 'forgot-password-' . hash('sha256', $this->request->getIPAddress() . '|' . $email);
        if (! service('throttler')->check($key, 3, MINUTE)) {
            return redirect()->to('/forgot-password')->withInput()->with('error', 'Too many reset requests. Please try again in one minute.');
        }

        if (! $this->validate(['email' => 'required|valid_email|max_length[160]'])) {
            return redirect()->to('/forgot-password')->withInput()->with('errors', $this->validator->getErrors());
        }

        $user = (new UserModel())->findByEmail($email);
        if ($user && $user['status'] === 'active') {
            try {
                $token = (new AuthService())->issuePasswordResetToken($user);
                (new AccountEmailService())->sendPasswordReset($user, $token);
            } catch (Throwable $exception) {
                log_message('error', 'Password reset email failed for user {userId}: {message}', [
                    'userId' => $user['id'],
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return redirect()->to('/forgot-password')->with('success', 'If an active account matches that email, a password reset link has been sent.');
    }
}
