<?php

namespace Tests\Concerns;

use App\Models\Destination;
use App\Models\Device;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\TicketType;
use App\Support\DeviceAbilities;
use App\Support\Idempotency\IdempotencyManager;

trait InteractsWithOrders
{
    /**
     * @return array{destination: Destination, adult: TicketType, child: TicketType}
     */
    protected function sellableCatalog(): array
    {
        $destination = Destination::factory()->create(['is_active' => true]);
        $adult = TicketType::factory()->create([
            'destination_id' => $destination->id,
            'code' => 'ADULT',
            'name' => 'Dewasa',
            'unit_price' => 50000,
            'tax_amount' => 1000,
            'service_fee_amount' => 500,
            'max_per_order' => 10,
            'is_active' => true,
        ]);
        $child = TicketType::factory()->create([
            'destination_id' => $destination->id,
            'code' => 'CHILD',
            'name' => 'Anak',
            'unit_price' => 25000,
            'tax_amount' => 0,
            'service_fee_amount' => 250,
            'max_per_order' => 10,
            'is_active' => true,
        ]);

        return compact('destination', 'adult', 'child');
    }

    /**
     * @return array{destination_id: int, items: list<array{ticket_type_id: int, quantity: int, visit_date?: string}>}
     */
    protected function twoAdultPayload(Destination $destination, TicketType $adult): array
    {
        return [
            'destination_id' => $destination->id,
            'items' => [
                ['ticket_type_id' => $adult->id, 'quantity' => 2, 'visit_date' => '2026-09-13'],
            ],
        ];
    }

    protected function activeDeviceToken(?Destination $destination = null, array $abilities = []): array
    {
        $device = Device::factory()->active()->create([
            'destination_id' => $destination?->id ?? Destination::factory(),
        ]);
        $token = $device->createToken(
            'kiosk',
            $abilities === [] ? DeviceAbilities::defaultKiosk() : $abilities,
        )->plainTextToken;

        return [$device, $token];
    }

    protected function withIdempotency(string $token, string $key): static
    {
        return $this->withToken($token)->withHeaders([
            IdempotencyManager::HEADER => $key,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function createPendingOrder(
        string $token,
        Destination $destination,
        TicketType $adult,
        string $key = 'order-1',
    ): array {
        $response = $this->withIdempotency($token, $key)
            ->postJson('/api/v1/orders', $this->twoAdultPayload($destination, $adult));
        $response->assertCreated();

        /** @var array<string, mixed> $data */
        $data = $response->json('data');

        return $data;
    }

    protected function attachOrderLine(
        Order $order,
        TicketType $type,
        int $quantity = 1,
    ): OrderItem {
        $lineSubtotal = $type->unit_price * $quantity;
        $lineTax = $type->tax_amount * $quantity;
        $lineFee = $type->service_fee_amount * $quantity;

        return OrderItem::factory()->create([
            'order_id' => $order->id,
            'ticket_type_id' => $type->id,
            'ticket_type_code' => $type->code,
            'ticket_type_name' => $type->name,
            'quantity' => $quantity,
            'unit_price' => $type->unit_price,
            'tax_amount' => $type->tax_amount,
            'service_fee_amount' => $type->service_fee_amount,
            'line_subtotal' => $lineSubtotal,
            'line_tax_total' => $lineTax,
            'line_service_fee_total' => $lineFee,
            'line_grand_total' => $lineSubtotal + $lineTax + $lineFee,
        ]);
    }
}
