<?php

namespace App\Actions\Catalog\TicketTypes;

use App\Enums\PermissionName;
use App\Models\TicketType;
use App\Models\User;
use App\Support\AuditWriter;
use App\Support\Authorization\Authorizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Soft business deactivation via is_active=false.
 * Does not hard-delete (order_items/tickets FK RESTRICT); catalog stays historically intact.
 */
final class DeactivateTicketType
{
    public function __construct(
        private readonly AuditWriter $audit,
    ) {}

    public function handle(User $actor, TicketType $ticketType, ?Request $request = null): TicketType
    {
        Authorizer::authorize($actor, PermissionName::TicketTypesManage);

        if (! $ticketType->is_active) {
            return $ticketType;
        }

        return DB::transaction(function () use ($actor, $ticketType, $request): TicketType {
            $before = [
                'id' => $ticketType->id,
                'destination_id' => $ticketType->destination_id,
                'code' => $ticketType->code,
                'is_active' => $ticketType->is_active,
                'unit_price' => $ticketType->unit_price,
                'tax_amount' => $ticketType->tax_amount,
                'service_fee_amount' => $ticketType->service_fee_amount,
            ];

            $ticketType->forceFill(['is_active' => false])->save();

            $this->audit->write(
                action: 'ticket_type.upsert',
                actorType: 'user',
                actorId: $actor->id,
                entityType: 'ticket_type',
                entityId: $ticketType->id,
                before: $before,
                after: [
                    'id' => $ticketType->id,
                    'destination_id' => $ticketType->destination_id,
                    'code' => $ticketType->code,
                    'is_active' => false,
                    'unit_price' => $ticketType->unit_price,
                    'tax_amount' => $ticketType->tax_amount,
                    'service_fee_amount' => $ticketType->service_fee_amount,
                ],
                meta: [
                    'operation' => 'deactivate',
                    'price_changed' => false,
                ],
                request: $request,
            );

            return $ticketType->fresh();
        });
    }
}
