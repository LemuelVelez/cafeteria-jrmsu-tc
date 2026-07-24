<?php

namespace App\Debug;

use CodeIgniter\Debug\ExceptionHandler as FrameworkExceptionHandler;

class ExceptionHandler extends FrameworkExceptionHandler
{
    protected function maskSensitiveData(array $trace, array $keysToMask, string $path = ''): array
    {
        foreach ($trace as $index => $line) {
            if (! array_key_exists('args', $line)) {
                continue;
            }

            $maskedLine = parent::maskSensitiveData([$line], $keysToMask, $path);
            $trace[$index] = $maskedLine[0];
        }

        return $trace;
    }
}
