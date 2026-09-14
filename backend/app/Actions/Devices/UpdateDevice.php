<?php

namespace App\Actions\Devices;

use App\Enums\PermissionName;
use App\Exceptions\DomainException;
use App\Models\Destination;
use App\Models\Device;
use App\Models\User;
use App\Support\AuditWriter;
use App\Support\Authorization\Authorizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class UpdateDevice
{
    public function __construct(
        private readonly AuditWriter $audit,
    ) {}

    /**
     * @param  array{name?: string, destination_id?: int}  $data
     */
    public function handle(User $actor, Device $device, array $data, ?Request $request = null): Device
    {
        Authorizer::authorize($actor, PermissionName::KiosksUpdate);

        return DB::transaction(function () use ($actor, $device, $data, $request): Device {
            $locked = Device::query()->whereKey($device->id)->lockForUpdate()->firstOrFail();
            $before = $this->snapshot($locked);

            if (array_key_exists('destination_id', $data)) {
                $destination = Destination::query()->findOrFail($data['destination_id']);

                if (! $destination->is_active) {
                    throw new DomainException(
                        'destination.inactive',
                        'Cannot bind a kiosk to an inactive destination.',
                        422,
                    );
                }

                $locked->destination_id = $destination->id;
            }

            if (array_key_exists('name', $data)) {
                $locked->name = $data['name'];
            }

            $locked->save();

            $this->audit->write(
                action: 'device.updated',
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
            'name' => $device->name,
            'destination_id' => $device->destination_id,
            'status' => $device->status->value,
        ];
    }
}
