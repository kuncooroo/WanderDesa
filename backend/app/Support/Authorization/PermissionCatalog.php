<?php

namespace App\Support\Authorization;

use App\Enums\PermissionName;

/**
 * Permission catalog helpers. Deny by default for unknown names.
 */
final class PermissionCatalog
{
    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return array_map(
            static fn (PermissionName $p) => $p->value,
            PermissionName::cases(),
        );
    }

    public static function isKnown(string $permission): bool
    {
        return PermissionName::tryFrom($permission) !== null;
    }

    public static function isNeverGrantedToHumans(string $permission): bool
    {
        $enum = PermissionName::tryFrom($permission);

        return $enum?->isNeverGrantedToHumans() ?? false;
    }
}
