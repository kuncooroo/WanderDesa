<?php

namespace App\Actions\Catalog\TicketTypes;

use App\Enums\PermissionName;
use App\Enums\ValidityType;
use App\Models\TicketType;
use App\Models\User;
use App\Support\AuditWriter;
use App\Support\Authorization\Authorizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class UpdateTicketType
{
    public function __construct(
        private readonly AuditWriter $audit,
    ) {}

    /**
     * @param  array{
     *     destination_id?: int,
     *     code?: string,
     *     name?: string,
     *     description?: string|null,
     *     currency?: string,
     *     unit_price?: int,
     *     tax_amount?: int,
     *     service_fee_amount?: int,
     *     validity_type?: string|ValidityType,
     *     validity_days?: int|null,
     *     valid_from_time?: string|null,
     *     valid_until_time?: string|null,
     *     max_per_order?: int,
     *     is_active?: bool
     * }  $data
     */
    public function handle(User $actor, TicketType $ticketType, array $data, ?Request $request = null): TicketType
    {
        Authorizer::authorize($actor, PermissionName::TicketTypesManage);

        return DB::transaction(function () use ($actor, $ticketType, $data, $request): TicketType {
            $before = $this->snapshot($ticketType);
            $priceChanged = $this->priceChanged($before, $data);

            $ticketType->fill($data);
            $ticketType->save();

            $this->audit->write(
                action: 'ticket_type.upsert',
                actorType: 'user',
                actorId: $actor->id,
                entityType: 'ticket_type',
                entityId: $ticketType->id,
                before: $before,
                after: $this->snapshot($ticketType->fresh()),
                meta: [
                    'operation' => 'update',
                    'price_changed' => $priceChanged,
                ],
                request: $request,
            );

            return $ticketType->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $data
     */
    private function priceChanged(array $before, array $data): bool
    {
        foreach (['unit_price', 'tax_amount', 'service_fee_amount'] as $field) {
            if (array_key_exists($field, $data) && (int) $data[$field] !== (int) $before[$field]) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(TicketType $ticketType): array
    {
        return [
            'id' => $ticketType->id,
            'destination_id' => $ticketType->destination_id,
            'code' => $ticketType->code,
            'name' => $ticketType->name,
            'description' => $ticketType->description,
            'currency' => $ticketType->currency,
            'unit_price' => $ticketType->unit_price,
            'tax_amount' => $ticketType->tax_amount,
            'service_fee_amount' => $ticketType->service_fee_amount,
            'validity_type' => $ticketType->validity_type instanceof ValidityType
                ? $ticketType->validity_type->value
                : $ticketType->validity_type,
            'validity_days' => $ticketType->validity_days,
            'valid_from_time' => $ticketType->valid_from_time,
            'valid_until_time' => $ticketType->valid_until_time,
            'max_per_order' => $ticketType->max_per_order,
            'is_active' => $ticketType->is_active,
        ];
    }
}
