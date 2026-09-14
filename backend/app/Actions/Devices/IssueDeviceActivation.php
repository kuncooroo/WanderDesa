<?php

namespace App\Actions\Devices;

use App\Enums\DeviceStatus;
use App\Enums\PermissionName;
use App\Exceptions\DomainException;
use App\Models\Device;
use App\Models\Setting;
use App\Models\User;
use App\Support\AuditWriter;
use App\Support\Authorization\Authorizer;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class IssueDeviceActivation
{
    private const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function __construct(
        private readonly AuditWriter $audit,
    ) {}

    /**
     * @return array{device: Device, activation_code: string, expires_at: CarbonInterface}
     */
    public function handle(
        User $actor,
        Device $device,
        bool $rotateSecret = true,
        ?Request $request = null,
    ): array {
        Authorizer::authorize($actor, PermissionName::KiosksActivate);

        return DB::transaction(function () use ($actor, $device, $rotateSecret, $request): array {
            $locked = Device::query()->whereKey($device->id)->lockForUpdate()->firstOrFail();
            $before = $this->snapshot($locked);

            if (! $rotateSecret && $locked->hasValidActivationSecret()) {
                throw new DomainException(
                    'device.activation_exists',
                    'An unused activation code already exists for this device.',
                    409,
                );
            }

            // Disabled devices can be re-armed for activation (docs/05 §24 alternative).
            if ($locked->status === DeviceStatus::Disabled) {
                $locked->status = DeviceStatus::Registered;
                $locked->is_active = false;
                $locked->maintenance_mode = false;
                $locked->deactivated_at = null;
            }

            $code = $this->generateCode();
            $expiresAt = now()->addMinutes(Setting::activationTtlMinutes());

            $locked->forceFill([
                'activation_secret_hash' => Hash::make($code),
                'activation_expires_at' => $expiresAt,
            ])->save();

            // Revoke existing tokens so only a successful exchange restores access.
            $locked->tokens()->delete();

            $this->audit->write(
                action: 'device.activated',
                actorType: 'user',
                actorId: $actor->id,
                entityType: 'device',
                entityId: $locked->id,
                before: $before,
                after: $this->snapshot($locked->fresh()),
                meta: [
                    'device_id' => $locked->device_id,
                    'expires_at' => $expiresAt->utc()->format('Y-m-d\TH:i:s\Z'),
                    'rotated' => $rotateSecret,
                ],
                request: $request,
            );

            return [
                'device' => $locked->fresh(['destination']),
                'activation_code' => $code,
                'expires_at' => $expiresAt,
            ];
        });
    }

    private function generateCode(): string
    {
        $chunks = [];

        for ($c = 0; $c < 2; $c++) {
            $chunk = '';
            for ($i = 0; $i < 4; $i++) {
                $chunk .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
            }
            $chunks[] = $chunk;
        }

        return implode('-', $chunks);
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
            'has_activation_secret' => $device->activation_secret_hash !== null,
            'activation_expires_at' => $device->activation_expires_at?->utc()->format('Y-m-d\TH:i:s\Z'),
        ];
    }
}
