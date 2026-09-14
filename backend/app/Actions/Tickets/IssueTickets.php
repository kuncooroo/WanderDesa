<?php

namespace App\Actions\Tickets;

use App\Enums\OrderStatus;
use App\Enums\TicketStatus;
use App\Exceptions\DomainException;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Services\Qr\QrPayloadGenerator;
use App\Services\Tickets\TicketStateService;
use App\Services\Tickets\TicketValidityCalculator;
use App\Support\AuditWriter;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Mint tickets only after an authoritative PAID payment. Idempotent per order.
 */
final class IssueTickets
{
    public function __construct(
        private readonly QrPayloadGenerator $qr,
        private readonly TicketValidityCalculator $validity,
        private readonly TicketStateService $states,
        private readonly AuditWriter $audit,
    ) {}

    /**
     * @return Collection<int, Ticket>
     */
    public function handle(Payment $payment): Collection
    {
        return DB::transaction(function () use ($payment) {
            /** @var Payment $lockedPayment */
            $lockedPayment = Payment::query()
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->firstOrFail();

            /** @var Order $order */
            $order = Order::query()
                ->whereKey($lockedPayment->order_id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedPayment->isPaid() || $order->status !== OrderStatus::Paid) {
                throw new DomainException(
                    'order.state_conflict',
                    'Tickets can only be issued for a paid order.',
                    409,
                );
            }

            $order->load([
                'items.ticketType' => fn ($query) => $query->withTrashed(),
                'destination',
                'tickets',
            ]);

            $expected = (int) $order->items->sum('quantity');
            $existing = $order->tickets;

            if ($existing->count() === $expected) {
                return $existing->values();
            }

            if ($existing->isNotEmpty()) {
                throw new DomainException(
                    'ticket.issue_incomplete',
                    'This order has a partial ticket set and cannot be completed automatically.',
                    409,
                );
            }

            $issued = collect();

            foreach ($order->items as $item) {
                $type = $this->ticketTypeFor($item);

                for ($i = 0; $i < (int) $item->quantity; $i++) {
                    $issued->push($this->createTicket($order, $item, $lockedPayment, $type));
                }
            }

            $order->setRelation('tickets', $issued);
            $lockedPayment->setRelation('tickets', $issued);

            return $issued->values();
        });
    }

    private function ticketTypeFor(OrderItem $item): TicketType
    {
        $type = $item->ticketType;

        if ($type === null) {
            $type = TicketType::withTrashed()->find($item->ticket_type_id);
        }

        if ($type === null) {
            throw new DomainException(
                'ticket.type_missing',
                'Ticket type for this order line is missing.',
                409,
            );
        }

        return $type;
    }

    private function createTicket(
        Order $order,
        OrderItem $item,
        Payment $payment,
        TicketType $type,
    ): Ticket {
        $destination = $order->destination;
        $issuedAt = now();
        $window = $this->validity->window($type, $destination, $issuedAt);
        $status = $this->states->initialStatus($window['start'], $window['end'], $issuedAt);

        $attempts = 0;

        while (true) {
            $code = $this->nextTicketCode();
            $qr = $this->qr->generate($code);

            try {
                $ticket = Ticket::query()->create([
                    'ticket_code' => $code,
                    'order_id' => $order->id,
                    'order_item_id' => $item->id,
                    'destination_id' => $order->destination_id,
                    'ticket_type_id' => $type->id,
                    'payment_id' => $payment->id,
                    'channel' => $order->channel,
                    'status' => $status,
                    'currency' => $order->currency,
                    'unit_price_snapshot' => $item->unit_price,
                    'tax_snapshot' => $item->tax_amount,
                    'service_fee_snapshot' => $item->service_fee_amount,
                    'valid_start_at' => $window['start'],
                    'valid_end_at' => $window['end'],
                    'issued_at' => $issuedAt,
                    'activated_at' => $status === TicketStatus::Active ? $issuedAt : null,
                    'expired_at' => $status === TicketStatus::Expired ? $issuedAt : null,
                    'issued_by_user_id' => $order->created_by_user_id,
                    'issued_by_device_id' => $order->device_id,
                    'qr_payload' => $qr->payload,
                    'qr_payload_hash' => $qr->hash,
                    'qr_version' => $qr->version,
                    'qr_secret_hint' => $qr->secretHint,
                ]);
            } catch (UniqueConstraintViolationException $e) {
                $attempts++;

                if ($attempts >= 8) {
                    throw $e;
                }

                continue;
            } catch (QueryException $e) {
                if (! str_contains(strtolower($e->getMessage()), 'unique')) {
                    throw $e;
                }

                $attempts++;

                if ($attempts >= 8) {
                    throw $e;
                }

                continue;
            }

            $this->audit->writeCritical(
                action: 'ticket.issued',
                actorType: 'system',
                actorId: null,
                entityType: 'ticket',
                entityId: (int) $ticket->id,
                before: null,
                after: [
                    'ticket_code' => $ticket->ticket_code,
                    'status' => $status->value,
                    'order_id' => $order->id,
                    'payment_id' => $payment->id,
                ],
                meta: [
                    'order_number' => $order->order_number,
                    'payment_number' => $payment->payment_number,
                    'ticket_type_id' => $type->id,
                ],
            );

            Log::info('ticket.issued', [
                'ticket_id' => $ticket->id,
                'ticket_code' => $ticket->ticket_code,
                'order_id' => $order->id,
                'payment_id' => $payment->id,
                'status' => $status->value,
            ]);

            return $ticket;
        }

        throw new RuntimeException('Unable to allocate a unique ticket code.');
    }

    private function nextTicketCode(): string
    {
        return 'TCK-'.Str::ulid()->toString();
    }
}
