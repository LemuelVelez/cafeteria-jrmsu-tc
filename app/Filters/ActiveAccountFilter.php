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
            session()->remove('user');
            session()->regenerate(true);
            $path = ltrim($request->getUri()->getPath(), '/');
            if ($request->isAJAX() || str_starts_with($path, 'api/')) {
                return service('response')->setStatusCode(403)->setJSON([
                    'success' => false,
                    'message' => 'Your account is not active.',
                    'data' => null,
                    'errors' => null,
                ]);
            }

            return redirect()->to('/login')->with('error', 'Your account is not active.');
        }

        if (
            $user['role'] === 'customer'
            && (bool) ($user['requires_email_verification'] ?? false)
            && empty($user['email_verified_at'])
        ) {
            $email = (string) ($user['email'] ?? '');
            session()->remove('user');
            session()->regenerate(true);
            $path = ltrim($request->getUri()->getPath(), '/');
            if ($request->isAJAX() || str_starts_with($path, 'api/')) {
                return service('response')->setStatusCode(403)->setJSON([
                    'success' => false,
                    'message' => 'Email verification is required.',
                    'data' => null,
                    'errors' => null,
                ]);
            }

            session()->setFlashdata('verification_email', $email);
            return redirect()->to('/email-verification')->with('error', 'Verify your email address before continuing.');
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): void
    {
    }
}
