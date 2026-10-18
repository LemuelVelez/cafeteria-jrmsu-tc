<?php

namespace App\Controllers\Auth;

use App\Controllers\BaseController;
use App\Services\AuthService;

class LoginController extends BaseController
{
    public function index(): string
    {
        return $this->render('auth/login');
    }

    public function store()
    {
        $key = 'login-' . hash('sha256', $this->request->getIPAddress());
        if (! service('throttler')->check($key, 5, MINUTE)) {
            return redirect()->to('/login')->withInput()->with('error', 'Too many sign-in attempts. Please try again in one minute.');
        }

        $rules = ['email' => 'required|valid_email', 'password' => 'required'];
        if (! $this->validate($rules)) {
            return redirect()->to('/login')->withInput()->with('errors', $this->validator->getErrors());
        }

        $auth = new AuthService();
        if (! $auth->attempt((string) $this->request->getPost('email'), (string) $this->request->getPost('password'))) {
            if ($auth->failureReason() === 'email_verification_required') {
                session()->setFlashdata('verification_email', strtolower(trim((string) $this->request->getPost('email'))));
                return redirect()->to('/email-verification')->with('error', 'Verify your email address before signing in.');
            }

            return redirect()->to('/login')->withInput()->with('error', 'Invalid email, password, or account status.');
        }

        $user = session()->get('user');
        $profile=(new \App\Models\UserModel())->find((int)$user['id']);
        $required=$user['role']==='admin' && (string)(db_connect()->table('settings')->where('setting_key','admin_totp_required')->get()->getRow('setting_value')??'0')==='1';
        if(!empty($profile['totp_secret']))return redirect()->to('/two-factor')->with('success','Enter your authenticator code.');
        if($required)return redirect()->to('/settings')->with('error','Set up two-factor authentication before accessing administration.');
        return redirect()->to(role_home($user['role']))->with('success', 'Welcome back, ' . $user['name'] . '!');
    }
}
