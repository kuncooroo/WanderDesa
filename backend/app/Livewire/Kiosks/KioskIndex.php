<?php

namespace App\Livewire\Kiosks;

use App\Actions\Devices\DeactivateDevice;
use App\Actions\Devices\IssueDeviceActivation;
use App\Actions\Devices\RegisterDevice;
use App\Actions\Devices\SetDeviceMaintenance;
use App\Actions\Devices\UpdateDevice;
use App\Enums\PermissionName;
use App\Exceptions\DomainException;
use App\Models\Destination;
use App\Models\Device;
use App\Models\Setting;
use App\Models\User;
use App\Support\Authorization\Authorizer;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Kiosk')]
class KioskIndex extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    public string $deviceId = '';

    public string $name = '';

    public string $terminalId = '';

    public ?int $destinationId = null;

    public ?int $editingId = null;

    public bool $showRegisterModal = false;

    public ?int $detailId = null;

    public string $editName = '';

    public ?int $editDestinationId = null;

    public string $flashMessage = '';

    public string $errorMessage = '';

    public ?string $activationCode = null;

    public ?string $activationExpiresAt = null;

    public ?int $activationDeviceId = null;

    public ?int $confirmDeactivateId = null;

    public function mount(): void
    {
        $this->authorize('viewAny', Device::class);
    }

    public function openRegisterModal(): void
    {
        $this->authorize('create', Device::class);
        $this->reset(['deviceId', 'name', 'terminalId', 'destinationId']);
        $this->resetValidation();
        $this->showRegisterModal = true;
        $this->editingId = null;
        $this->detailId = null;
        $this->errorMessage = '';
        $this->flashMessage = '';
    }

    public function closeRegisterModal(): void
    {
        $this->showRegisterModal = false;
        $this->reset(['deviceId', 'name', 'terminalId', 'destinationId']);
        $this->resetValidation();
    }

    public function showDetail(int $id): void
    {
        $device = Device::query()->findOrFail($id);
        $this->authorize('view', $device);
        $this->detailId = $device->id;
        $this->showRegisterModal = false;
        $this->editingId = null;
        $this->errorMessage = '';
        $this->flashMessage = '';
    }

    public function closeDetail(): void
    {
        $this->detailId = null;
    }

    public function registerDevice(RegisterDevice $register): void
    {
        $this->authorize('create', Device::class);

        $validated = $this->validate([
            'deviceId' => [
                'required',
                'string',
                'max:64',
                'regex:/^[A-Za-z0-9][A-Za-z0-9._:-]*$/',
                Rule::unique('devices', 'device_id'),
            ],
            'name' => ['required', 'string', 'max:120'],
            'terminalId' => ['nullable', 'string', 'max:64', Rule::unique('devices', 'terminal_id')],
            'destinationId' => [
                'required',
                'integer',
                Rule::exists('destinations', 'id')->where(fn ($q) => $q->where('is_active', true)),
            ],
        ]);

        $register->handle($this->staff(), [
            'device_id' => $validated['deviceId'],
            'name' => $validated['name'],
            'terminal_id' => $validated['terminalId'] !== '' ? $validated['terminalId'] : null,
            'destination_id' => (int) $validated['destinationId'],
        ], request());

        $this->flashMessage = 'Kiosk didaftarkan.';
        $this->errorMessage = '';
        $this->closeRegisterModal();
        $this->resetPage();
    }

    public function editDevice(int $id): void
    {
        $device = Device::query()->findOrFail($id);
        $this->authorize('update', $device);

        $this->editingId = $device->id;
        $this->detailId = null;
        $this->showRegisterModal = false;
        $this->editName = $device->name;
        $this->editDestinationId = $device->destination_id;
        $this->errorMessage = '';
        $this->flashMessage = '';
    }

    public function cancelEdit(): void
    {
        $this->editingId = null;
        $this->editName = '';
        $this->editDestinationId = null;
        $this->resetValidation(['editName', 'editDestinationId']);
    }

    public function saveDevice(UpdateDevice $update): void
    {
        if ($this->editingId === null) {
            return;
        }

        $device = Device::query()->findOrFail($this->editingId);
        $this->authorize('update', $device);

        $validated = $this->validate([
            'editName' => ['required', 'string', 'max:120'],
            'editDestinationId' => [
                'required',
                'integer',
                Rule::exists('destinations', 'id')->where(fn ($q) => $q->where('is_active', true)),
            ],
        ]);

        try {
            $update->handle($this->staff(), $device, [
                'name' => $validated['editName'],
                'destination_id' => (int) $validated['editDestinationId'],
            ], request());
        } catch (DomainException $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        $this->flashMessage = 'Kiosk diperbarui.';
        $this->errorMessage = '';
        $this->cancelEdit();
    }

    public function issueActivation(int $deviceId, IssueDeviceActivation $issue): void
    {
        $device = Device::query()->findOrFail($deviceId);
        $this->authorize('activate', $device);

        $result = $issue->handle($this->staff(), $device, true, request());

        $this->activationDeviceId = $result['device']->id;
        $this->activationCode = $result['activation_code'];
        $this->activationExpiresAt = $result['expires_at']->timezone(config('app.timezone'))->toDateTimeString();
    }

    public function clearActivationModal(): void
    {
        $this->activationDeviceId = null;
        $this->activationCode = null;
        $this->activationExpiresAt = null;
    }

    public function toggleMaintenance(int $deviceId, bool $enabled, SetDeviceMaintenance $setMaintenance): void
    {
        $device = Device::query()->findOrFail($deviceId);
        $this->authorize('maintenance', $device);
        $setMaintenance->handle($this->staff(), $device, $enabled, request());
    }

    public function askDeactivate(int $deviceId): void
    {
        $device = Device::query()->findOrFail($deviceId);
        $this->authorize('deactivate', $device);
        $this->confirmDeactivateId = $deviceId;
    }

    public function cancelDeactivate(): void
    {
        $this->confirmDeactivateId = null;
    }

    public function confirmDeactivate(DeactivateDevice $deactivate): void
    {
        if ($this->confirmDeactivateId === null) {
            return;
        }

        $device = Device::query()->findOrFail($this->confirmDeactivateId);
        $this->authorize('deactivate', $device);
        $deactivate->handle($this->staff(), $device, request());
        $this->confirmDeactivateId = null;
        $this->flashMessage = 'Kiosk dinonaktifkan.';
    }

    public function render()
    {
        $staleSeconds = Setting::heartbeatStaleSeconds();

        $devices = Device::query()
            ->with('destination')
            ->orderBy('name')
            ->paginate(15);

        return view('livewire.kiosks.kiosk-index', [
            'devices' => $devices,
            'destinations' => Destination::query()->active()->orderBy('name')->get(),
            'staleSeconds' => $staleSeconds,
            'detailDevice' => $this->detailId
                ? Device::query()->with('destination')->find($this->detailId)
                : null,
            'canCreate' => Authorizer::check($this->staff(), PermissionName::KiosksCreate),
            'canUpdate' => Authorizer::check($this->staff(), PermissionName::KiosksUpdate),
            'canActivate' => Authorizer::check($this->staff(), PermissionName::KiosksActivate),
            'canMaintenance' => Authorizer::check($this->staff(), PermissionName::KiosksMaintenance),
            'canDeactivate' => Authorizer::check($this->staff(), PermissionName::KiosksDeactivate),
        ]);
    }

    private function staff(): User
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            throw new AuthorizationException('Unauthenticated.');
        }

        return $user;
    }
}
