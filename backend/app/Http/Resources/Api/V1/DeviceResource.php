<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Device;
use App\Support\ApiTimestamp;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Device
 */
class DeviceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Device $device */
        $device = $this->resource;

        return [
            'id' => $device->id,
            'device_id' => $device->device_id,
            'terminal_id' => $device->terminal_id,
            'destination_id' => $device->destination_id,
            'name' => $device->name,
            'status' => $device->status->value,
            'is_active' => $device->is_active,
            'maintenance_mode' => $device->maintenance_mode,
            'software_version' => $device->software_version,
            'hardware_version' => $device->hardware_version,
            'last_heartbeat_at' => ApiTimestamp::utc($device->last_heartbeat_at),
            'registered_at' => ApiTimestamp::utc($device->registered_at),
            'activated_at' => ApiTimestamp::utc($device->activated_at),
            'deactivated_at' => ApiTimestamp::utc($device->deactivated_at),
        ];
    }
}
