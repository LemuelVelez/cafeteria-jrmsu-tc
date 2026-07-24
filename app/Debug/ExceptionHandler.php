<?php

namespace App\Debug;

use CodeIgniter\Debug\ExceptionHandler as FrameworkExceptionHandler;
use CodeIgniter\Debug\ExceptionHandlerInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Exceptions;
use Throwable;

final class ExceptionHandler implements ExceptionHandlerInterface
{
    private FrameworkExceptionHandler $handler;

    public function __construct(Exceptions $config)
    {
        $this->handler = new FrameworkExceptionHandler($config);
    }

    public function handle(
        Throwable $exception,
        RequestInterface $request,
        ResponseInterface $response,
        int $statusCode,
        int $exitCode,
    ): void {
        $this->handler->handle($exception, $request, $response, $statusCode, $exitCode);
    }
}
