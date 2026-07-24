<?php

namespace App\Controllers\Auth;

use App\Controllers\BaseController;
use App\Services\AuthService;

class LogoutController extends BaseController
{
    public function __invoke()
    {
        (new AuthService())->logout();
        return redirect()->to('/')->with('success', 'You have been signed out.');
    }
}
