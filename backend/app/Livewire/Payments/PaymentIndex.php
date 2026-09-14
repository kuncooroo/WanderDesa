<?php

namespace App\Livewire\Payments;

use App\Actions\Payments\RefundPayment;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PermissionName;
use App\Enums\TicketStatus;
use App\Exceptions\DomainException;
use App\Models\Destination;
use App\Models\Payment;
use App\Models\User;
use App\Support\Authorization\Authorizer;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Staff payment list + detail/refund (docs/09 B5). No manual paint-to-PAID.
 */
#[Layout('layouts.app')]
#[Title('Pembayaran')]
class PaymentIndex extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    #[Url(as: 'q')]
    public string $paymentNumber = '';

    #[Url(as: 'status')]
    public string $statusFilter = '';

    #[Url(as: 'method')]
    public string $methodFilter = '';

    #[Url(as: 'destination')]
    public string $destinationId = '';

    #[Url(as: 'from')]
    public string $fromDate = '';

    #[Url(as: 'to')]
    public string $toDate = '';

    public ?int $selectedId = null;

    public string $refundReason = '';

    public string $refundIdempotencyKey = '';

    public string $errorMessage = '';

    public string $flashMessage = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Payment::class);
        $this->refundIdempotencyKey = (string) Str::uuid();
    }

    public function updatingPaymentNumber(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingMethodFilter(): void
    {
        $this->resetPage();
    }

    public function updatingDestinationId(): void
    {
        $this->resetPage();
    }

    public function updatingFromDate(): void
    {
        $this->resetPage();
    }

    public function updatingToDate(): void
    {
        $this->resetPage();
    }

    public function selectPayment(int $id): void
    {
        $payment = Payment::query()->findOrFail($id);
        $this->authorize('view', $payment);
        $this->selectedId = $id;
        $this->refundReason = '';
        $this->errorMessage = '';
        $this->flashMessage = '';
        $this->refundIdempotencyKey = (string) Str::uuid();
    }

    public function clearSelection(): void
    {
        $this->selectedId = null;
        $this->refundReason = '';
        $this->errorMessage = '';
    }

    public function refundSelected(RefundPayment $refund): void
    {
        if ($this->selectedId === null) {
            return;
        }

        $payment = Payment::query()->with('order')->findOrFail($this->selectedId);
        $this->authorize('refund', $payment);

        $this->validate([
            'refundReason' => ['required', 'string', 'min:1', 'max:500'],
        ]);

        try {
            $refund->handle(
                $this->staff(),
                $payment,
                $this->refundIdempotencyKey,
                trim($this->refundReason),
                request(),
            );
        } catch (DomainException $e) {
            $this->errorMessage = $e->getMessage();
            $this->refundIdempotencyKey = (string) Str::uuid();

            return;
        }

        $this->flashMessage = 'Pembayaran direfund.';
        $this->errorMessage = '';
        $this->refundReason = '';
        $this->refundIdempotencyKey = (string) Str::uuid();
        $this->resetPage();
    }

    public function render()
    {
        $query = Payment::query()
            ->with(['order.destination', 'collectedBy', 'device'])
            ->orderByDesc('id');

        $search = trim($this->paymentNumber);
        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('payment_number', 'like', '%'.$search.'%')
                    ->orWhereHas('order', function ($orderQuery) use ($search): void {
                        $orderQuery->where('order_number', 'like', '%'.$search.'%');
                    });
            });
        }

        if ($this->statusFilter !== '') {
            $query->where('status', $this->statusFilter);
        }

        if ($this->methodFilter !== '') {
            $query->where('method', $this->methodFilter);
        }

        if ($this->destinationId !== '') {
            $query->whereHas('order', function ($orderQuery): void {
                $orderQuery->where('destination_id', (int) $this->destinationId);
            });
        }

        if ($this->fromDate !== '') {
            $query->whereDate('created_at', '>=', $this->fromDate);
        }

        if ($this->toDate !== '') {
            $query->whereDate('created_at', '<=', $this->toDate);
        }

        $payments = $query->paginate(20);

        $selected = null;
        $canRefundSelected = false;

        if ($this->selectedId !== null) {
            $selected = Payment::query()
                ->with(['order.destination', 'collectedBy', 'device', 'tickets'])
                ->find($this->selectedId);

            if ($selected !== null) {
                $canRefundSelected = $this->refundEligible($selected);
            }
        }

        return view('livewire.payments.payment-index', [
            'payments' => $payments,
            'selected' => $selected,
            'destinations' => Destination::query()->orderBy('name')->get(['id', 'name', 'code']),
            'statuses' => PaymentStatus::cases(),
            'methods' => PaymentMethod::cases(),
            'canRefund' => Authorizer::check($this->staff(), PermissionName::PaymentsRefund),
            'canRefundSelected' => $canRefundSelected,
        ]);
    }

    private function refundEligible(Payment $payment): bool
    {
        if (! Authorizer::check($this->staff(), PermissionName::PaymentsRefund)) {
            return false;
        }

        if (! $payment->isPaid()) {
            return false;
        }

        $order = $payment->order;
        if ($order === null || $order->status !== OrderStatus::Paid) {
            return false;
        }

        $tickets = $payment->relationLoaded('tickets')
            ? $payment->tickets
            : $payment->tickets()->get();

        return ! $tickets->contains(
            fn ($ticket): bool => $ticket->status === TicketStatus::Used,
        );
    }

    private function staff(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
