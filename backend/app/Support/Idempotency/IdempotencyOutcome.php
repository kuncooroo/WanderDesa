<?php

namespace App\Support\Idempotency;

final readonly class IdempotencyOutcome
{
    public function __construct(
        public string $resourceType,
        public int $resourceId,
        public int $responseCode = 201,
        public mixed $value = null,
    ) {}
}
