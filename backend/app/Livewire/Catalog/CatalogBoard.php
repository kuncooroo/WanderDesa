<?php

namespace App\Livewire\Catalog;

use App\Actions\Catalog\CreateDestination;
use App\Actions\Catalog\DeactivateDestination;
use App\Actions\Catalog\TicketTypes\CreateTicketType;
use App\Actions\Catalog\TicketTypes\DeactivateTicketType;
use App\Actions\Catalog\TicketTypes\UpdateTicketType;
use App\Actions\Catalog\UpdateDestination;
use App\Enums\PermissionName;
use App\Enums\ValidityType;
use App\Models\Destination;
use App\Models\TicketType;
use App\Models\User;
use App\Support\Authorization\Authorizer;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Staff catalog UI (docs/09 B10–B12). Prices are IDR integers; server Actions own writes.
 */
#[Layout('layouts.app')]
#[Title('Destinasi & tiket')]
class CatalogBoard extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    #[Url(as: 'tab')]
    public string $tab = 'destinations';

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'destination')]
    public string $selectedDestinationId = '';

    public ?int $editingDestinationId = null;

    public bool $showDestinationForm = false;

    public string $destinationCode = '';

    public string $destinationName = '';

    public string $destinationTimezone = 'Asia/Jakarta';

    public string $destinationDescription = '';

    public bool $destinationActive = true;

    public ?int $editingTicketTypeId = null;

    public bool $showTicketForm = false;

    public string $ticketCode = '';

    public string $ticketName = '';

    public string $ticketDescription = '';

    public int $unitPrice = 0;

    public int $taxAmount = 0;

    public int $serviceFeeAmount = 0;

    public string $validityType = 'same_day';

    public ?int $validityDays = null;

    public string $validFromTime = '';

    public string $validUntilTime = '';

    public int $maxPerOrder = 20;

    public bool $ticketActive = true;

    public string $flashMessage = '';

    public string $errorMessage = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Destination::class);

        if (! in_array($this->tab, ['destinations', 'tickets'], true)) {
            $this->tab = 'destinations';
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function showTab(string $tab): void
    {
        if (! in_array($tab, ['destinations', 'tickets'], true)) {
            return;
        }

        $this->tab = $tab;
        $this->clearMessages();
        $this->resetPage();
    }

    public function startCreateDestination(): void
    {
        $this->authorize('create', Destination::class);
        $this->resetDestinationForm();
        $this->editingDestinationId = null;
        $this->showDestinationForm = true;
        $this->clearMessages();
    }

    public function editDestination(int $id): void
    {
        $destination = Destination::query()->findOrFail($id);
        $this->authorize('update', $destination);

        $this->editingDestinationId = $destination->id;
        $this->destinationCode = $destination->code;
        $this->destinationName = $destination->name;
        $this->destinationTimezone = $destination->timezone;
        $this->destinationDescription = (string) ($destination->description ?? '');
        $this->destinationActive = (bool) $destination->is_active;
        $this->showDestinationForm = true;
        $this->clearMessages();
    }

    public function cancelDestinationForm(): void
    {
        $this->resetDestinationForm();
        $this->showDestinationForm = false;
        $this->clearMessages();
    }

    public function saveDestination(CreateDestination $create, UpdateDestination $update): void
    {
        $rules = [
            'destinationCode' => [
                'required',
                'string',
                'max:40',
                'regex:/^[A-Za-z0-9][A-Za-z0-9_-]*$/',
                Rule::unique('destinations', 'code')->ignore($this->editingDestinationId),
            ],
            'destinationName' => ['required', 'string', 'max:160'],
            'destinationTimezone' => ['required', 'string', 'timezone:all', 'max:64'],
            'destinationDescription' => ['nullable', 'string'],
            'destinationActive' => ['boolean'],
        ];

        $validated = $this->validate($rules);

        $payload = [
            'code' => $validated['destinationCode'],
            'name' => $validated['destinationName'],
            'timezone' => $validated['destinationTimezone'],
            'description' => $validated['destinationDescription'] !== '' ? $validated['destinationDescription'] : null,
            'is_active' => $validated['destinationActive'],
        ];

        if ($this->editingDestinationId === null) {
            $this->authorize('create', Destination::class);
            $create->handle($this->staff(), $payload, request());
            $this->flashMessage = 'Destinasi dibuat.';
        } else {
            $destination = Destination::query()->findOrFail($this->editingDestinationId);
            $this->authorize('update', $destination);
            $update->handle($this->staff(), $destination, $payload, request());
            $this->flashMessage = 'Destinasi diperbarui.';
        }

        $this->errorMessage = '';
        $this->resetDestinationForm();
        $this->showDestinationForm = false;
        $this->resetPage();
    }

    public function deactivateDestination(int $id, DeactivateDestination $deactivate): void
    {
        $destination = Destination::query()->findOrFail($id);
        $this->authorize('delete', $destination);
        $deactivate->handle($this->staff(), $destination, request());
        $this->flashMessage = 'Destinasi dinonaktifkan.';
        $this->errorMessage = '';

        if ($this->editingDestinationId === $id) {
            $this->resetDestinationForm();
            $this->showDestinationForm = false;
        }

        if ((string) $id === $this->selectedDestinationId) {
            $this->selectedDestinationId = '';
        }
    }

    public function activateDestination(int $id, UpdateDestination $update): void
    {
        $destination = Destination::query()->findOrFail($id);
        $this->authorize('update', $destination);
        $update->handle($this->staff(), $destination, ['is_active' => true], request());
        $this->flashMessage = 'Destinasi diaktifkan.';
        $this->errorMessage = '';
    }

    public function selectDestination(int $id): void
    {
        $destination = Destination::query()->findOrFail($id);
        $this->authorize('view', $destination);
        $this->selectedDestinationId = (string) $id;
        $this->tab = 'tickets';
        $this->resetTicketForm();
        $this->showTicketForm = false;
        $this->clearMessages();
        $this->resetPage();
    }

    public function startCreateTicketType(): void
    {
        $this->authorize('create', TicketType::class);

        if ($this->selectedDestinationId === '') {
            $this->errorMessage = 'Pilih destinasi terlebih dahulu.';

            return;
        }

        $this->resetTicketForm();
        $this->editingTicketTypeId = null;
        $this->showTicketForm = true;
        $this->clearMessages();
    }

    public function editTicketType(int $id): void
    {
        $ticketType = TicketType::query()->findOrFail($id);
        $this->authorize('update', $ticketType);

        $this->editingTicketTypeId = $ticketType->id;
        $this->selectedDestinationId = (string) $ticketType->destination_id;
        $this->ticketCode = $ticketType->code;
        $this->ticketName = $ticketType->name;
        $this->ticketDescription = (string) ($ticketType->description ?? '');
        $this->unitPrice = (int) $ticketType->unit_price;
        $this->taxAmount = (int) $ticketType->tax_amount;
        $this->serviceFeeAmount = (int) $ticketType->service_fee_amount;
        $this->validityType = $ticketType->validity_type instanceof ValidityType
            ? $ticketType->validity_type->value
            : (string) $ticketType->validity_type;
        $this->validityDays = $ticketType->validity_days;
        $this->validFromTime = $this->formatTime($ticketType->valid_from_time);
        $this->validUntilTime = $this->formatTime($ticketType->valid_until_time);
        $this->maxPerOrder = (int) $ticketType->max_per_order;
        $this->ticketActive = (bool) $ticketType->is_active;
        $this->showTicketForm = true;
        $this->clearMessages();
    }

    public function cancelTicketForm(): void
    {
        $this->resetTicketForm();
        $this->showTicketForm = false;
        $this->clearMessages();
    }

    public function saveTicketType(CreateTicketType $create, UpdateTicketType $update): void
    {
        $destinationId = (int) $this->selectedDestinationId;

        $rules = [
            'selectedDestinationId' => [
                'required',
                'integer',
                Rule::exists('destinations', 'id')->whereNull('deleted_at'),
            ],
            'ticketCode' => [
                'required',
                'string',
                'max:40',
                'regex:/^[A-Za-z0-9][A-Za-z0-9_-]*$/',
                Rule::unique('ticket_types', 'code')
                    ->where(fn ($query) => $query->where('destination_id', $destinationId))
                    ->ignore($this->editingTicketTypeId),
            ],
            'ticketName' => ['required', 'string', 'max:160'],
            'ticketDescription' => ['nullable', 'string'],
            'unitPrice' => ['required', 'integer', 'min:0'],
            'taxAmount' => ['required', 'integer', 'min:0'],
            'serviceFeeAmount' => ['required', 'integer', 'min:0'],
            'validityType' => ['required', 'string', Rule::in(ValidityType::values())],
            'validityDays' => [
                Rule::requiredIf(fn (): bool => $this->validityType === ValidityType::DaysFromIssue->value),
                'nullable',
                'integer',
                'min:1',
            ],
            'validFromTime' => ['nullable', 'date_format:H:i'],
            'validUntilTime' => [
                'nullable',
                'date_format:H:i',
                Rule::when($this->validFromTime !== '', ['after:validFromTime']),
            ],
            'maxPerOrder' => ['required', 'integer', 'min:1'],
            'ticketActive' => ['boolean'],
        ];

        $validated = $this->validate($rules);

        $payload = [
            'destination_id' => (int) $validated['selectedDestinationId'],
            'code' => $validated['ticketCode'],
            'name' => $validated['ticketName'],
            'description' => $validated['ticketDescription'] !== '' ? $validated['ticketDescription'] : null,
            'currency' => 'IDR',
            'unit_price' => (int) $validated['unitPrice'],
            'tax_amount' => (int) $validated['taxAmount'],
            'service_fee_amount' => (int) $validated['serviceFeeAmount'],
            'validity_type' => $validated['validityType'],
            'validity_days' => $validated['validityType'] === ValidityType::DaysFromIssue->value
                ? $validated['validityDays']
                : null,
            'valid_from_time' => $validated['validFromTime'] !== '' ? $validated['validFromTime'] : null,
            'valid_until_time' => $validated['validUntilTime'] !== '' ? $validated['validUntilTime'] : null,
            'max_per_order' => (int) $validated['maxPerOrder'],
            'is_active' => $validated['ticketActive'],
        ];

        if ($this->editingTicketTypeId === null) {
            $this->authorize('create', TicketType::class);
            $create->handle($this->staff(), $payload, request());
            $this->flashMessage = 'Jenis tiket dibuat.';
        } else {
            $ticketType = TicketType::query()->findOrFail($this->editingTicketTypeId);
            $this->authorize('update', $ticketType);
            $update->handle($this->staff(), $ticketType, $payload, request());
            $this->flashMessage = 'Jenis tiket diperbarui.';
        }

        $this->errorMessage = '';
        $this->resetTicketForm();
        $this->showTicketForm = false;
        $this->resetPage();
    }

    public function deactivateTicketType(int $id, DeactivateTicketType $deactivate): void
    {
        $ticketType = TicketType::query()->findOrFail($id);
        $this->authorize('delete', $ticketType);
        $deactivate->handle($this->staff(), $ticketType, request());
        $this->flashMessage = 'Jenis tiket dinonaktifkan.';
        $this->errorMessage = '';

        if ($this->editingTicketTypeId === $id) {
            $this->resetTicketForm();
            $this->showTicketForm = false;
        }
    }

    public function activateTicketType(int $id, UpdateTicketType $update): void
    {
        $ticketType = TicketType::query()->findOrFail($id);
        $this->authorize('update', $ticketType);
        $update->handle($this->staff(), $ticketType, ['is_active' => true], request());
        $this->flashMessage = 'Jenis tiket diaktifkan.';
        $this->errorMessage = '';
    }

    public function render()
    {
        $destinationsQuery = Destination::query()->orderBy('name');

        $search = trim($this->search);
        if ($search !== '' && $this->tab === 'destinations') {
            $destinationsQuery->where(function ($q) use ($search): void {
                $q->where('name', 'like', '%'.$search.'%')
                    ->orWhere('code', 'like', '%'.$search.'%');
            });
        }

        $destinations = $destinationsQuery->paginate(15, pageName: 'destPage');

        $ticketTypes = collect();
        $selectedDestination = null;

        if ($this->selectedDestinationId !== '') {
            $selectedDestination = Destination::query()->find((int) $this->selectedDestinationId);

            if ($selectedDestination !== null) {
                $ticketsQuery = TicketType::query()
                    ->where('destination_id', $selectedDestination->id)
                    ->orderBy('name');

                if ($search !== '' && $this->tab === 'tickets') {
                    $ticketsQuery->where(function ($q) use ($search): void {
                        $q->where('name', 'like', '%'.$search.'%')
                            ->orWhere('code', 'like', '%'.$search.'%');
                    });
                }

                $ticketTypes = $ticketsQuery->paginate(15, pageName: 'ticketPage');
            }
        }

        return view('livewire.catalog.catalog-board', [
            'destinations' => $destinations,
            'ticketTypes' => $ticketTypes,
            'selectedDestination' => $selectedDestination,
            'allDestinations' => Destination::query()->orderBy('name')->get(['id', 'name', 'code', 'is_active']),
            'validityTypes' => ValidityType::cases(),
            'canManageDestinations' => Authorizer::check($this->staff(), PermissionName::DestinationsManage),
            'canManageTicketTypes' => Authorizer::check($this->staff(), PermissionName::TicketTypesManage),
        ]);
    }

    private function resetDestinationForm(): void
    {
        $this->editingDestinationId = null;
        $this->destinationCode = '';
        $this->destinationName = '';
        $this->destinationTimezone = 'Asia/Jakarta';
        $this->destinationDescription = '';
        $this->destinationActive = true;
        $this->resetValidation([
            'destinationCode',
            'destinationName',
            'destinationTimezone',
            'destinationDescription',
            'destinationActive',
        ]);
    }

    private function resetTicketForm(): void
    {
        $this->editingTicketTypeId = null;
        $this->ticketCode = '';
        $this->ticketName = '';
        $this->ticketDescription = '';
        $this->unitPrice = 0;
        $this->taxAmount = 0;
        $this->serviceFeeAmount = 0;
        $this->validityType = ValidityType::SameDay->value;
        $this->validityDays = null;
        $this->validFromTime = '';
        $this->validUntilTime = '';
        $this->maxPerOrder = 20;
        $this->ticketActive = true;
        $this->resetValidation([
            'ticketCode',
            'ticketName',
            'ticketDescription',
            'unitPrice',
            'taxAmount',
            'serviceFeeAmount',
            'validityType',
            'validityDays',
            'validFromTime',
            'validUntilTime',
            'maxPerOrder',
            'ticketActive',
            'selectedDestinationId',
        ]);
    }

    private function clearMessages(): void
    {
        $this->flashMessage = '';
        $this->errorMessage = '';
    }

    private function formatTime(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $string = (string) $value;

        return strlen($string) >= 5 ? substr($string, 0, 5) : $string;
    }

    private function staff(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
