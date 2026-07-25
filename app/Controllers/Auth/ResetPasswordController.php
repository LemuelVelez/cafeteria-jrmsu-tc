<?php

namespace App\Controllers\Auth;

use App\Controllers\BaseController;
use App\Services\AuthService;

class ResetPasswordController extends BaseController
{
    public function index(): string
    {
        $token = trim((string) $this->request->getGet('token'));
        if ($token === '' || ! (new AuthService())->isPasswordResetTokenValid($token)) {
            return redirect()->to('/forgot-password')->with('error', 'The password reset link is invalid or has expired.');
        }

        return $this->render('auth/reset_password', [
            'title' => 'Reset password',
            'token' => $token,
        ]);
    }

    public function store()
    {
        $token = trim((string) $this->request->getPost('token'));
        $key = 'reset-password-' . hash('sha256', $this->request->getIPAddress() . '|' . $token);
        if (! service('throttler')->check($key, 5, MINUTE)) {
            return redirect()->to('/forgot-password')->with('error', 'Too many password reset attempts. Request a new reset link.');
        }

        if (! $this->validate([
            'token' => 'required|exact_length[64]|alpha_numeric',
            'password' => 'required|min_length[8]',
            'password_confirm' => 'required|matches[password]',
        ])) {
            return redirect()->to('/reset-password?token=' . rawurlencode($token))->withInput()->with('errors', $this->validator->getErrors());
        }

        if (! (new AuthService())->resetPassword($token, (string) $this->request->getPost('password'))) {
            return redirect()->to('/forgot-password')->with('error', 'The password reset link is invalid or has expired.');
        }

        return redirect()->to('/login')->with('success', 'Your password has been reset. You may now sign in.');
    }
}
