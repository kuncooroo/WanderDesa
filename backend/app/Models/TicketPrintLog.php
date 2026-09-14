<?php

namespace App\Models;

use App\Enums\PrintAttemptResult;
use Database\Factories\TicketPrintLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable([
    'ticket_id',
    'result',
    'actor_type',
    'actor_id',
    'message',
    'created_at',
])]
class TicketPrintLog extends Model
{
    /** @use HasFactory<TicketPrintLogFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'result' => PrintAttemptResult::class,
            'actor_id' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Ticket print logs are append-only.');
        });

        static::deleting(function (): never {
            throw new LogicException('Ticket print logs are append-only.');
        });
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function isSuccess(): bool
    {
        return $this->result === PrintAttemptResult::Success;
    }
}
