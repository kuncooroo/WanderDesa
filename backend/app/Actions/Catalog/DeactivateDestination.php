<?php

namespace App\Actions\Catalog;

use App\Enums\PermissionName;
use App\Models\Destination;
use App\Models\User;
use App\Support\AuditWriter;
use App\Support\Authorization\Authorizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Soft business deactivation via is_active=false.
 * Does not hard-delete (ticket_types FK RESTRICT); catalog stays historically intact.
 */
final class DeactivateDestination
{
    public function __construct(
        private readonly AuditWriter $audit,
    ) {}

    public function handle(User $actor, Destination $destination, ?Request $request = null): Destination
    {
        Authorizer::authorize($actor, PermissionName::DestinationsManage);

        if (! $destination->is_active) {
            return $destination;
        }

        return DB::transaction(function () use ($actor, $destination, $request): Destination {
            $before = [
                'id' => $destination->id,
                'code' => $destination->code,
                'is_active' => $destination->is_active,
            ];

            $destination->forceFill(['is_active' => false])->save();

            $this->audit->write(
                action: 'destination.upsert',
                actorType: 'user',
                actorId: $actor->id,
                entityType: 'destination',
                entityId: $destination->id,
                before: $before,
                after: [
                    'id' => $destination->id,
                    'code' => $destination->code,
                    'is_active' => false,
                ],
                meta: ['operation' => 'deactivate'],
                request: $request,
            );

            return $destination->fresh();
        });
    }
}
