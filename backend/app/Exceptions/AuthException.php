<?php

namespace App\Exceptions;

use Exception;

final class AuthException extends Exception
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $statusCode = 401,
    ) {
        parent::__construct($message);
    }
}
