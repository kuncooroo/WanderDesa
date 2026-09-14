<?php

namespace App\Models;

use Database\Factories\AuditLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable([
    'actor_type',
    'actor_id',
    'action',
    'entity_type',
    'entity_id',
    'destination_id',
    'ip_address',
    'user_agent',
    'before_json',
    'after_json',
    'meta_json',
])]
class AuditLog extends Model
{
    /** @use HasFactory<AuditLogFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'actor_id' => 'integer',
            'entity_id' => 'integer',
            'before_json' => 'array',
            'after_json' => 'array',
            'meta_json' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Audit logs are append-only.');
        });

        static::deleting(function (): never {
            throw new LogicException('Audit logs are append-only.');
        });
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(Destination::class);
    }

    /**
     * @param  Builder<AuditLog>  $query
     * @return Builder<AuditLog>
     */
    public function scopeAction($query, ?string $action)
    {
        if ($action !== null && $action !== '') {
            $query->where('action', 'like', '%'.$action.'%');
        }

        return $query;
    }

    /**
     * @param  Builder<AuditLog>  $query
     * @return Builder<AuditLog>
     */
    public function scopeActorType($query, ?string $actorType)
    {
        if ($actorType !== null && $actorType !== '') {
            $query->where('actor_type', $actorType);
        }

        return $query;
    }

    /**
     * @param  Builder<AuditLog>  $query
     * @return Builder<AuditLog>
     */
    public function scopeEntityType($query, ?string $entityType)
    {
        if ($entityType !== null && $entityType !== '') {
            $query->where('entity_type', $entityType);
        }

        return $query;
    }

    /**
     * @param  Builder<AuditLog>  $query
     * @return Builder<AuditLog>
     */
    public function scopeCreatedBetween($query, ?string $from, ?string $to)
    {
        if ($from !== null && $from !== '') {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to !== null && $to !== '') {
            $query->whereDate('created_at', '<=', $to);
        }

        return $query;
    }
}
