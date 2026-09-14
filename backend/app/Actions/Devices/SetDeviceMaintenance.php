<?php

namespace App\Actions\Devices;

use App\Enums\DeviceStatus;
use App\Enums\PermissionName;
use App\Exceptions\DomainException;
use App\Models\Device;
use App\Models\User;
use App\Support\AuditWriter;
use App\Support\Authorization\Authorizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class SetDeviceMaintenance
{
    public function __construct(
        private readonly AuditWriter $audit,
    ) {}

    public function handle(
        User $actor,
        Device $device,
        bool $enabled,
        ?Request $request = null,
    ): Device {
        Authorizer::authorize($actor, PermissionName::KiosksMaintenance);

        return DB::transaction(function () use ($actor, $device, $enabled, $request): Device {
            $locked = Device::query()->whereKey($device->id)->lockForUpdate()->firstOrFail();
            $before = $this->snapshot($locked);

            if ($locked->status === DeviceStatus::Disabled || ! $locked->is_active) {
                throw new DomainException(
                    'device.state_conflict',
                    'Disabled or inactive devices cannot enter or leave maintenance.',
                    409,
                );
            }

            if ($locked->status === DeviceStatus::Registered) {
                throw new DomainException(
                    'device.state_conflict',
                    'Registered devices must be activated before maintenance can be toggled.',
                    409,
                );
            }

            if ($enabled) {
                $locked->forceFill([
                    'status' => DeviceStatus::Maintenance,
                    'maintenance_mode' => true,
                ])->save();
                $action = 'device.maintenance_on';
            } else {
                $locked->forceFill([
                    'status' => DeviceStatus::Active,
                    'maintenance_mode' => false,
                ])->save();
                $action = 'device.maintenance_off';
            }

            $this->audit->write(
                action: $action,
                actorType: 'user',
                actorId: $actor->id,
                entityType: 'device',
                entityId: $locked->id,
                before: $before,
                after: $this->snapshot($locked->fresh()),
                meta: ['device_id' => $locked->device_id, 'enabled' => $enabled],
                request: $request,
            );

            return $locked->fresh(['destination']);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Device $device): array
    {
        return [
            'id' => $device->id,
            'device_id' => $device->device_id,
            'status' => $device->status->value,
            'is_active' => $device->is_active,
            'maintenance_mode' => $device->maintenance_mode,
        ];
    }
}
