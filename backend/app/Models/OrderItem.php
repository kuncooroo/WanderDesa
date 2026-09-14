<?php

namespace App\Models;

use Database\Factories\OrderItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'order_id',
    'ticket_type_id',
    'ticket_type_code',
    'ticket_type_name',
    'quantity',
    'unit_price',
    'tax_amount',
    'service_fee_amount',
    'line_subtotal',
    'line_tax_total',
    'line_service_fee_total',
    'line_grand_total',
])]
class OrderItem extends Model
{
    /** @use HasFactory<OrderItemFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'integer',
            'tax_amount' => 'integer',
            'service_fee_amount' => 'integer',
            'line_subtotal' => 'integer',
            'line_tax_total' => 'integer',
            'line_service_fee_total' => 'integer',
            'line_grand_total' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }
}
