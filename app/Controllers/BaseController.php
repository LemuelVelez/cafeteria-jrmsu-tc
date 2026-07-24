<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\CLIRequest;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

abstract class BaseController extends Controller
{
    protected RequestInterface|IncomingRequest|CLIRequest $request;
    protected $helpers = ['cafeteria', 'form', 'url'];
    protected $session;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger): void
    {
        parent::initController($request, $response, $logger);
        $this->session = service('session');
    }

    protected function render(string $view, array $data = []): string
    {
        $data += [
            'cafeteriaName' => (string) env('CAFETERIA_NAME', 'JRMSU-TC Cafeteria'),
            'currentUser' => $this->session->get('user'),
        ];
        return view($view, $data);
    }

    protected function jsonSuccess(string $message, mixed $data = null, int $status = 200): ResponseInterface
    {
        return $this->response->setStatusCode($status)->setJSON([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ]);
    }

    protected function jsonError(string $message, mixed $errors = null, int $status = 422): ResponseInterface
    {
        return $this->response->setStatusCode($status)->setJSON([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ]);
    }
}
