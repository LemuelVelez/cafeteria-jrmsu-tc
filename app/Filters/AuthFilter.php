<?php

namespace App\Filters;

use App\Models\UserModel;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $session = session();
        $sessionUser = $session->get('user');
        if (! is_array($sessionUser) || empty($sessionUser['id'])) {
            return $this->unauthorized($request, 'Authentication required.');
        }

        $user = (new UserModel())->find((int) $sessionUser['id']);
        if (! $user || $user['status'] !== 'active' || (int) ($user['session_version'] ?? 1) !== (int) ($sessionUser['session_version'] ?? 1)) {
            $session->destroy();
            return $this->unauthorized($request, 'Your session is no longer valid. Please sign in again.');
        }

        $config = config('Cafeteria');
        $role = (string) ($user['role'] ?? 'customer');
        $timeout = max(60, (int) ($config->idleTimeouts[$role] ?? 1800));
        $lastActivity = (int) $session->get('last_activity');
        if ($lastActivity > 0 && time() - $lastActivity > $timeout) {
            $session->destroy();
            return $this->unauthorized($request, 'Your session expired due to inactivity. Please sign in again.');
        }

        $session->set('last_activity', time());
    }

    private function unauthorized(RequestInterface $request, string $message)
    {
        if ($request->isAJAX() || str_starts_with($request->getUri()->getPath(), 'api/')) {
            return service('response')->setStatusCode(401)->setJSON(['success' => false, 'message' => $message, 'data' => null, 'errors' => null]);
        }
        session()->setFlashdata('error', $message);
        return redirect()->to('/login');
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): void
    {
    }
}
