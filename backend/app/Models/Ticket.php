<?php

namespace App\Models;

use App\Enums\Channel;
use App\Enums\TicketStatus;
use Database\Factories\TicketFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'ticket_code',
    'order_id',
    'order_item_id',
    'destination_id',
    'ticket_type_id',
    'payment_id',
    'channel',
    'status',
    'currency',
    'unit_price_snapshot',
    'tax_snapshot',
    'service_fee_snapshot',
    'valid_start_at',
    'valid_end_at',
    'issued_at',
    'activated_at',
    'used_at',
    'expired_at',
    'cancelled_at',
    'refunded_at',
    'issued_by_user_id',
    'issued_by_device_id',
    'qr_payload',
    'qr_payload_hash',
    'qr_version',
    'qr_secret_hint',
])]
class Ticket extends Model
{
    /** @use HasFactory<TicketFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channel' => Channel::class,
            'status' => TicketStatus::class,
            'unit_price_snapshot' => 'integer',
            'tax_snapshot' => 'integer',
            'service_fee_snapshot' => 'integer',
            'valid_start_at' => 'datetime',
            'valid_end_at' => 'datetime',
            'issued_at' => 'datetime',
            'activated_at' => 'datetime',
            'used_at' => 'datetime',
            'expired_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'refunded_at' => 'datetime',
            'qr_version' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(Destination::class);
    }

    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function issuedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by_user_id');
    }

    public function issuedByDevice(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'issued_by_device_id');
    }

    public function checkIn(): HasOne
    {
        return $this->hasOne(CheckIn::class);
    }

    public function printLogs(): HasMany
    {
        return $this->hasMany(TicketPrintLog::class);
    }
}
