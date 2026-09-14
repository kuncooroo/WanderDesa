<?php

namespace App\Models;

use App\Enums\ValidityType;
use Database\Factories\TicketTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'destination_id',
    'code',
    'name',
    'description',
    'currency',
    'unit_price',
    'tax_amount',
    'service_fee_amount',
    'validity_type',
    'validity_days',
    'valid_from_time',
    'valid_until_time',
    'max_per_order',
    'is_active',
])]
class TicketType extends Model
{
    /** @use HasFactory<TicketTypeFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_price' => 'integer',
            'tax_amount' => 'integer',
            'service_fee_amount' => 'integer',
            'validity_type' => ValidityType::class,
            'validity_days' => 'integer',
            'max_per_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @param  Builder<TicketType>  $query
     * @return Builder<TicketType>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(Destination::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }
}
