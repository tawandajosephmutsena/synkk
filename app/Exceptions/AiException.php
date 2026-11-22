<?php

namespace App\Exceptions;

use RuntimeException;

class AiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorCode = 'PROVIDER_ERROR',
        public readonly int $statusCode = 400,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
