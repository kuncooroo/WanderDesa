<?php

namespace App\Actions\Devices;

use App\Enums\DeviceStatus;
use App\Exceptions\AuthException;
use App\Exceptions\DomainException;
use App\Models\Device;
use App\Support\AuditWriter;
use App\Support\DeviceAbilities;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class ExchangeDeviceActivation
{
    public function __construct(
        private readonly AuditWriter $audit,
    ) {}

    /**
     * @param  array{
     *     device_id: string,
     *     activation_code: string,
     *     software_version?: string|null
     * }  $data
     * @return array{device: Device, plain_text_token: string}
     */
    public function handle(array $data, ?Request $request = null): array
    {
        return DB::transaction(function () use ($data, $request): array {
            $device = Device::query()
                ->where('device_id', $data['device_id'])
                ->lockForUpdate()
                ->first();

            if ($device === null) {
                throw new AuthException(
                    'auth.activation_invalid',
                    'Invalid activation material.',
                    401,
                );
            }

            if ($device->status === DeviceStatus::Disabled) {
                throw new DomainException(
                    'device.inactive',
                    'This device is disabled and cannot be activated.',
                    403,
                );
            }

            if (
                $device->activation_secret_hash === null
                || $device->activation_expires_at === null
                || $device->activation_expires_at->isPast()
                || ! Hash::check($data['activation_code'], $device->activation_secret_hash)
            ) {
                throw new AuthException(
                    'auth.activation_invalid',
                    'Invalid or expired activation material.',
                    401,
                );
            }

            $before = [
                'status' => $device->status->value,
                'is_active' => $device->is_active,
            ];

            $device->forceFill([
                'status' => DeviceStatus::Active,
                'is_active' => true,
                'maintenance_mode' => false,
                'activated_at' => $device->activated_at ?? now(),
                'deactivated_at' => null,
                'activation_secret_hash' => null,
                'activation_expires_at' => null,
                'software_version' => $data['software_version'] ?? $device->software_version,
            ])->save();

            $device->tokens()->delete();
            $token = $device->createToken('kiosk', DeviceAbilities::defaultKiosk());

            $this->audit->write(
                action: 'device.token_issued',
                actorType: 'device',
                actorId: $device->id,
                entityType: 'device',
                entityId: $device->id,
                before: $before,
                after: [
                    'status' => DeviceStatus::Active->value,
                    'is_active' => true,
                    'maintenance_mode' => false,
                ],
                meta: [
                    'device_id' => $device->device_id,
                    'abilities' => DeviceAbilities::defaultKiosk(),
                ],
                request: $request,
            );

            return [
                'device' => $device->fresh(['destination']),
                'plain_text_token' => $token->plainTextToken,
            ];
        });
    }
}
