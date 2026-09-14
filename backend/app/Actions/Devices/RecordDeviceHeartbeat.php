<?php

namespace App\Actions\Devices;

use App\Enums\DeviceStatus;
use App\Exceptions\DomainException;
use App\Models\Device;
use App\Models\DeviceHeartbeat;
use App\Support\DeviceAbilities;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class RecordDeviceHeartbeat
{
    /**
     * @param  array{
     *     software_version: string,
     *     hardware_version?: string|null,
     *     printer_ok?: bool|null,
     *     extras?: array<string, mixed>|null
     * }  $data
     * @return array{device: Device, server_time: CarbonInterface, commands: list<string>}
     */
    public function handle(Device $device, array $data, ?Request $request = null): array
    {
        if (! $device->tokenCan(DeviceAbilities::HEARTBEAT)) {
            throw new AuthorizationException('Missing device ability: devices:heartbeat');
        }

        if ($device->status === DeviceStatus::Disabled || ! $device->is_active) {
            throw new DomainException(
                'device.inactive',
                'Disabled devices cannot heartbeat.',
                403,
            );
        }

        return DB::transaction(function () use ($device, $data, $request): array {
            $locked = Device::query()->whereKey($device->id)->lockForUpdate()->firstOrFail();

            $now = now();
            $locked->forceFill([
                'software_version' => $data['software_version'],
                'hardware_version' => $data['hardware_version'] ?? $locked->hardware_version,
                'last_heartbeat_at' => $now,
                'last_ip' => $request?->ip(),
            ])->save();

            DeviceHeartbeat::query()->create([
                'device_id' => $locked->id,
                'software_version' => $data['software_version'],
                'printer_ok' => $data['printer_ok'] ?? null,
                'payload_json' => [
                    'hardware_version' => $data['hardware_version'] ?? null,
                    'extras' => $data['extras'] ?? null,
                ],
                'created_at' => $now,
            ]);

            $commands = [];
            if ($locked->isInMaintenance()) {
                $commands[] = 'enter_maintenance';
            }

            return [
                'device' => $locked->fresh(),
                'server_time' => $now,
                'commands' => $commands,
            ];
        });
    }
}
