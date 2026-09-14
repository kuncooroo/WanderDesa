<?php

namespace App\Models;

use Database\Factories\IdempotencyKeyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'key_hash',
    'scope',
    'actor_type',
    'actor_id',
    'request_hash',
    'response_code',
    'resource_type',
    'resource_id',
    'locked_at',
])]
class IdempotencyKey extends Model
{
    /** @use HasFactory<IdempotencyKeyFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'actor_id' => 'integer',
            'response_code' => 'integer',
            'resource_id' => 'integer',
            'locked_at' => 'datetime',
        ];
    }

    public function isCompleted(): bool
    {
        return $this->resource_id !== null;
    }

    public function isInProgress(): bool
    {
        return $this->locked_at !== null && $this->resource_id === null;
    }

    public function isLockStale(int $ttlSeconds): bool
    {
        if (! $this->isInProgress() || $this->locked_at === null) {
            return false;
        }

        return $this->locked_at->copy()->addSeconds($ttlSeconds)->lte(now());
    }
}
