<?php

namespace App\Actions\Payments;

use App\Enums\IdempotencyScope;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PermissionName;
use App\Enums\TicketStatus;
use App\Exceptions\DomainException;
use App\Integrations\Payments\PaymentGateway;
use App\Integrations\Payments\PaymentGatewayException;
use App\Models\Device;
use App\Models\Payment;
use App\Models\Ticket;
use App\Models\User;
use App\Services\CashierShifts\CashierShiftLedger;
use App\Services\Payments\PaymentStateService;
use App\Services\Tickets\TicketStateService;
use App\Support\AuditWriter;
use App\Support\Authorization\Authorizer;
use App\Support\Idempotency\IdempotencyActor;
use App\Support\Idempotency\IdempotencyManager;
use App\Support\Idempotency\IdempotencyOutcome;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Full MVP refund: server sets refund_amount = payment.amount; blocks if any ticket USED.
 */
final class RefundPayment
{
    public function __construct(
        private readonly IdempotencyManager $idempotency,
        private readonly PaymentStateService $paymentStates,
        private readonly TicketStateService $ticketStates,
        private readonly PaymentGateway $gateway,
        private readonly AuditWriter $audit,
        private readonly CashierShiftLedger $cashierShiftLedger,
    ) {}

    public function handle(
        User $principal,
        Payment $payment,
        string $idempotencyKey,
        string $reason,
        ?Request $request = null,
    ): Payment {
        Authorizer::authorize($principal, PermissionName::PaymentsRefund);

        $canonical = [
            'payment_id' => (int) $payment->id,
            'reason' => $reason,
        ];

        $result = $this->idempotency->run(
            $idempotencyKey,
            IdempotencyScope::PaymentRefund,
            IdempotencyActor::fromPrincipal($principal),
            $canonical,
            function () use ($principal, $payment, $reason, $request): IdempotencyOutcome {
                $refunded = $this->refund($principal, $payment, $reason, $request);

                return new IdempotencyOutcome('payment', (int) $refunded->id, 200, $refunded);
            },
        );

        if ($result->replayed) {
            return Payment::query()
                ->with(['order', 'tickets'])
                ->findOrFail($result->resourceId);
        }

        /** @var Payment $refunded */
        $refunded = $result->value;

        return $refunded->relationLoaded('order')
            ? $refunded
            : Payment::query()->with(['order', 'tickets'])->findOrFail((int) $refunded->id);
    }

    public function assertStaffPrincipal(Device|User|null $principal): User
    {
        if (! $principal instanceof User) {
            throw new AuthorizationException('Only staff may refund payments.');
        }

        return $principal;
    }

    private function refund(
        User $principal,
        Payment $payment,
        string $reason,
        ?Request $request,
    ): Payment {
        return DB::transaction(function () use ($principal, $payment, $reason, $request): Payment {
            /** @var Payment $locked */
            $locked = Payment::query()
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->firstOrFail();

            $order = $locked->order()->lockForUpdate()->firstOrFail();

            $tickets = Ticket::query()
                ->where('payment_id', $locked->id)
                ->lockForUpdate()
                ->get();

            if ($locked->status === PaymentStatus::Refunded
                && $order->status === OrderStatus::Refunded) {
                return $locked->load(['order', 'tickets']);
            }

            if (! $locked->isPaid() || $order->status !== OrderStatus::Paid) {
                throw new DomainException(
                    'order.state_conflict',
                    'Only paid payments on paid orders can be refunded.',
                    409,
                );
            }

            if ($tickets->contains(
                fn (Ticket $ticket): bool => $ticket->status === TicketStatus::Used,
            )) {
                throw new DomainException(
                    'refund.ineligible',
                    'Refund is not allowed when any related ticket has been used.',
                    422,
                );
            }

            $refundAmount = (int) $locked->amount;

            if ($locked->method instanceof PaymentMethod
                ? $locked->method->isProviderBacked()
                : $locked->isProviderBacked()) {
                try {
                    $this->gateway->refund($locked);
                } catch (PaymentGatewayException $e) {
                    Log::error('payment.refund_provider_failed', [
                        'payment_id' => $locked->id,
                        'order_id' => $order->id,
                        'failure_code' => $e->failureCode,
                        'message' => $e->getMessage(),
                    ]);

                    throw new DomainException(
                        $e->failureCode,
                        'Payment provider refund failed. Payment remains paid.',
                        502,
                    );
                }
            }

            $before = [
                'payment_status' => $locked->status->value,
                'order_status' => $order->status->value,
                'ticket_statuses' => $tickets->mapWithKeys(
                    static fn (Ticket $ticket): array => [
                        (string) $ticket->id => $ticket->status instanceof TicketStatus
                            ? $ticket->status->value
                            : (string) $ticket->status,
                    ],
                )->all(),
            ];

            $now = now();

            $this->paymentStates->transition($locked, PaymentStatus::Refunded);
            $meta = is_array($locked->metadata_json) ? $locked->metadata_json : [];
            $locked->forceFill([
                'refunded_at' => $locked->refunded_at ?? $now,
                'metadata_json' => array_merge($meta, [
                    'refund_amount' => $refundAmount,
                    'refund_reason' => $reason,
                ]),
            ])->save();

            if ($locked->isCash()) {
                $this->cashierShiftLedger->attachCashRefund($principal, $locked, $refundAmount);
                $locked->refresh();
            }

            $order->forceFill([
                'status' => OrderStatus::Refunded,
                'refunded_at' => $order->refunded_at ?? $now,
            ])->save();

            foreach ($tickets as $ticket) {
                if ($ticket->status === TicketStatus::Refunded) {
                    continue;
                }

                if (! $this->ticketStates->canTransition($ticket, TicketStatus::Refunded)) {
                    // Expired/cancelled (and similar) already block entry; leave unchanged.
                    continue;
                }

                $ticketBefore = [
                    'status' => $ticket->status instanceof TicketStatus
                        ? $ticket->status->value
                        : (string) $ticket->status,
                ];

                $this->ticketStates->transition($ticket, TicketStatus::Refunded);
                $ticket->forceFill([
                    'refunded_at' => $ticket->refunded_at ?? $now,
                ])->save();

                $this->audit->writeCritical(
                    action: 'ticket.refunded',
                    actorType: 'user',
                    actorId: (int) $principal->id,
                    entityType: 'ticket',
                    entityId: (int) $ticket->id,
                    before: $ticketBefore,
                    after: [
                        'status' => TicketStatus::Refunded->value,
                        'refunded_at' => optional($ticket->refunded_at)?->toIso8601String(),
                    ],
                    meta: [
                        'payment_id' => $locked->id,
                        'order_id' => $order->id,
                        'reason' => $reason,
                    ],
                    request: $request,
                );
            }

            $this->audit->writeCritical(
                action: 'refund.created',
                actorType: 'user',
                actorId: (int) $principal->id,
                entityType: 'payment',
                entityId: (int) $locked->id,
                before: $before,
                after: [
                    'payment_status' => PaymentStatus::Refunded->value,
                    'order_status' => OrderStatus::Refunded->value,
                    'refund_amount' => $refundAmount,
                ],
                meta: [
                    'source' => 'staff',
                    'order_id' => $order->id,
                    'payment_number' => $locked->payment_number,
                    'reason' => $reason,
                    'method' => $locked->method instanceof PaymentMethod
                        ? $locked->method->value
                        : (string) $locked->method,
                ],
                request: $request,
            );

            Log::info('refund.created', [
                'payment_id' => $locked->id,
                'order_id' => $order->id,
                'refund_amount' => $refundAmount,
                'actor_id' => $principal->id,
            ]);

            return $locked->load(['order', 'tickets']);
        });
    }
}
