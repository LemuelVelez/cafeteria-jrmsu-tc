<?php

namespace App\Controllers\Auth;

use App\Controllers\BaseController;
use App\Models\AuthTokenModel;
use App\Models\UserModel;
use App\Services\AuthService;

class ResetPasswordController extends BaseController
{
    public function index(): string
    {
        $token = trim((string) $this->request->getGet('token'));
        if ($token === '' || ! (new AuthService())->isPasswordResetTokenValid($token)) {
            return redirect()->to('/forgot-password')->with('error', 'The password reset link is invalid or has expired.');
        }
        return $this->render('auth/reset_password', ['title' => 'Reset password', 'token' => $token]);
    }

    public function store()
    {
        $token = trim((string) $this->request->getPost('token'));
        $key = 'reset-password-' . hash('sha256', $this->request->getIPAddress() . '|' . $token);
        if (! service('throttler')->check($key, 5, MINUTE)) {
            return redirect()->to('/forgot-password')->with('error', 'Too many password reset attempts. Request a new reset link.');
        }

        $tokenRow = (new AuthTokenModel())->findValid($token, AuthTokenModel::PASSWORD_RESET);
        $user = $tokenRow ? (new UserModel())->find((int) $tokenRow['user_id']) : null;
        if (! $user) {
            return redirect()->to('/forgot-password')->with('error', 'The password reset link is invalid or has expired.');
        }

        $validation = service('validation');
        $validation->setRules([
            'token' => 'required|exact_length[64]|alpha_numeric',
            'password' => 'required|strong_password',
            'password_confirm' => 'required|matches[password]',
        ]);
        $data = [
            'token' => $token,
            'password' => (string) $this->request->getPost('password'),
            'password_confirm' => (string) $this->request->getPost('password_confirm'),
            'name' => $user['name'],
            'email' => $user['email'],
        ];
        if (! $validation->run($data)) {
            return redirect()->to('/reset-password?token=' . rawurlencode($token))->withInput()->with('errors', $validation->getErrors());
        }

        if (! (new AuthService())->resetPassword($token, $data['password'])) {
            return redirect()->to('/forgot-password')->with('error', 'The password reset link is invalid or has expired.');
        }
        return redirect()->to('/login')->with('success', 'Your password has been reset. You may now sign in.');
    }
}
