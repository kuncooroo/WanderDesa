<?php

namespace App\Models;

use App\Enums\WebhookProcessStatus;
use Database\Factories\PaymentWebhookEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'provider',
    'event_id',
    'payment_id',
    'payload_hash',
    'processed_at',
    'process_status',
])]
class PaymentWebhookEvent extends Model
{
    /** @use HasFactory<PaymentWebhookEventFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'process_status' => WebhookProcessStatus::class,
            'processed_at' => 'datetime',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
