<?php

namespace App\Actions\Catalog;

use App\Enums\PermissionName;
use App\Models\Destination;
use App\Models\User;
use App\Support\AuditWriter;
use App\Support\Authorization\Authorizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class UpdateDestination
{
    public function __construct(
        private readonly AuditWriter $audit,
    ) {}

    /**
     * @param  array{
     *     code?: string,
     *     name?: string,
     *     timezone?: string,
     *     description?: string|null,
     *     is_active?: bool
     * }  $data
     */
    public function handle(User $actor, Destination $destination, array $data, ?Request $request = null): Destination
    {
        Authorizer::authorize($actor, PermissionName::DestinationsManage);

        return DB::transaction(function () use ($actor, $destination, $data, $request): Destination {
            $before = $this->snapshot($destination);

            $destination->fill($data);
            $destination->save();

            $this->audit->write(
                action: 'destination.upsert',
                actorType: 'user',
                actorId: $actor->id,
                entityType: 'destination',
                entityId: $destination->id,
                before: $before,
                after: $this->snapshot($destination->fresh()),
                meta: ['operation' => 'update'],
                request: $request,
            );

            return $destination->fresh();
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Destination $destination): array
    {
        return [
            'id' => $destination->id,
            'code' => $destination->code,
            'name' => $destination->name,
            'description' => $destination->description,
            'timezone' => $destination->timezone,
            'is_active' => $destination->is_active,
        ];
    }
}
