<?php

namespace App\Actions\Devices;

use App\Enums\PermissionName;
use App\Models\Device;
use App\Models\Setting;
use App\Models\User;
use App\Support\Authorization\Authorizer;
use Illuminate\Support\Collection;

final class GetFleetHealth
{
    /**
     * @return Collection<int, array{
     *     id: int,
     *     device_id: string,
     *     status: string,
     *     maintenance_mode: bool,
     *     last_heartbeat_at: string|null,
     *     online: bool,
     *     software_version: string|null
     * }>
     */
    public function handle(User $actor): Collection
    {
        Authorizer::authorize($actor, PermissionName::KiosksView);

        $staleSeconds = Setting::heartbeatStaleSeconds();

        return Device::query()
            ->orderBy('name')
            ->get()
            ->map(function (Device $device) use ($staleSeconds): array {
                return [
                    'id' => $device->id,
                    'device_id' => $device->device_id,
                    'status' => $device->status->value,
                    'maintenance_mode' => $device->maintenance_mode,
                    'last_heartbeat_at' => $device->last_heartbeat_at?->utc()->format('Y-m-d\TH:i:s\Z'),
                    'online' => $device->isOnline($staleSeconds),
                    'software_version' => $device->software_version,
                ];
            });
    }
}
