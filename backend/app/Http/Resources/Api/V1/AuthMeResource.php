<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Device;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User|Device
 */
class AuthMeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        if ($this->resource instanceof User) {
            $this->resource->loadMissing(['roles.permissions']);

            return [
                'principal_type' => 'user',
                'id' => $this->resource->id,
                'name' => $this->resource->name,
                'email' => $this->resource->email,
                'is_active' => $this->resource->is_active,
                'last_login_at' => $this->resource->last_login_at?->utc()->toIso8601String(),
                'roles' => $this->resource->roles->pluck('name')->values()->all(),
                'permissions' => $this->resource->roles
                    ->flatMap(fn ($role) => $role->permissions->pluck('name'))
                    ->unique()
                    ->values()
                    ->all(),
            ];
        }

        /** @var Device $device */
        $device = $this->resource;
        $token = method_exists($device, 'currentAccessToken')
            ? $device->currentAccessToken()
            : null;

        return [
            'principal_type' => 'device',
            'id' => $device->id,
            'device_id' => $device->device_id,
            'terminal_id' => $device->terminal_id,
            'name' => $device->name,
            'destination_id' => $device->destination_id,
            'status' => $device->status?->value ?? $device->status,
            'is_active' => $device->is_active,
            'maintenance_mode' => $device->maintenance_mode,
            'abilities' => $token?->abilities ?? [],
        ];
    }
}
