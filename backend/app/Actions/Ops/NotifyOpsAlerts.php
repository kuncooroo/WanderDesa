<?php

namespace App\Actions\Ops;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\Device;
use App\Models\User;
use App\Notifications\KioskOfflineNotification;
use App\Notifications\PaymentWebhookFailedNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

final class NotifyOpsAlerts
{
    public function kioskOffline(Device $device, int $staleSeconds): void
    {
        $this->send(
            $this->usersWithPermission(PermissionName::KiosksView),
            new KioskOfflineNotification($device, $staleSeconds),
        );
    }

    public function paymentWebhookFailed(
        string $provider,
        string $eventId,
        string $exceptionClass,
        ?int $paymentId = null,
    ): void {
        $this->send(
            $this->usersWithRoles([RoleName::Admin, RoleName::SuperAdmin]),
            new PaymentWebhookFailedNotification(
                $provider,
                $eventId,
                $exceptionClass,
                $paymentId,
            ),
        );
    }

    /**
     * @param  Collection<int, User>  $recipients
     */
    private function send(Collection $recipients, KioskOfflineNotification|PaymentWebhookFailedNotification $notification): void
    {
        foreach ($recipients as $user) {
            try {
                $user->notify($notification);
            } catch (Throwable $e) {
                Log::warning('ops.notification.dispatch_failed', [
                    'exception' => $e::class,
                    'notification' => $notification::class,
                    'user_id' => $user->id,
                ]);
            }
        }
    }

    /**
     * @return Collection<int, User>
     */
    private function usersWithPermission(PermissionName $permission): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->whereHas(
                'roles.permissions',
                fn ($query) => $query->where('permissions.name', $permission->value),
            )
            ->get();
    }

    /**
     * @param  list<RoleName>  $roles
     * @return Collection<int, User>
     */
    private function usersWithRoles(array $roles): Collection
    {
        $names = array_map(static fn (RoleName $role): string => $role->value, $roles);

        return User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn ($query) => $query->whereIn('roles.name', $names))
            ->get();
    }
}
