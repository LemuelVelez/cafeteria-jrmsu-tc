<?php

namespace App\Filters;

use App\Models\UserModel;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class ActiveAccountFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $userId = (session()->get('user')['id'] ?? null);
        if (! $userId) {
            return null;
        }
        $user = (new UserModel())->find($userId);
        if (! $user || $user['status'] !== 'active') {
            session()->destroy();
            return redirect()->to('/login')->with('error', 'Your account is not active.');
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): void
    {
    }
}
