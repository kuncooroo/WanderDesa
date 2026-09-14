<?php

namespace App\Support\Idempotency;

use App\Models\IdempotencyKey;

final readonly class IdempotencyResult
{
    public function __construct(
        public bool $replayed,
        public string $resourceType,
        public int $resourceId,
        public int $responseCode,
        public mixed $value = null,
    ) {}

    public static function fromOutcome(IdempotencyOutcome $outcome): self
    {
        return new self(
            replayed: false,
            resourceType: $outcome->resourceType,
            resourceId: $outcome->resourceId,
            responseCode: $outcome->responseCode,
            value: $outcome->value,
        );
    }

    public static function replay(IdempotencyKey $record): self
    {
        return new self(
            replayed: true,
            resourceType: (string) $record->resource_type,
            resourceId: (int) $record->resource_id,
            responseCode: (int) $record->response_code,
        );
    }
}
