<?php

namespace App\Models;

use Database\Factories\CheckInFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'ticket_id',
    'destination_id',
    'gate_id',
    'result',
    'checked_in_at',
    'checked_in_by_user_id',
    'device_id',
    'client_context',
])]
class CheckIn extends Model
{
    /** @use HasFactory<CheckInFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'checked_in_at' => 'datetime',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(Destination::class);
    }

    public function gate(): BelongsTo
    {
        return $this->belongsTo(Gate::class);
    }

    public function checkedInBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_in_by_user_id');
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
