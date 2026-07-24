<?php

namespace App\Controllers\Auth;

use App\Controllers\BaseController;
use App\Models\UserModel;
use App\Services\AuthService;

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

        $id = (new AuthService())->registerCustomer([
            'name' => $this->request->getPost('name'),
            'email' => $email,
            'phone' => $this->request->getPost('phone'),
            'address' => $this->request->getPost('address'),
            'password' => $this->request->getPost('password'),
        ]);
        if (! $id) {
            return redirect()->to('/register')->withInput()->with('error', 'Unable to create the account.');
        }

        return redirect()->to('/login')->with('success', 'Account created. You may now sign in and add a profile photo in My settings.');
    }
}
