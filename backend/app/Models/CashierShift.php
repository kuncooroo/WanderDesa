<?php

namespace App\Models;

use App\Enums\CashierShiftStatus;
use Database\Factories\CashierShiftFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'opened_at',
    'closed_at',
    'initial_cash',
    'total_cash_sales',
    'total_cash_refund',
    'expected_cash',
    'actual_cash',
    'difference',
    'status',
    'notes',
])]
class CashierShift extends Model
{
    /** @use HasFactory<CashierShiftFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'initial_cash' => 'integer',
            'total_cash_sales' => 'integer',
            'total_cash_refund' => 'integer',
            'expected_cash' => 'integer',
            'actual_cash' => 'integer',
            'difference' => 'integer',
            'status' => CashierShiftStatus::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cashSales(): HasMany
    {
        return $this->hasMany(Payment::class, 'cashier_shift_id');
    }

    public function cashRefunds(): HasMany
    {
        return $this->hasMany(Payment::class, 'refund_cashier_shift_id');
    }

    public function isOpen(): bool
    {
        return $this->status === CashierShiftStatus::Open;
    }

    public function isClosed(): bool
    {
        return $this->status === CashierShiftStatus::Closed;
    }

    public function computeExpectedCash(): int
    {
        return (int) $this->initial_cash
            + (int) $this->total_cash_sales
            - (int) $this->total_cash_refund;
    }
}
