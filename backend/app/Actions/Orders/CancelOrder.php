<?php

namespace App\Actions\Orders;

use App\Actions\Payments\ApplyProviderPaymentEvent;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PermissionName;
use App\Exceptions\DomainException;
use App\Integrations\Payments\CancelsProviderCharges;
use App\Integrations\Payments\PaymentGateway;
use App\Integrations\Payments\PaymentGatewayException;
use App\Integrations\Payments\ProviderCancelOutcome;
use App\Integrations\Payments\RemotePaymentStatus;
use App\Models\Device;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Support\AuditWriter;
use App\Support\Authorization\Authorizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class CancelOrder
{
    public function __construct(
        private readonly AuditWriter $audit,
        private readonly PaymentGateway $gateway,
        private readonly ApplyProviderPaymentEvent $applyProviderEvent,
    ) {}

    public function handle(
        Device|User $principal,
        Order $order,
        ?string $reason = null,
        ?Request $request = null,
    ): Order {
        $this->authorizeCancel($principal, $order);
        $order->loadMissing('payments');
        $this->releaseProviderCharges($order);

        return DB::transaction(function () use ($principal, $order, $reason, $request): Order {
            /** @var Order $locked */
            $locked = Order::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            $locked->load(['payments', 'items', 'tickets']);

            if (! $locked->isPendingPayment()) {
                throw new DomainException(
                    'order.state_conflict',
                    'Only unpaid pending orders can be cancelled.',
                    409,
                );
            }

            if ($locked->payments->contains(
                fn (Payment $payment): bool => $payment->status === PaymentStatus::Paid,
            )) {
                throw new DomainException(
                    'order.state_conflict',
                    'Paid orders cannot be cancelled. Use the refund flow.',
                    409,
                );
            }

            $before = $this->snapshot($locked);
            $now = now();

            foreach ($locked->payments as $payment) {
                if (! in_array($payment->status, [PaymentStatus::Pending, PaymentStatus::Processing], true)) {
                    continue;
                }

                $paymentBefore = [
                    'id' => $payment->id,
                    'status' => $payment->status->value,
                ];

                $payment->forceFill([
                    'status' => PaymentStatus::Cancelled,
                    'cancelled_at' => $now,
                ])->save();

                $this->audit->writeCritical(
                    action: 'payment.cancelled',
                    actorType: $principal instanceof Device ? 'device' : 'user',
                    actorId: (int) $principal->getKey(),
                    entityType: 'payment',
                    entityId: (int) $payment->id,
                    before: $paymentBefore,
                    after: [
                        'id' => $payment->id,
                        'status' => PaymentStatus::Cancelled->value,
                    ],
                    meta: [
                        'order_id' => $locked->id,
                        'reason' => $reason,
                    ],
                    request: $request,
                );
            }

            $locked->forceFill([
                'status' => OrderStatus::Cancelled,
                'cancelled_at' => $now,
            ])->save();

            $locked->load(['items', 'payments', 'tickets']);

            $this->audit->writeCritical(
                action: 'order.cancelled',
                actorType: $principal instanceof Device ? 'device' : 'user',
                actorId: (int) $principal->getKey(),
                entityType: 'order',
                entityId: (int) $locked->id,
                before: $before,
                after: $this->snapshot($locked),
                meta: [
                    'reason' => $reason,
                    'order_number' => $locked->order_number,
                ],
                request: $request,
            );

            Log::info('order.cancelled', [
                'order_id' => $locked->id,
                'order_number' => $locked->order_number,
            ]);

            return $locked;
        });
    }

    private function releaseProviderCharges(Order $order): void
    {
        if (! $this->gateway instanceof CancelsProviderCharges) {
            return;
        }

        foreach ($order->payments as $payment) {
            if (! $payment->isOpen() || ! $payment->isProviderBacked()) {
                continue;
            }

            try {
                $outcome = $this->gateway->cancelOpenCharge($payment);
            } catch (PaymentGatewayException $e) {
                throw new DomainException(
                    $e->failureCode,
                    'Payment provider could not cancel the charge. The order was not cancelled.',
                    502,
                );
            }

            if ($outcome !== ProviderCancelOutcome::AlreadySettled) {
                continue;
            }

            $fresh = $payment->fresh() ?? $payment;
            $this->applyProviderEvent->handle($fresh, RemotePaymentStatus::Paid, source: 'reconcile');
            $fresh->refresh();

            if (! $fresh->isPaid()) {
                throw new DomainException(
                    'upstream.payment_provider_unavailable',
                    'The provider settled this charge, but the payment could not be recorded. The order was not cancelled.',
                    502,
                );
            }

            throw new DomainException(
                'order.state_conflict',
                'This payment has already settled and cannot be cancelled.',
                409,
            );
        }
    }

    private function authorizeCancel(Device|User $principal, Order $order): void
    {
        if ($principal instanceof Device) {
            if ((int) $order->device_id !== (int) $principal->getKey()) {
                throw new DomainException(
                    'resource.not_found',
                    'Order not found.',
                    404,
                );
            }

            return;
        }

        Authorizer::authorize($principal, PermissionName::OrdersCancel);
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Order $order): array
    {
        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status instanceof OrderStatus ? $order->status->value : $order->status,
            'cancelled_at' => optional($order->cancelled_at)?->toIso8601String(),
        ];
    }
}
