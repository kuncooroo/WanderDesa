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

final class DeactivateDevice
{
    public function __construct(
        private readonly AuditWriter $audit,
    ) {}

    public function handle(User $actor, Device $device, ?Request $request = null): Device
    {
        Authorizer::authorize($actor, PermissionName::KiosksDeactivate);

        return DB::transaction(function () use ($actor, $device, $request): Device {
            $locked = Device::query()->whereKey($device->id)->lockForUpdate()->firstOrFail();
            $before = $this->snapshot($locked);

            if ($locked->status === DeviceStatus::Disabled) {
                throw new DomainException(
                    'device.state_conflict',
                    'Device is already disabled.',
                    409,
                );
            }

            $locked->forceFill([
                'status' => DeviceStatus::Disabled,
                'is_active' => false,
                'maintenance_mode' => false,
                'deactivated_at' => now(),
                'activation_secret_hash' => null,
                'activation_expires_at' => null,
            ])->save();

            $locked->tokens()->delete();

            $this->audit->write(
                action: 'device.disabled',
                actorType: 'user',
                actorId: $actor->id,
                entityType: 'device',
                entityId: $locked->id,
                before: $before,
                after: $this->snapshot($locked->fresh()),
                meta: ['device_id' => $locked->device_id],
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
            'deactivated_at' => $device->deactivated_at?->utc()->format('Y-m-d\TH:i:s\Z'),
        ];
    }
}
