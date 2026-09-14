<?php

namespace App\Livewire\Audit;

use App\Models\AuditLog;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Audit logs')]
class AuditLogIndex extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    #[Url(as: 'action')]
    public string $actionFilter = '';

    #[Url(as: 'actor')]
    public string $actorTypeFilter = '';

    #[Url(as: 'entity')]
    public string $entityTypeFilter = '';

    #[Url(as: 'from')]
    public string $fromDate = '';

    #[Url(as: 'to')]
    public string $toDate = '';

    public ?int $selectedId = null;

    public function mount(): void
    {
        $this->authorize('viewAny', AuditLog::class);
    }

    public function updatingActionFilter(): void
    {
        $this->resetPage();
    }

    public function updatingActorTypeFilter(): void
    {
        $this->resetPage();
    }

    public function updatingEntityTypeFilter(): void
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

    public function selectLog(int $id): void
    {
        $log = AuditLog::query()->findOrFail($id);
        $this->authorize('view', $log);
        $this->selectedId = $id;
    }

    public function clearSelection(): void
    {
        $this->selectedId = null;
    }

    public function render()
    {
        $logs = AuditLog::query()
            ->action($this->actionFilter !== '' ? $this->actionFilter : null)
            ->actorType($this->actorTypeFilter !== '' ? $this->actorTypeFilter : null)
            ->entityType($this->entityTypeFilter !== '' ? $this->entityTypeFilter : null)
            ->createdBetween(
                $this->fromDate !== '' ? $this->fromDate : null,
                $this->toDate !== '' ? $this->toDate : null,
            )
            ->orderByDesc('id')
            ->paginate(20);

        $selected = $this->selectedId !== null
            ? AuditLog::query()->find($this->selectedId)
            : null;

        return view('livewire.audit.audit-log-index', [
            'logs' => $logs,
            'selected' => $selected,
        ]);
    }
}
