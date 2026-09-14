<?php

namespace App\Support\Idempotency;

use App\Models\IdempotencyKey;

final readonly class IdempotencyReservation
{
    private function __construct(
        public IdempotencyKey $record,
        public bool $replay,
    ) {}

    public static function fresh(IdempotencyKey $record): self
    {
        return new self($record, false);
    }

    public static function replay(IdempotencyKey $record): self
    {
        return new self($record, true);
    }

    public function isReplay(): bool
    {
        return $this->replay;
    }

    public function isFresh(): bool
    {
        return ! $this->replay;
    }
}
