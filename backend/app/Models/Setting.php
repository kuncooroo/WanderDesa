<?php

namespace App\Models;

use Database\Factories\SettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['key', 'value', 'type', 'description', 'updated_by_user_id'])]
class Setting extends Model
{
    /** @use HasFactory<SettingFactory> */
    use HasFactory;

    public const ORDER_PAYMENT_TTL_MINUTES = 'order.payment_ttl_minutes';

    public const ORDER_PAYMENT_TTL_DEFAULT = 15;

    public const KIOSK_HEARTBEAT_STALE_SECONDS = 'kiosk.heartbeat_stale_seconds';

    public const KIOSK_HEARTBEAT_STALE_DEFAULT = 120;

    /** Activation code TTL (docs/08 TBD; default 60 minutes). */
    public const KIOSK_ACTIVATION_TTL_MINUTES = 'kiosk.activation_ttl_minutes';

    public const KIOSK_ACTIVATION_TTL_DEFAULT = 60;

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public static function intValue(string $key, int $default): int
    {
        $row = static::query()->where('key', $key)->first();

        if ($row === null || $row->value === null || $row->value === '') {
            return $default;
        }

        if (! is_numeric($row->value)) {
            return $default;
        }

        return (int) $row->value;
    }

    /**
     * Unpaid order TTL in minutes (docs/06). Invalid/missing values fall back to 15.
     */
    public static function orderPaymentTtlMinutes(): int
    {
        $minutes = self::intValue(self::ORDER_PAYMENT_TTL_MINUTES, self::ORDER_PAYMENT_TTL_DEFAULT);

        return $minutes > 0 ? $minutes : self::ORDER_PAYMENT_TTL_DEFAULT;
    }

    /**
     * Seconds without heartbeat before fleet view marks a device offline (docs/06).
     */
    public static function heartbeatStaleSeconds(): int
    {
        $seconds = self::intValue(self::KIOSK_HEARTBEAT_STALE_SECONDS, self::KIOSK_HEARTBEAT_STALE_DEFAULT);

        return $seconds > 0 ? $seconds : self::KIOSK_HEARTBEAT_STALE_DEFAULT;
    }

    /**
     * Minutes until an unused activation code expires.
     */
    public static function activationTtlMinutes(): int
    {
        $minutes = self::intValue(self::KIOSK_ACTIVATION_TTL_MINUTES, self::KIOSK_ACTIVATION_TTL_DEFAULT);

        return $minutes > 0 ? $minutes : self::KIOSK_ACTIVATION_TTL_DEFAULT;
    }
}
