<?php

namespace App\Actions\Settings;

use App\Enums\PermissionName;
use App\Exceptions\DomainException;
use App\Models\Setting;
use App\Models\User;
use App\Support\AuditWriter;
use App\Support\Authorization\Authorizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Upsert allowlisted system settings (docs/09 B21). Sensitive TTL/payment keys.
 */
final class UpdateSystemSettings
{
    /**
     * @var array<string, array{type: string, description: string, min: int, max: int}>
     */
    public const DEFINITIONS = [
        Setting::ORDER_PAYMENT_TTL_MINUTES => [
            'type' => 'int',
            'description' => 'Menit hingga pesanan unpaid kedaluwarsa (default 15).',
            'min' => 1,
            'max' => 1440,
        ],
        Setting::KIOSK_HEARTBEAT_STALE_SECONDS => [
            'type' => 'int',
            'description' => 'Detik tanpa heartbeat sebelum kiosk dianggap offline (default 120).',
            'min' => 30,
            'max' => 3600,
        ],
        Setting::KIOSK_ACTIVATION_TTL_MINUTES => [
            'type' => 'int',
            'description' => 'Menit hingga kode aktivasi kiosk kedaluwarsa (default 60).',
            'min' => 5,
            'max' => 1440,
        ],
    ];

    public function __construct(
        private readonly AuditWriter $audit,
    ) {}

    /**
     * @param  array<string, int|string>  $values  key => value
     * @return list<Setting>
     */
    public function handle(User $actor, array $values, ?Request $request = null): array
    {
        Authorizer::authorize($actor, PermissionName::SettingsUpdate);

        foreach (array_keys($values) as $key) {
            if (! array_key_exists($key, self::DEFINITIONS)) {
                throw new DomainException(
                    'settings.unknown_key',
                    "Unknown or disallowed setting key: {$key}",
                    422,
                );
            }
        }

        return DB::transaction(function () use ($actor, $values, $request): array {
            $updated = [];
            $beforeBag = [];
            $afterBag = [];

            foreach ($values as $key => $raw) {
                $definition = self::DEFINITIONS[$key];
                $intValue = (int) $raw;

                if ($intValue < $definition['min'] || $intValue > $definition['max']) {
                    throw new DomainException(
                        'settings.out_of_range',
                        "{$key} must be between {$definition['min']} and {$definition['max']}.",
                        422,
                        [['field' => $key, 'code' => 'out_of_range']],
                    );
                }

                /** @var Setting $row */
                $row = Setting::query()->firstOrNew(['key' => $key]);
                $beforeBag[$key] = $row->exists ? $row->value : null;

                $row->fill([
                    'value' => (string) $intValue,
                    'type' => $definition['type'],
                    'description' => $definition['description'],
                    'updated_by_user_id' => $actor->id,
                ]);
                $row->save();

                $afterBag[$key] = $row->value;
                $updated[] = $row->fresh();
            }

            $this->audit->write(
                action: 'settings.updated',
                actorType: 'user',
                actorId: $actor->id,
                entityType: 'settings',
                entityId: null,
                before: $beforeBag,
                after: $afterBag,
                meta: ['keys' => array_keys($values)],
                request: $request,
            );

            return $updated;
        });
    }
}
