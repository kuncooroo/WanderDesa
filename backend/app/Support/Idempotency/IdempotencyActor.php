<?php

namespace App\Support\Idempotency;

use App\Enums\IdempotencyActorType;
use App\Models\Device;
use App\Models\User;
use InvalidArgumentException;

final readonly class IdempotencyActor
{
    public function __construct(
        public IdempotencyActorType $type,
        public int $id,
    ) {
        if ($this->id < 1) {
            throw new InvalidArgumentException('Idempotency actor id must be a positive integer.');
        }
    }

    public static function fromPrincipal(object $principal): self
    {
        if ($principal instanceof Device) {
            return new self(IdempotencyActorType::Device, (int) $principal->getKey());
        }

        if ($principal instanceof User) {
            return new self(IdempotencyActorType::User, (int) $principal->getKey());
        }

        throw new InvalidArgumentException('Idempotency actor must be a Device or User.');
    }
}
