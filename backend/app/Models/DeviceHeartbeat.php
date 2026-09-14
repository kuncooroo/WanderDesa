<?php

namespace App\Models;

use Database\Factories\DeviceHeartbeatFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['device_id', 'software_version', 'printer_ok', 'payload_json', 'created_at'])]
class DeviceHeartbeat extends Model
{
    /** @use HasFactory<DeviceHeartbeatFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'printer_ok' => 'boolean',
            'payload_json' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
