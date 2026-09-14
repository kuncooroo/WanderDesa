<?php

namespace App\Livewire\Settings;

use App\Actions\Settings\UpdateSystemSettings;
use App\Enums\PermissionName;
use App\Exceptions\DomainException;
use App\Models\Setting;
use App\Models\User;
use App\Support\Authorization\Authorizer;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * System settings UI (docs/09 B21). Admin may view; Super Admin updates in MVP.
 */
#[Layout('layouts.app')]
#[Title('Pengaturan')]
class SettingsBoard extends Component
{
    use AuthorizesRequests;

    public int $orderPaymentTtlMinutes = Setting::ORDER_PAYMENT_TTL_DEFAULT;

    public int $kioskHeartbeatStaleSeconds = Setting::KIOSK_HEARTBEAT_STALE_DEFAULT;

    public int $kioskActivationTtlMinutes = Setting::KIOSK_ACTIVATION_TTL_DEFAULT;

    public bool $confirmDanger = false;

    public string $flashMessage = '';

    public string $errorMessage = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Setting::class);
        $this->loadFromStore();
    }

    public function save(UpdateSystemSettings $update): void
    {
        $this->authorize('update', Setting::class);

        $this->validate([
            'orderPaymentTtlMinutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'kioskHeartbeatStaleSeconds' => ['required', 'integer', 'min:30', 'max:3600'],
            'kioskActivationTtlMinutes' => ['required', 'integer', 'min:5', 'max:1440'],
            'confirmDanger' => ['accepted'],
        ], [
            'confirmDanger.accepted' => 'Centang konfirmasi sebelum menyimpan pengaturan sensitif.',
        ]);

        try {
            $update->handle($this->staff(), [
                Setting::ORDER_PAYMENT_TTL_MINUTES => $this->orderPaymentTtlMinutes,
                Setting::KIOSK_HEARTBEAT_STALE_SECONDS => $this->kioskHeartbeatStaleSeconds,
                Setting::KIOSK_ACTIVATION_TTL_MINUTES => $this->kioskActivationTtlMinutes,
            ], request());
        } catch (DomainException $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        $this->flashMessage = 'Pengaturan disimpan.';
        $this->errorMessage = '';
        $this->confirmDanger = false;
        $this->loadFromStore();
    }

    public function resetDefaults(): void
    {
        $this->authorize('update', Setting::class);

        $this->orderPaymentTtlMinutes = Setting::ORDER_PAYMENT_TTL_DEFAULT;
        $this->kioskHeartbeatStaleSeconds = Setting::KIOSK_HEARTBEAT_STALE_DEFAULT;
        $this->kioskActivationTtlMinutes = Setting::KIOSK_ACTIVATION_TTL_DEFAULT;
        $this->confirmDanger = false;
        $this->clearMessages();
    }

    public function render()
    {
        return view('livewire.settings.settings-board', [
            'canUpdate' => Authorizer::check($this->staff(), PermissionName::SettingsUpdate),
            'definitions' => UpdateSystemSettings::DEFINITIONS,
            'updatedBy' => Setting::query()
                ->whereIn('key', array_keys(UpdateSystemSettings::DEFINITIONS))
                ->with('updatedBy:id,name,email')
                ->orderByDesc('updated_at')
                ->get(),
        ]);
    }

    private function loadFromStore(): void
    {
        $this->orderPaymentTtlMinutes = Setting::orderPaymentTtlMinutes();
        $this->kioskHeartbeatStaleSeconds = Setting::heartbeatStaleSeconds();
        $this->kioskActivationTtlMinutes = Setting::activationTtlMinutes();
    }

    private function clearMessages(): void
    {
        $this->flashMessage = '';
        $this->errorMessage = '';
    }

    private function staff(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
