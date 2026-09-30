<?php

namespace App\Actions\Payments;

use App\Enums\IdempotencyScope;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PermissionName;
use App\Exceptions\DomainException;
use App\Integrations\Payments\PaymentGateway;
use App\Integrations\Payments\PaymentGatewayException;
use App\Models\Device;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\CashierShifts\CashierShiftLedger;
use App\Services\Payments\PaymentStateService;
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

final class InitiatePayment
{
    public function __construct(
        private readonly IdempotencyManager $idempotency,
        private readonly PaymentStateService $states,
        private readonly PaymentGateway $gateway,
        private readonly AuditWriter $audit,
        private readonly CashierShiftLedger $shifts,
    ) {}

    /**
     * @return array{payment: Payment, next_action: array<string, mixed>|null, status: int}
     */
    public function handle(
        Device|User $principal,
        Order $order,
        PaymentMethod $method,
        string $idempotencyKey,
        ?Request $request = null,
    ): array {
        $this->authorizeInitiate($principal, $method);

        if (config('payments.gateway') === 'midtrans'
            && in_array($method, [PaymentMethod::Debit, PaymentMethod::EWallet], true)) {
            throw new DomainException(
                'payment.method_unavailable',
                'Metode ini belum tersedia. Gunakan QRIS.',
            );
        }

        $canonical = [
            'order_id' => (int) $order->id,
            'method' => $method->value,
        ];

        $result = $this->idempotency->run(
            $idempotencyKey,
            IdempotencyScope::PaymentInitiate,
            IdempotencyActor::fromPrincipal($principal),
            $canonical,
            function () use ($principal, $order, $method, $request): IdempotencyOutcome {
                if ($principal instanceof Device) {
                    $this->assertSellable($principal);
                }

                $session = $this->persist($principal, $order, $method, $request);
                $payment = $session['payment'];
                $status = $payment->status === PaymentStatus::Failed ? 502 : 201;

                return new IdempotencyOutcome('payment', (int) $payment->id, $status, $session);
            },
        );

        if ($result->replayed) {
            $payment = $this->loadPayment($result->resourceId);

            return [
                'payment' => $payment,
                'next_action' => $payment->nextAction(),
                'status' => $result->responseCode,
            ];
        }

        /** @var array{payment: Payment, next_action: array<string, mixed>|null} $session */
        $session = $result->value;

        return [
            'payment' => $session['payment'],
            'next_action' => $session['next_action'],
            'status' => $result->responseCode,
        ];
    }

    private function authorizeInitiate(Device|User $principal, PaymentMethod $method): void
    {
        if ($principal instanceof Device) {
            if ($method->isCash()) {
                throw new AuthorizationException('Devices cannot initiate cash payments.');
            }

            if (! $principal->tokenCan(DeviceAbilities::PAYMENTS_INITIATE)) {
                throw new AuthorizationException('Missing device ability: payments:initiate');
            }

            return;
        }

        Authorizer::authorize($principal, PermissionName::PaymentsCreate);
    }

    private function assertSellable(Device $device): void
    {
        if ($device->isSellable()) {
            return;
        }

        if ($device->isInMaintenance()) {
            throw new DomainException(
                'device.maintenance',
                'This device is in maintenance and cannot initiate payments.',
                403,
            );
        }

        throw new DomainException(
            'device.inactive',
            'This device cannot initiate payments.',
            403,
        );
    }

    private function assertDeviceOwnsOrder(Device|User $principal, Order $order): void
    {
        if (! $principal instanceof Device) {
            return;
        }

        if ((int) $order->device_id !== (int) $principal->getKey()) {
            throw new DomainException(
                'resource.not_found',
                'Order not found.',
                404,
            );
        }
    }

    /**
     * @return array{payment: Payment, next_action: array<string, mixed>|null}
     */
    private function persist(
        Device|User $principal,
        Order $order,
        PaymentMethod $method,
        ?Request $request,
    ): array {
        $payment = DB::transaction(function () use ($principal, $order, $method): Payment {
            /** @var Order $locked */
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $locked->load('payments');

            $this->assertDeviceOwnsOrder($principal, $locked);
            $this->assertOrderPayable($locked);

            return $this->insertPayment($principal, $locked, $method);
        });

        $nextAction = null;

        if ($method->isProviderBacked()) {
            try {
                $initiation = $this->gateway->initiate($payment, $order->fresh() ?? $order);
                $this->states->transition($payment, PaymentStatus::Processing);
                $payment->forceFill([
                    'provider' => $initiation->provider,
                    'provider_payment_id' => $initiation->providerPaymentId,
                    'provider_reference' => $initiation->providerReference,
                    'metadata_json' => array_filter([
                        'next_action' => $initiation->nextAction,
                        'instrument' => $method->value,
                    ], fn (mixed $value): bool => $value !== null),
                ])->save();
                $nextAction = $initiation->nextAction;
            } catch (PaymentGatewayException $e) {
                $this->markFailed($payment, $e->failureCode, $e->getMessage(), $principal, $request);
            }
        } else {
            $this->states->transition($payment, PaymentStatus::Processing);
            $payment->save();
        }

        $payment->load(['order', 'tickets']);

        if ($payment->status !== PaymentStatus::Failed && $principal instanceof User && $method === PaymentMethod::Qris) {
            $this->shifts->attachQrisSale($principal, $payment);
        }

        if ($payment->status !== PaymentStatus::Failed) {
            $this->audit->writeCritical(
                action: 'payment.initiated',
                actorType: $principal instanceof Device ? 'device' : 'user',
                actorId: (int) $principal->getKey(),
                entityType: 'payment',
                entityId: (int) $payment->id,
                before: null,
                after: [
                    'payment_number' => $payment->payment_number,
                    'status' => $payment->status->value,
                    'method' => $method->value,
                    'amount' => $payment->amount,
                ],
                meta: [
                    'order_id' => $order->id,
                ],
                request: $request,
            );

            Log::info('payment.initiated', [
                'payment_id' => $payment->id,
                'order_id' => $order->id,
                'method' => $method->value,
                'amount' => $payment->amount,
            ]);
        }

        return [
            'payment' => $payment,
            'next_action' => $payment->status === PaymentStatus::Failed ? null : $nextAction,
        ];
    }

    private function assertOrderPayable(Order $order): void
    {
        if ($order->hasExpiredUnpaid()) {
            throw new DomainException(
                'order.state_conflict',
                'This order has expired and cannot accept payment.',
                409,
            );
        }

        if (! $order->isPendingPayment()) {
            throw new DomainException(
                'order.state_conflict',
                'Payments can only be initiated for pending orders.',
                409,
            );
        }

        if ($order->payments->contains(
            fn (Payment $payment): bool => $payment->status === PaymentStatus::Paid,
        )) {
            throw new DomainException(
                'order.state_conflict',
                'This order already has a paid payment.',
                409,
            );
        }

        if ($order->payments->contains(
            fn (Payment $payment): bool => $payment->isOpen(),
        )) {
            throw new DomainException(
                'order.state_conflict',
                'An open payment already exists for this order.',
                409,
            );
        }
    }

    private function insertPayment(Device|User $principal, Order $order, PaymentMethod $method): Payment
    {
        $attributes = [
            'order_id' => $order->id,
            'status' => PaymentStatus::Pending,
            'method' => $method,
            'amount' => (int) $order->grand_total,
            'currency' => $order->currency ?? 'IDR',
            'device_id' => $principal instanceof Device
                ? (int) $principal->getKey()
                : $order->device_id,
            'collected_by_user_id' => $principal instanceof User ? (int) $principal->getKey() : null,
        ];

        $attempts = 0;

        while (true) {
            try {
                $attributes['payment_number'] = $this->nextPaymentNumber();

                return Payment::query()->create($attributes);
            } catch (UniqueConstraintViolationException $e) {
                $attempts++;

                if ($attempts >= 5 || ! str_contains(strtolower($e->getMessage()), 'payment_number')) {
                    throw $e;
                }
            }
        }

        throw new RuntimeException('Unable to allocate a unique payment number.');
    }

    private function markFailed(
        Payment $payment,
        string $code,
        string $message,
        Device|User $principal,
        ?Request $request,
    ): void {
        $this->states->transition($payment, PaymentStatus::Failed);
        $payment->forceFill([
            'failed_at' => now(),
            'failure_code' => $code,
            'failure_message' => $message,
        ])->save();

        $this->audit->writeCritical(
            action: 'payment.failed',
            actorType: $principal instanceof Device ? 'device' : 'user',
            actorId: (int) $principal->getKey(),
            entityType: 'payment',
            entityId: (int) $payment->id,
            before: ['status' => PaymentStatus::Pending->value],
            after: ['status' => PaymentStatus::Failed->value],
            meta: [
                'failure_code' => $code,
                'order_id' => $payment->order_id,
            ],
            request: $request,
        );

        Log::warning('payment.failed', [
            'payment_id' => $payment->id,
            'order_id' => $payment->order_id,
            'failure_code' => $code,
        ]);
    }

    private function nextPaymentNumber(): string
    {
        return 'PAY-'.Str::ulid()->toString();
    }

    private function loadPayment(int $paymentId): Payment
    {
        return Payment::query()
            ->with(['order', 'tickets'])
            ->findOrFail($paymentId);
    }
}
