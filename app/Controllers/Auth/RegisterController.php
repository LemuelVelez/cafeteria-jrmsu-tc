<?php

namespace App\Controllers\Auth;

use App\Controllers\BaseController;
use App\Models\UserModel;
use App\Services\AccountEmailService;
use App\Services\AuthService;
use Throwable;

class RegisterController extends BaseController
{
    public function index(): string
    {
        return $this->render('auth/register');
    }

    public function store()
    {
        $key = 'register-' . hash('sha256', $this->request->getIPAddress());
        if (! service('throttler')->check($key, 3, MINUTE)) {
            return redirect()->to('/register')->withInput()->with('error', 'Too many registration attempts. Please try again in one minute.');
        }

        $rules = [
            'name' => 'required|min_length[2]|max_length[100]',
            'email' => 'required|valid_email|max_length[160]',
            'phone' => 'permit_empty|max_length[30]',
            'password' => 'required|min_length[8]',
            'password_confirm' => 'required|matches[password]',
        ];
        if (! $this->validate($rules)) {
            return redirect()->to('/register')->withInput()->with('errors', $this->validator->getErrors());
        }

        $email = strtolower(trim((string) $this->request->getPost('email')));
        if ((new UserModel())->findByEmail($email)) {
            return redirect()->to('/register')->withInput()->with('error', 'That email address is already registered.');
        }

        $registration = (new AuthService())->registerCustomer([
            'name' => $this->request->getPost('name'),
            'email' => $email,
            'phone' => $this->request->getPost('phone'),
            'address' => $this->request->getPost('address'),
            'password' => $this->request->getPost('password'),
        ]);
        if (! $registration) {
            return redirect()->to('/register')->withInput()->with('error', 'Unable to create the account.');
        }

        session()->setFlashdata('verification_email', $email);

        try {
            $sent = (new AccountEmailService())->sendVerification($registration['user'], $registration['token']);
        } catch (Throwable $exception) {
            $sent = false;
            log_message('error', 'Initial verification email failed for user {userId}: {message}', [
                'userId' => $registration['user']['id'],
                'message' => $exception->getMessage(),
            ]);
        }

        if (! $sent) {
            return redirect()->to('/email-verification')->with('error', 'Your account was created, but the verification email could not be sent. Check the Gmail configuration, then request a new link.');
        }

        return redirect()->to('/email-verification')->with('success', 'Account created. Check your email and open the verification link before signing in.');
    }
}
