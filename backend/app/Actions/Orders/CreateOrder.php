<?php

namespace App\Actions\Orders;

use App\Actions\Pricing\QuoteOrderAction;
use App\Enums\Channel;
use App\Enums\IdempotencyScope;
use App\Enums\OrderStatus;
use App\Enums\PermissionName;
use App\Exceptions\DomainException;
use App\Models\Device;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Support\AuditWriter;
use App\Support\Authorization\Authorizer;
use App\Support\DeviceAbilities;
use App\Support\Idempotency\IdempotencyActor;
use App\Support\Idempotency\IdempotencyManager;
use App\Support\Idempotency\IdempotencyOutcome;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

final class CreateOrder
{
    public function __construct(
        private readonly QuoteOrderAction $quote,
        private readonly IdempotencyManager $idempotency,
        private readonly AuditWriter $audit,
    ) {}

    /**
     * @param  array{
     *     destination_id: int,
     *     visitor_id?: int|null,
     *     customer_note?: string|null,
     *     items: list<array{ticket_type_id: int, quantity: int, visit_date?: string|null}>
     * }  $payload
     */
    public function handle(
        Device|User $principal,
        array $payload,
        string $idempotencyKey,
        ?Request $request = null,
    ): Order {
        $this->authorizeCreate($principal);

        $canonical = $this->canonicalPayload($payload);

        $result = $this->idempotency->run(
            $idempotencyKey,
            IdempotencyScope::OrderCreate,
            IdempotencyActor::fromPrincipal($principal),
            $canonical,
            function () use ($principal, $canonical, $request): IdempotencyOutcome {
                if ($principal instanceof Device) {
                    $this->assertSellable($principal);
                }

                $order = $this->persist($principal, $canonical, $request);

                return new IdempotencyOutcome('order', (int) $order->id, 201, $order);
            },
        );

        if ($result->replayed) {
            return $this->loadOrder($result->resourceId);
        }

        /** @var Order $order */
        $order = $result->value;

        return $order->relationLoaded('items')
            ? $order
            : $this->loadOrder((int) $order->id);
    }

    private function authorizeCreate(Device|User $principal): void
    {
        if ($principal instanceof Device) {
            if (! $principal->tokenCan(DeviceAbilities::ORDERS_CREATE)) {
                throw new AuthorizationException('Missing device ability: orders:create');
            }

            return;
        }

        Authorizer::authorize($principal, PermissionName::OrdersCreate);
    }

    private function assertSellable(Device $device): void
    {
        if ($device->isSellable()) {
            return;
        }

        if ($device->isInMaintenance()) {
            throw new DomainException(
                'device.maintenance',
                'This device is in maintenance and cannot create orders.',
                403,
            );
        }

        throw new DomainException(
            'device.inactive',
            'This device cannot create orders.',
            403,
        );
    }

    /**
     * @param  array{
     *     destination_id: int,
     *     visitor_id?: int|null,
     *     customer_note?: string|null,
     *     items: list<array{ticket_type_id: int, quantity: int, visit_date?: string|null}>
     * }  $payload
     */
    private function persist(Device|User $principal, array $payload, ?Request $request): Order
    {
        $quote = $this->quote->handle($payload['destination_id'], $payload['items']);
        $channel = $principal instanceof Device ? Channel::Kiosk : Channel::Assisted;
        $ttlMinutes = Setting::orderPaymentTtlMinutes();

        return DB::transaction(function () use ($principal, $payload, $quote, $channel, $ttlMinutes, $request): Order {
            $order = $this->insertOrder($principal, $payload, $quote, $channel, $ttlMinutes);

            foreach ($quote['items'] as $line) {
                $order->items()->create([
                    'ticket_type_id' => $line['ticket_type_id'],
                    'ticket_type_code' => $line['ticket_type_code'],
                    'ticket_type_name' => $line['ticket_type_name'],
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'tax_amount' => $line['tax_amount'],
                    'service_fee_amount' => $line['service_fee_amount'],
                    'line_subtotal' => $line['line_subtotal'],
                    'line_tax_total' => $line['line_tax_total'],
                    'line_service_fee_total' => $line['line_service_fee_total'],
                    'line_grand_total' => $line['line_grand_total'],
                ]);
            }

            $order->load(['items', 'payments', 'tickets']);

            $this->audit->writeCritical(
                action: 'order.created',
                actorType: $principal instanceof Device ? 'device' : 'user',
                actorId: (int) $principal->getKey(),
                entityType: 'order',
                entityId: (int) $order->id,
                before: null,
                after: $this->snapshot($order),
                meta: [
                    'channel' => $channel->value,
                    'order_number' => $order->order_number,
                ],
                request: $request,
            );

            Log::info('order.created', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'channel' => $channel->value,
                'grand_total' => $order->grand_total,
            ]);

            return $order;
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $quote
     */
    private function insertOrder(
        Device|User $principal,
        array $payload,
        array $quote,
        Channel $channel,
        int $ttlMinutes,
    ): Order {
        $attributes = [
            'destination_id' => $payload['destination_id'],
            'channel' => $channel,
            'status' => OrderStatus::PendingPayment,
            'visitor_id' => $payload['visitor_id'] ?? null,
            'device_id' => $principal instanceof Device ? (int) $principal->getKey() : null,
            'created_by_user_id' => $principal instanceof User ? (int) $principal->getKey() : null,
            'currency' => $quote['currency'],
            'subtotal' => $quote['subtotal'],
            'discount_total' => $quote['discount_total'],
            'tax_total' => $quote['tax_total'],
            'service_fee_total' => $quote['service_fee_total'],
            'grand_total' => $quote['grand_total'],
            'customer_note' => $payload['customer_note'] ?? null,
            'expires_at' => now()->addMinutes($ttlMinutes),
        ];

        $attempts = 0;

        while (true) {
            try {
                $attributes['order_number'] = $this->nextOrderNumber();

                return Order::query()->create($attributes);
            } catch (UniqueConstraintViolationException $e) {
                $attempts++;

                if ($attempts >= 5 || ! str_contains(strtolower($e->getMessage()), 'order_number')) {
                    throw $e;
                }
            }
        }

        throw new RuntimeException('Unable to allocate a unique order number.');
    }

    private function nextOrderNumber(): string
    {
        return 'WD-'.Str::ulid()->toString();
    }

    /**
     * @param  array{
     *     destination_id: int,
     *     visitor_id?: int|null,
     *     customer_note?: string|null,
     *     items: list<array{ticket_type_id: int, quantity: int, visit_date?: string|null}>
     * }  $payload
     * @return array{
     *     destination_id: int,
     *     visitor_id: int|null,
     *     customer_note: string|null,
     *     items: list<array{ticket_type_id: int, quantity: int, visit_date: string|null}>
     * }
     */
    private function canonicalPayload(array $payload): array
    {
        $items = array_map(
            static function (array $item): array {
                return [
                    'ticket_type_id' => (int) $item['ticket_type_id'],
                    'quantity' => (int) $item['quantity'],
                    'visit_date' => $item['visit_date'] ?? null,
                ];
            },
            $payload['items'],
        );

        return [
            'destination_id' => (int) $payload['destination_id'],
            'visitor_id' => isset($payload['visitor_id']) ? (int) $payload['visitor_id'] : null,
            'customer_note' => $payload['customer_note'] ?? null,
            'items' => $items,
        ];
    }

    private function loadOrder(int $orderId): Order
    {
        return Order::query()
            ->with(['items', 'payments', 'tickets'])
            ->findOrFail($orderId);
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Order $order): array
    {
        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'destination_id' => $order->destination_id,
            'channel' => $order->channel instanceof Channel ? $order->channel->value : $order->channel,
            'status' => $order->status instanceof OrderStatus ? $order->status->value : $order->status,
            'subtotal' => $order->subtotal,
            'discount_total' => $order->discount_total,
            'tax_total' => $order->tax_total,
            'service_fee_total' => $order->service_fee_total,
            'grand_total' => $order->grand_total,
            'expires_at' => optional($order->expires_at)?->toIso8601String(),
            'item_count' => $order->items->count(),
        ];
    }
}
