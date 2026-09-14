<?php

namespace App\Models;

use App\Enums\Channel;
use App\Enums\OrderStatus;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'order_number',
    'destination_id',
    'channel',
    'status',
    'visitor_id',
    'device_id',
    'created_by_user_id',
    'currency',
    'subtotal',
    'discount_total',
    'tax_total',
    'service_fee_total',
    'grand_total',
    'discount_code',
    'customer_note',
    'expires_at',
    'paid_at',
    'cancelled_at',
    'expired_at',
    'refunded_at',
])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channel' => Channel::class,
            'status' => OrderStatus::class,
            'subtotal' => 'integer',
            'discount_total' => 'integer',
            'tax_total' => 'integer',
            'service_fee_total' => 'integer',
            'grand_total' => 'integer',
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'expired_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(Destination::class);
    }

    public function visitor(): BelongsTo
    {
        return $this->belongsTo(Visitor::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function isPendingPayment(): bool
    {
        return $this->status === OrderStatus::PendingPayment;
    }

    /**
     * TTL elapsed while still awaiting payment (expire job is TASK-026).
     */
    public function hasExpiredUnpaid(): bool
    {
        return $this->isPendingPayment()
            && $this->expires_at !== null
            && $this->expires_at->lte(now());
    }
}
