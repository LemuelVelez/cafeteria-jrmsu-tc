<?php

namespace App\Filters;

use App\Services\ApiRequestPolicy;
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
            if ($request->isAJAX() || ApiRequestPolicy::isApiPath($request->getUri()->getPath())) {
                return service('response')->setStatusCode(403)->setJSON(['success' => false, 'message' => 'You are not allowed to perform this action.', 'data' => null, 'errors' => null]);
            }
            session()->setFlashdata('error', 'You do not have permission to open that page.');
            return redirect()->to($role ? role_home($role) : '/login');
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): void
    {
    }
}
