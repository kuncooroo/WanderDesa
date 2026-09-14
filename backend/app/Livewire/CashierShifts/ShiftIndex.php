<?php

namespace App\Livewire\CashierShifts;

use App\Actions\CashierShifts\CloseCashierShift;
use App\Actions\CashierShifts\DiscardOpenCashierShift;
use App\Actions\CashierShifts\OpenCashierShift;
use App\Actions\CashierShifts\UpdateCashierShiftNotes;
use App\Enums\CashierShiftStatus;
use App\Enums\PermissionName;
use App\Exceptions\DomainException;
use App\Models\CashierShift;
use App\Models\User;
use App\Services\CashierShifts\CashierShiftLedger;
use App\Support\Authorization\Authorizer;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Shift list + open/close/notes popups. Money fields are server-computed except initial/actual cash inputs.
 */
#[Layout('layouts.app')]
#[Title('Shift & rekonsiliasi')]
class ShiftIndex extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    #[Url(as: 'status')]
    public string $statusFilter = '';

    #[Url(as: 'from')]
    public string $fromDate = '';

    #[Url(as: 'to')]
    public string $toDate = '';

    public bool $showOpenModal = false;

    public bool $showCloseModal = false;

    public bool $showNotesModal = false;

    public ?int $closingShiftId = null;

    public ?int $notesShiftId = null;

    public string $initialCash = '';

    public string $openNotes = '';

    public string $actualCash = '';

    public string $closeNotes = '';

    public string $editNotes = '';

    /** @var array<string, int|string>|null */
    public ?array $closePreview = null;

    public string $errorMessage = '';

    public string $flashMessage = '';

    public function mount(): void
    {
        $this->authorize('viewAny', CashierShift::class);
    }

    public function updatingStatusFilter(): void
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

    public function openCreateModal(): void
    {
        $this->authorize('create', CashierShift::class);
        $this->resetValidation();
        $this->errorMessage = '';
        $this->initialCash = '';
        $this->openNotes = '';
        $this->showOpenModal = true;
    }

    public function closeCreateModal(): void
    {
        $this->showOpenModal = false;
    }

    public function submitOpenShift(OpenCashierShift $action): void
    {
        $this->authorize('create', CashierShift::class);
        $this->errorMessage = '';
        $this->flashMessage = '';

        $this->validate([
            'initialCash' => ['required', 'integer', 'min:0'],
            'openNotes' => ['nullable', 'string', 'max:2000'],
        ], [], [
            'initialCash' => 'modal awal',
            'openNotes' => 'catatan',
        ]);

        try {
            /** @var User $user */
            $user = auth()->user();
            $action->handle($user, [
                'initial_cash' => (int) $this->initialCash,
                'notes' => $this->openNotes !== '' ? $this->openNotes : null,
            ], request());

            $this->showOpenModal = false;
            $this->flashMessage = 'Shift dibuka.';
            $this->resetPage();
        } catch (DomainException $e) {
            $this->errorMessage = $e->getMessage();
        } catch (AuthorizationException $e) {
            $this->errorMessage = $e->getMessage();
        } catch (ValidationException $e) {
            throw $e;
        }
    }

    public function openCloseModal(int $shiftId, CashierShiftLedger $ledger): void
    {
        $shift = CashierShift::query()->findOrFail($shiftId);
        $this->authorize('close', $shift);

        $totals = $ledger->recalculate($shift);

        $this->closingShiftId = $shift->id;
        $this->actualCash = '';
        $this->closeNotes = (string) ($shift->notes ?? '');
        $this->closePreview = [
            'id' => $shift->id,
            'initial_cash' => (int) $shift->initial_cash,
            'total_cash_sales' => $totals['total_cash_sales'],
            'total_cash_refund' => $totals['total_cash_refund'],
            'expected_cash' => $totals['expected_cash'],
        ];
        $this->errorMessage = '';
        $this->resetValidation();
        $this->showCloseModal = true;
    }

    public function closeCloseModal(): void
    {
        $this->showCloseModal = false;
        $this->closingShiftId = null;
        $this->closePreview = null;
    }

    public function updatedActualCash(): void
    {
        // Live difference computed in render from closePreview + actualCash.
    }

    public function submitCloseShift(CloseCashierShift $action): void
    {
        if ($this->closingShiftId === null) {
            return;
        }

        $shift = CashierShift::query()->findOrFail($this->closingShiftId);
        $this->authorize('close', $shift);
        $this->errorMessage = '';

        $this->validate([
            'actualCash' => ['required', 'integer', 'min:0'],
            'closeNotes' => ['nullable', 'string', 'max:2000'],
        ], [], [
            'actualCash' => 'kas aktual',
            'closeNotes' => 'catatan',
        ]);

        try {
            /** @var User $user */
            $user = auth()->user();
            $closed = $action->handle($user, $shift, [
                'actual_cash' => (int) $this->actualCash,
                'notes' => $this->closeNotes !== '' ? $this->closeNotes : null,
            ], request());

            $this->showCloseModal = false;
            $this->closingShiftId = null;
            $this->closePreview = null;

            $diff = (int) $closed->difference;
            $this->flashMessage = $diff === 0
                ? 'Shift ditutup. Kas sesuai.'
                : 'Shift ditutup. Selisih Rp '.number_format($diff, 0, ',', '.');
            $this->resetPage();
        } catch (DomainException $e) {
            $this->errorMessage = $e->getMessage();
        } catch (AuthorizationException $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function openNotesModal(int $shiftId): void
    {
        $shift = CashierShift::query()->findOrFail($shiftId);
        $this->authorize('update', $shift);

        $this->notesShiftId = $shift->id;
        $this->editNotes = (string) ($shift->notes ?? '');
        $this->errorMessage = '';
        $this->showNotesModal = true;
    }

    public function closeNotesModal(): void
    {
        $this->showNotesModal = false;
        $this->notesShiftId = null;
    }

    public function submitNotes(UpdateCashierShiftNotes $action): void
    {
        if ($this->notesShiftId === null) {
            return;
        }

        $shift = CashierShift::query()->findOrFail($this->notesShiftId);
        $this->authorize('update', $shift);

        $this->validate([
            'editNotes' => ['nullable', 'string', 'max:2000'],
        ], [], [
            'editNotes' => 'catatan',
        ]);

        try {
            /** @var User $user */
            $user = auth()->user();
            $action->handle(
                $user,
                $shift,
                $this->editNotes !== '' ? $this->editNotes : null,
                request(),
            );

            $this->showNotesModal = false;
            $this->notesShiftId = null;
            $this->flashMessage = 'Catatan shift diperbarui.';
        } catch (AuthorizationException $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function discardShift(int $shiftId, DiscardOpenCashierShift $action): void
    {
        $shift = CashierShift::query()->findOrFail($shiftId);
        $this->authorize('delete', $shift);
        $this->errorMessage = '';

        try {
            /** @var User $user */
            $user = auth()->user();
            $action->handle($user, $shift, request());
            $this->flashMessage = 'Shift terbuka dibatalkan.';
            $this->resetPage();
        } catch (DomainException $e) {
            $this->errorMessage = $e->getMessage();
        } catch (AuthorizationException $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function render(CashierShiftLedger $ledger)
    {
        /** @var User $user */
        $user = auth()->user();
        $canViewAll = Authorizer::check($user, PermissionName::PaymentsView);
        $canCreate = Authorizer::check($user, PermissionName::PaymentsCreate);

        $query = CashierShift::query()
            ->with('user')
            ->orderByDesc('opened_at');

        if (! $canViewAll) {
            $query->where('user_id', $user->id);
        }

        if ($this->statusFilter !== '' && CashierShiftStatus::tryFrom($this->statusFilter)) {
            $query->where('status', $this->statusFilter);
        }

        if ($this->fromDate !== '') {
            $query->whereDate('opened_at', '>=', $this->fromDate);
        }

        if ($this->toDate !== '') {
            $query->whereDate('opened_at', '<=', $this->toDate);
        }

        $shifts = $query->paginate(15);

        $activeShift = $canCreate ? $ledger->openShiftFor($user) : null;

        $summaryQuery = CashierShift::query();
        if (! $canViewAll) {
            $summaryQuery->where('user_id', $user->id);
        }

        $openCount = (clone $summaryQuery)->where('status', CashierShiftStatus::Open)->count();
        $closedToday = (clone $summaryQuery)
            ->where('status', CashierShiftStatus::Closed)
            ->whereDate('closed_at', now()->toDateString())
            ->count();
        $differenceToday = (int) (clone $summaryQuery)
            ->where('status', CashierShiftStatus::Closed)
            ->whereDate('closed_at', now()->toDateString())
            ->sum('difference');

        $liveDifference = null;
        if ($this->showCloseModal && $this->closePreview !== null && $this->actualCash !== '' && is_numeric($this->actualCash)) {
            $liveDifference = (int) $this->actualCash - (int) $this->closePreview['expected_cash'];
        }

        return view('livewire.cashier-shifts.shift-index', [
            'shifts' => $shifts,
            'activeShift' => $activeShift,
            'canCreate' => $canCreate,
            'canViewAll' => $canViewAll,
            'openCount' => $openCount,
            'closedToday' => $closedToday,
            'differenceToday' => $differenceToday,
            'liveDifference' => $liveDifference,
            'statuses' => CashierShiftStatus::cases(),
        ]);
    }
}
