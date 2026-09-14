<?php

namespace App\Livewire\AssistedSale;

use App\Actions\Orders\CreateOrder;
use App\Actions\Payments\ConfirmCashPayment;
use App\Actions\Payments\InitiatePayment;
use App\Actions\Pricing\QuoteOrderAction;
use App\Actions\Tickets\RecordTicketPrintAttempt;
use App\Enums\Channel;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PrintAttemptResult;
use App\Exceptions\DomainException;
use App\Models\Destination;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use App\Services\CashierShifts\CashierShiftLedger;
use App\Support\Tickets\TicketPrintPayload;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Assisted POS wizard — displays server quotes/totals only; Actions own commerce.
 */
#[Layout('layouts.app')]
#[Title('Penjualan dibantu')]
class SaleWizard extends Component
{
    use AuthorizesRequests;

    public string $step = 'catalog';

    public ?int $destinationId = null;

    /** @var array<string, int> ticket_type_id => quantity */
    public array $quantities = [];

    public string $customerNote = '';

    public string $visitDate = '';

    /** @var array<string, mixed>|null */
    public ?array $quote = null;

    public ?int $orderId = null;

    public ?int $paymentId = null;

    public string $orderNumber = '';

    public string $paymentStatus = '';

    public int $paidAmount = 0;

    /** @var list<array<string, mixed>> */
    public array $tickets = [];

    /** @var list<array<string, mixed>> */
    public array $printPayloads = [];

    public bool $showCashModal = false;

    public bool $hasOpenShift = false;

    public string $errorMessage = '';

    /** Assisted collection instrument: cash|qris|debit|e_wallet */
    public string $paymentMethod = 'cash';

    /** @var array<string, mixed>|null */
    public ?array $nextAction = null;

    public string $orderIdempotencyKey = '';

    public string $initiateIdempotencyKey = '';

    public string $confirmIdempotencyKey = '';

    public function mount(CashierShiftLedger $ledger): void
    {
        $this->authorize('create', Order::class);
        $this->visitDate = now()->toDateString();
        $this->rotateIdempotencyKeys();

        /** @var User $user */
        $user = auth()->user();
        $this->hasOpenShift = $ledger->openShiftFor($user) !== null;
    }

    public function updatedDestinationId(): void
    {
        $this->quantities = [];
        $this->quote = null;
        $this->errorMessage = '';
        $this->refreshQuote();
    }

    public function updatedQuantities(): void
    {
        $this->errorMessage = '';
        $this->refreshQuote();
    }

    public function updatedVisitDate(): void
    {
        $this->errorMessage = '';
        $this->refreshQuote();
    }

    public function setQuantity(int $ticketTypeId, int|string $quantity): void
    {
        $quantity = max(0, min(99, (int) $quantity));
        $key = (string) $ticketTypeId;

        if ($quantity === 0) {
            unset($this->quantities[$key]);
        } else {
            $this->quantities[$key] = $quantity;
        }

        $this->refreshQuote();
    }

    public function goToSummary(): void
    {
        $this->errorMessage = '';

        if (! $this->hasOpenShift) {
            $this->errorMessage = 'Buka shift kasir terlebih dahulu sebelum penjualan dibantu.';

            return;
        }

        $this->refreshQuote();

        if ($this->quote === null || (int) ($this->quote['grand_total'] ?? 0) <= 0) {
            $this->errorMessage = 'Pilih destinasi dan tiket terlebih dahulu.';

            return;
        }

        $this->step = 'summary';
    }

    public function backToCatalog(): void
    {
        $this->errorMessage = '';
        $this->step = 'catalog';
        $this->showCashModal = false;
    }

    public function createAndCollectCash(
        CreateOrder $createOrder,
        InitiatePayment $initiatePayment,
        CashierShiftLedger $ledger,
    ): void {
        $this->createAndCollect(PaymentMethod::Cash->value, $createOrder, $initiatePayment, $ledger);
    }

    public function createAndCollect(
        string $method,
        CreateOrder $createOrder,
        InitiatePayment $initiatePayment,
        CashierShiftLedger $ledger,
    ): void {
        $this->authorize('create', Order::class);
        $this->errorMessage = '';
        $this->nextAction = null;

        try {
            $paymentMethod = PaymentMethod::fromClient($method);
        } catch (\ValueError) {
            $this->errorMessage = 'Metode pembayaran tidak valid.';

            return;
        }

        $this->paymentMethod = $paymentMethod->value;

        $user = $this->staff();
        $this->hasOpenShift = $ledger->openShiftFor($user) !== null;

        if (! $this->hasOpenShift) {
            $this->errorMessage = 'Buka shift kasir terlebih dahulu sebelum penjualan dibantu.';

            return;
        }

        $payload = $this->orderPayload();

        try {
            $order = $createOrder->handle(
                $user,
                $payload,
                $this->orderIdempotencyKey,
                request(),
            );

            $session = $initiatePayment->handle(
                $user,
                $order,
                $paymentMethod,
                $this->initiateIdempotencyKey,
                request(),
            );

            /** @var Payment $payment */
            $payment = $session['payment'];

            if ($payment->status === PaymentStatus::Failed) {
                $this->errorMessage = 'Gagal memulai pembayaran. Coba lagi.';
                $this->rotatePaymentKeys();

                return;
            }

            $this->orderId = (int) $order->id;
            $this->orderNumber = (string) $order->order_number;
            $this->paymentId = (int) $payment->id;
            $this->paymentStatus = $payment->status->value;
            $this->paidAmount = (int) $payment->amount;
            $this->nextAction = is_array($session['next_action'] ?? null) ? $session['next_action'] : null;
            $this->step = 'pay';
            $this->showCashModal = $paymentMethod->isCash();

            if ($payment->status === PaymentStatus::Paid) {
                $this->finishPaid($payment);
            }
        } catch (AuthorizationException $e) {
            throw $e;
        } catch (ValidationException $e) {
            $this->errorMessage = collect($e->errors())->flatten()->first() ?: 'Validasi gagal.';
        } catch (DomainException $e) {
            $this->errorMessage = $e->getMessage();
            $this->rotateIdempotencyKeys();
        }
    }

    public function refreshPaymentStatus(): void
    {
        if ($this->paymentId === null) {
            return;
        }

        $payment = Payment::query()->with('tickets')->find($this->paymentId);
        if ($payment === null) {
            return;
        }

        $this->paymentStatus = $payment->status->value;
        $this->nextAction = $payment->nextAction();

        if ($payment->status === PaymentStatus::Paid) {
            $this->finishPaid($payment);
        }
    }

    public function confirmCashReceived(ConfirmCashPayment $confirmCash): void
    {
        $this->authorize('create', Order::class);
        $this->errorMessage = '';

        if ($this->paymentId === null) {
            $this->errorMessage = 'Pembayaran belum dimulai.';

            return;
        }

        $payment = Payment::query()->findOrFail($this->paymentId);

        try {
            $paid = $confirmCash->handle(
                $this->staff(),
                $payment,
                $this->confirmIdempotencyKey,
                request(),
            );

            $this->paymentStatus = $paid->status->value;
            $this->paidAmount = (int) $paid->amount;
            $this->showCashModal = false;
            $this->finishPaid($paid);
        } catch (AuthorizationException $e) {
            throw $e;
        } catch (DomainException $e) {
            $this->errorMessage = $e->getMessage();
            $this->rotateConfirmKey();
        }
    }

    private function finishPaid(Payment $payment): void
    {
        $this->paymentStatus = $payment->status->value;
        $this->paidAmount = (int) $payment->amount;
        $this->showCashModal = false;
        $this->loadIssuedTickets((int) $payment->order_id);
        $this->step = 'done';
    }

    public function cancelCashModal(): void
    {
        $this->showCashModal = false;
    }

    public function loadPrintPayloads(): void
    {
        $this->authorize('create', Order::class);
        $this->errorMessage = '';
        $this->printPayloads = [];

        foreach ($this->tickets as $ticketRow) {
            $ticket = Ticket::query()
                ->with(['destination', 'ticketType', 'orderItem', 'order'])
                ->where('ticket_code', $ticketRow['ticket_code'])
                ->first();

            if ($ticket === null) {
                continue;
            }

            if (! $this->staff()->can('print', $ticket) && ! $this->staff()->can('reprint', $ticket)) {
                throw new AuthorizationException('This action is unauthorized.');
            }
            $this->printPayloads[] = TicketPrintPayload::fromTicket($ticket);
        }
    }

    public function recordPrintSuccess(RecordTicketPrintAttempt $record): void
    {
        $this->recordSalePrints($record, PrintAttemptResult::Success, null);
    }

    public function recordPrintFailure(RecordTicketPrintAttempt $record): void
    {
        $this->recordSalePrints($record, PrintAttemptResult::Failed, 'assisted_print_failed');
    }

    private function recordSalePrints(
        RecordTicketPrintAttempt $record,
        PrintAttemptResult $result,
        ?string $message,
    ): void {
        $this->authorize('create', Order::class);
        $this->errorMessage = '';

        foreach ($this->tickets as $ticketRow) {
            $ticket = Ticket::query()
                ->with(['destination', 'ticketType', 'orderItem', 'order'])
                ->where('ticket_code', $ticketRow['ticket_code'])
                ->first();

            if ($ticket === null) {
                continue;
            }

            $record->handle($this->staff(), $ticket, $result, $message, request());
        }
    }

    public function startNewSale(): void
    {
        $this->authorize('create', Order::class);
        $this->step = 'catalog';
        $this->destinationId = null;
        $this->quantities = [];
        $this->customerNote = '';
        $this->visitDate = now()->toDateString();
        $this->quote = null;
        $this->orderId = null;
        $this->paymentId = null;
        $this->orderNumber = '';
        $this->paymentStatus = '';
        $this->paidAmount = 0;
        $this->tickets = [];
        $this->printPayloads = [];
        $this->showCashModal = false;
        $this->nextAction = null;
        $this->paymentMethod = 'cash';
        $this->errorMessage = '';
        $this->rotateIdempotencyKeys();
        $this->hasOpenShift = app(CashierShiftLedger::class)->openShiftFor($this->staff()) !== null;
    }

    public function render()
    {
        $destinations = Destination::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        $ticketTypes = $this->destinationId === null
            ? collect()
            : TicketType::query()
                ->where('destination_id', $this->destinationId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get();

        return view('livewire.assisted-sale.sale-wizard', [
            'destinations' => $destinations,
            'ticketTypes' => $ticketTypes,
            'channelAssisted' => Channel::Assisted->value,
            'paymentMethods' => PaymentMethod::cases(),
        ]);
    }

    private function refreshQuote(): void
    {
        $this->quote = null;

        if ($this->destinationId === null) {
            return;
        }

        $items = $this->cartItems();

        if ($items === []) {
            return;
        }

        try {
            $this->quote = app(QuoteOrderAction::class)->handle($this->destinationId, $items);
        } catch (ValidationException $e) {
            $this->errorMessage = collect($e->errors())->flatten()->first() ?: 'Kutipan harga gagal.';
            $this->quote = null;
        }
    }

    /**
     * @return list<array{ticket_type_id: int, quantity: int, visit_date: string}>
     */
    private function cartItems(): array
    {
        $items = [];

        foreach ($this->quantities as $ticketTypeId => $quantity) {
            $qty = (int) $quantity;
            if ($qty < 1) {
                continue;
            }

            $items[] = [
                'ticket_type_id' => (int) $ticketTypeId,
                'quantity' => $qty,
                'visit_date' => $this->visitDate !== '' ? $this->visitDate : now()->toDateString(),
            ];
        }

        return $items;
    }

    /**
     * @return array{
     *     destination_id: int,
     *     customer_note?: string|null,
     *     items: list<array{ticket_type_id: int, quantity: int, visit_date: string}>
     * }
     */
    private function orderPayload(): array
    {
        $payload = [
            'destination_id' => (int) $this->destinationId,
            'items' => $this->cartItems(),
        ];

        $note = trim($this->customerNote);
        if ($note !== '') {
            $payload['customer_note'] = $note;
        }

        return $payload;
    }

    private function loadIssuedTickets(int $orderId): void
    {
        /** @var Collection<int, Ticket> $tickets */
        $tickets = Ticket::query()
            ->where('order_id', $orderId)
            ->orderBy('id')
            ->get();

        $this->tickets = $tickets->map(static function (Ticket $ticket): array {
            return [
                'id' => $ticket->id,
                'ticket_code' => $ticket->ticket_code,
                'status' => $ticket->status?->value ?? (string) $ticket->status,
                'channel' => $ticket->channel?->value ?? (string) $ticket->channel,
                'qr_payload' => $ticket->qr_payload,
            ];
        })->values()->all();

        $order = Order::query()->find($orderId);
        if ($order !== null) {
            $this->orderNumber = (string) $order->order_number;
        }
    }

    private function staff(): User
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            throw new AuthorizationException('Unauthenticated.');
        }

        return $user;
    }

    private function rotateIdempotencyKeys(): void
    {
        $this->orderIdempotencyKey = (string) Str::uuid();
        $this->initiateIdempotencyKey = (string) Str::uuid();
        $this->confirmIdempotencyKey = (string) Str::uuid();
    }

    private function rotatePaymentKeys(): void
    {
        $this->initiateIdempotencyKey = (string) Str::uuid();
        $this->confirmIdempotencyKey = (string) Str::uuid();
    }

    private function rotateConfirmKey(): void
    {
        $this->confirmIdempotencyKey = (string) Str::uuid();
    }
}
