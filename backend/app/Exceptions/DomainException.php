<?php

namespace App\Exceptions;

use Exception;

final class DomainException extends Exception
{
    /**
     * @param  list<array{field?: string, code?: string, message?: string}>  $details
     */
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $statusCode = 422,
        public readonly array $details = [],
    ) {
        parent::__construct($message);
    }
}
