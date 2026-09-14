<?php

namespace App\Livewire\Orders;

use App\Actions\Orders\CancelOrder;
use App\Enums\Channel;
use App\Enums\OrderStatus;
use App\Enums\PermissionName;
use App\Exceptions\DomainException;
use App\Models\Destination;
use App\Models\Order;
use App\Models\User;
use App\Support\Authorization\Authorizer;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Staff order list + detail (docs/09 B3). Money fields are read-only from server.
 */
#[Layout('layouts.app')]
#[Title('Pesanan')]
class OrderIndex extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    #[Url(as: 'q')]
    public string $orderNumber = '';

    #[Url(as: 'status')]
    public string $statusFilter = '';

    #[Url(as: 'channel')]
    public string $channelFilter = '';

    #[Url(as: 'destination')]
    public string $destinationId = '';

    #[Url(as: 'from')]
    public string $fromDate = '';

    #[Url(as: 'to')]
    public string $toDate = '';

    public ?int $selectedId = null;

    public string $errorMessage = '';

    public string $flashMessage = '';

    public string $cancelReason = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Order::class);
    }

    public function updatingOrderNumber(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingChannelFilter(): void
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

    public function selectOrder(int $id): void
    {
        $order = Order::query()->findOrFail($id);
        $this->authorize('view', $order);
        $this->selectedId = $id;
        $this->errorMessage = '';
        $this->flashMessage = '';
        $this->cancelReason = '';
    }

    public function clearSelection(): void
    {
        $this->selectedId = null;
        $this->cancelReason = '';
    }

    public function cancelSelected(CancelOrder $cancel): void
    {
        if ($this->selectedId === null) {
            return;
        }

        $order = Order::query()->findOrFail($this->selectedId);
        $this->authorize('cancel', $order);

        $this->validate([
            'cancelReason' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $cancel->handle(
                $this->staff(),
                $order,
                $this->cancelReason !== '' ? $this->cancelReason : null,
                request(),
            );
        } catch (DomainException $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        $this->flashMessage = 'Pesanan dibatalkan.';
        $this->errorMessage = '';
        $this->cancelReason = '';
        $this->resetPage();
    }

    public function render()
    {
        $query = Order::query()
            ->with(['destination', 'device', 'createdBy'])
            ->orderByDesc('id');

        $search = trim($this->orderNumber);
        if ($search !== '') {
            $query->where('order_number', 'like', '%'.$search.'%');
        }

        if ($this->statusFilter !== '') {
            $query->where('status', $this->statusFilter);
        }

        if ($this->channelFilter !== '') {
            $query->where('channel', $this->channelFilter);
        }

        if ($this->destinationId !== '') {
            $query->where('destination_id', (int) $this->destinationId);
        }

        if ($this->fromDate !== '') {
            $query->whereDate('created_at', '>=', $this->fromDate);
        }

        if ($this->toDate !== '') {
            $query->whereDate('created_at', '<=', $this->toDate);
        }

        $orders = $query->paginate(20);

        $selected = null;
        if ($this->selectedId !== null) {
            $selected = Order::query()
                ->with(['destination', 'device', 'createdBy', 'items', 'payments', 'tickets'])
                ->find($this->selectedId);
        }

        return view('livewire.orders.order-index', [
            'orders' => $orders,
            'selected' => $selected,
            'destinations' => Destination::query()->orderBy('name')->get(['id', 'name', 'code']),
            'statuses' => OrderStatus::cases(),
            'channels' => Channel::cases(),
            'canCancel' => Authorizer::check($this->staff(), PermissionName::OrdersCancel),
        ]);
    }

    private function staff(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
