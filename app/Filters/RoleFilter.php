<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class RoleFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $role = (session()->get('user')['role'] ?? null);
        $allowed = $arguments ?? [];
        if (! in_array($role, $allowed, true)) {
            if ($request->isAJAX() || str_starts_with($request->getUri()->getPath(), 'api/')) {
                return service('response')->setStatusCode(403)->setJSON(['success' => false, 'message' => 'You are not allowed to perform this action.']);
            }
            session()->setFlashdata('error', 'You do not have permission to open that page.');
            return redirect()->to($role ? role_home($role) : '/login');
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): void
    {
    }
}
