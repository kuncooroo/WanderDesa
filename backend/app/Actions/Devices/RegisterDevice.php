<?php

namespace App\Actions\Devices;

use App\Enums\DeviceStatus;
use App\Enums\PermissionName;
use App\Exceptions\DomainException;
use App\Models\Destination;
use App\Models\Device;
use App\Models\User;
use App\Support\AuditWriter;
use App\Support\Authorization\Authorizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class RegisterDevice
{
    public function __construct(
        private readonly AuditWriter $audit,
    ) {}

    /**
     * @param  array{
     *     device_id: string,
     *     destination_id: int,
     *     name: string,
     *     terminal_id?: string|null
     * }  $data
     */
    public function handle(User $actor, array $data, ?Request $request = null): Device
    {
        Authorizer::authorize($actor, PermissionName::KiosksCreate);

        return DB::transaction(function () use ($actor, $data, $request): Device {
            if (Device::query()->where('device_id', $data['device_id'])->exists()) {
                throw new DomainException(
                    'device.duplicate',
                    'A device with this device_id already exists.',
                    409,
                );
            }

            $destination = Destination::query()->findOrFail($data['destination_id']);

            if (! $destination->is_active) {
                throw new DomainException(
                    'destination.inactive',
                    'Cannot register a kiosk against an inactive destination.',
                    422,
                );
            }

            $device = Device::query()->create([
                'device_id' => $data['device_id'],
                'terminal_id' => $data['terminal_id'] ?? null,
                'destination_id' => $destination->id,
                'name' => $data['name'],
                'status' => DeviceStatus::Registered,
                'is_active' => false,
                'maintenance_mode' => false,
                'registered_at' => now(),
            ]);

            $this->audit->write(
                action: 'device.registered',
                actorType: 'user',
                actorId: $actor->id,
                entityType: 'device',
                entityId: $device->id,
                before: null,
                after: $this->snapshot($device),
                meta: ['device_id' => $device->device_id],
                request: $request,
            );

            return $device->fresh(['destination']);
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
            'terminal_id' => $device->terminal_id,
            'destination_id' => $device->destination_id,
            'name' => $device->name,
            'status' => $device->status->value,
            'is_active' => $device->is_active,
            'maintenance_mode' => $device->maintenance_mode,
        ];
    }
}
