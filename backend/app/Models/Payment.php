<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'payment_number',
    'order_id',
    'status',
    'method',
    'provider',
    'amount',
    'currency',
    'provider_payment_id',
    'provider_reference',
    'paid_at',
    'failed_at',
    'expired_at',
    'cancelled_at',
    'refunded_at',
    'failure_code',
    'failure_message',
    'collected_by_user_id',
    'cashier_shift_id',
    'refund_cashier_shift_id',
    'device_id',
    'metadata_json',
])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'method' => PaymentMethod::class,
            'amount' => 'integer',
            'paid_at' => 'datetime',
            'failed_at' => 'datetime',
            'expired_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'refunded_at' => 'datetime',
            'metadata_json' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function collectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collected_by_user_id');
    }

    public function cashierShift(): BelongsTo
    {
        return $this->belongsTo(CashierShift::class, 'cashier_shift_id');
    }

    public function refundCashierShift(): BelongsTo
    {
        return $this->belongsTo(CashierShift::class, 'refund_cashier_shift_id');
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function webhookEvents(): HasMany
    {
        return $this->hasMany(PaymentWebhookEvent::class);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [PaymentStatus::Pending, PaymentStatus::Processing], true);
    }

    public function isPaid(): bool
    {
        return $this->status === PaymentStatus::Paid;
    }

    public function isCash(): bool
    {
        return $this->method instanceof PaymentMethod
            ? $this->method->isCash()
            : $this->method === PaymentMethod::Cash->value;
    }

    public function isProviderBacked(): bool
    {
        return $this->method instanceof PaymentMethod
            ? $this->method->isProviderBacked()
            : ! $this->isCash();
    }

    public function nextAction(): ?array
    {
        $meta = $this->metadata_json ?? [];
        $action = $meta['next_action'] ?? null;

        return is_array($action) ? $action : null;
    }
}
