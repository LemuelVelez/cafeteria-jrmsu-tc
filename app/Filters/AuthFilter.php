<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (! (session()->get('user')['id'] ?? null)) {
            if ($request->isAJAX() || str_starts_with($request->getUri()->getPath(), 'api/')) {
                return service('response')->setStatusCode(401)->setJSON(['success' => false, 'message' => 'Authentication required.']);
            }
            session()->setFlashdata('error', 'Please sign in to continue.');
            return redirect()->to('/login');
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): void
    {
    }
}
