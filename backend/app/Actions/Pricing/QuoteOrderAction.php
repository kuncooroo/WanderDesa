<?php

namespace App\Actions\Pricing;

use App\Models\Destination;
use App\Models\TicketType;
use App\Services\Pricing\PricingService;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class QuoteOrderAction
{
    public function __construct(
        private readonly PricingService $pricing,
    ) {}

    /**
     * @param  list<array{ticket_type_id: int, quantity: int, visit_date?: string|null}>  $items
     * @return array{
     *     currency: string,
     *     items: list<array<string, mixed>>,
     *     subtotal: int,
     *     discount_total: int,
     *     tax_total: int,
     *     service_fee_total: int,
     *     grand_total: int
     * }
     */
    public function handle(int $destinationId, array $items): array
    {
        $destination = Destination::query()->find($destinationId);

        if ($destination === null) {
            throw ValidationException::withMessages([
                'destination_id' => ['The selected destination is invalid.'],
            ]);
        }

        if (! $destination->is_active) {
            throw ValidationException::withMessages([
                'destination_id' => ['The selected destination is not active.'],
            ]);
        }

        $typeIds = collect($items)->pluck('ticket_type_id')->unique()->values()->all();

        /** @var Collection<int, TicketType> $types */
        $types = TicketType::query()
            ->whereIn('id', $typeIds)
            ->get()
            ->keyBy('id');

        $lines = [];
        $quantityByType = [];

        foreach ($items as $index => $item) {
            $fieldPrefix = "items.{$index}";
            $ticketTypeId = (int) $item['ticket_type_id'];
            $quantity = (int) $item['quantity'];
            $ticketType = $types->get($ticketTypeId);

            if ($ticketType === null) {
                throw ValidationException::withMessages([
                    "{$fieldPrefix}.ticket_type_id" => ['The selected ticket type is invalid.'],
                ]);
            }

            if (! $ticketType->is_active) {
                throw ValidationException::withMessages([
                    "{$fieldPrefix}.ticket_type_id" => ['The selected ticket type is not active.'],
                ]);
            }

            if ((int) $ticketType->destination_id !== (int) $destination->id) {
                throw ValidationException::withMessages([
                    "{$fieldPrefix}.ticket_type_id" => ['Ticket types must belong to the selected destination.'],
                ]);
            }

            if ($ticketType->currency !== 'IDR') {
                throw ValidationException::withMessages([
                    "{$fieldPrefix}.ticket_type_id" => ['Only IDR ticket types are supported.'],
                ]);
            }

            $quantityByType[$ticketTypeId] = ($quantityByType[$ticketTypeId] ?? 0) + $quantity;

            $lines[] = [
                'ticket_type' => $ticketType,
                'quantity' => $quantity,
                'visit_date' => $item['visit_date'] ?? null,
            ];
        }

        $errors = [];
        foreach ($quantityByType as $ticketTypeId => $totalQty) {
            $ticketType = $types->get($ticketTypeId);
            if ($ticketType !== null && $totalQty > (int) $ticketType->max_per_order) {
                $errors["ticket_type_id.{$ticketTypeId}"] = [
                    "Quantity exceeds max_per_order of {$ticketType->max_per_order} for this ticket type.",
                ];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $this->pricing->quote($lines);
    }
}
