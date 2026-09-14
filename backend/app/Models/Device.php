<?php

namespace App\Models;

use App\Enums\DeviceStatus;
use Database\Factories\DeviceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'device_id',
    'terminal_id',
    'destination_id',
    'name',
    'status',
    'is_active',
    'maintenance_mode',
    'software_version',
    'hardware_version',
    'last_heartbeat_at',
    'last_ip',
    'activation_secret_hash',
    'activation_expires_at',
    'registered_at',
    'activated_at',
    'deactivated_at',
])]
#[Hidden(['activation_secret_hash'])]
class Device extends Model
{
    /** @use HasFactory<DeviceFactory> */
    use HasApiTokens, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DeviceStatus::class,
            'is_active' => 'boolean',
            'maintenance_mode' => 'boolean',
            'last_heartbeat_at' => 'datetime',
            'activation_expires_at' => 'datetime',
            'registered_at' => 'datetime',
            'activated_at' => 'datetime',
            'deactivated_at' => 'datetime',
        ];
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(Destination::class);
    }

    public function heartbeats(): HasMany
    {
        return $this->hasMany(DeviceHeartbeat::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'issued_by_device_id');
    }

    public function checkIns(): HasMany
    {
        return $this->hasMany(CheckIn::class);
    }

    /**
     * Kiosk may sell only when ACTIVE, enabled, and not in maintenance (docs/05, docs/07).
     */
    public function isSellable(): bool
    {
        return $this->status === DeviceStatus::Active
            && $this->is_active
            && ! $this->maintenance_mode;
    }

    public function isInMaintenance(): bool
    {
        return $this->maintenance_mode || $this->status === DeviceStatus::Maintenance;
    }

    public function isDisabled(): bool
    {
        return $this->status === DeviceStatus::Disabled || ! $this->is_active;
    }

    public function hasValidActivationSecret(): bool
    {
        return $this->activation_secret_hash !== null
            && $this->activation_expires_at !== null
            && $this->activation_expires_at->isFuture();
    }

    /**
     * Derived fleet online view from stale heartbeat threshold (docs/05 §22).
     */
    public function isOnline(?int $staleSeconds = null): bool
    {
        if ($this->last_heartbeat_at === null) {
            return false;
        }

        $stale = $staleSeconds ?? Setting::heartbeatStaleSeconds();

        return $this->last_heartbeat_at->gt(now()->subSeconds($stale));
    }
}
