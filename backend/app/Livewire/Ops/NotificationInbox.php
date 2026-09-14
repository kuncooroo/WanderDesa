<?php

namespace App\Livewire\Ops;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Notifications\DatabaseNotification;
use Livewire\Component;

final class NotificationInbox extends Component
{
    public function markRead(string $id): void
    {
        $row = $this->staff()->notifications()->whereKey($id)->first();
        if ($row === null) {
            return;
        }

        $row->markAsRead();
    }

    public function markAllRead(): void
    {
        $this->staff()->unreadNotifications->markAsRead();
    }

    public function render()
    {
        $user = $this->staff();
        $items = $user->notifications()->latest()->limit(15)->get();

        return view('livewire.ops.notification-inbox', [
            'items' => $items,
            'unreadCount' => $user->unreadNotifications()->count(),
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

    public function hrefFor(DatabaseNotification $notification): ?string
    {
        $kind = is_array($notification->data) ? ($notification->data['kind'] ?? null) : null;

        return match ($kind) {
            'kiosk.offline' => route('dashboard.kiosks'),
            'payment.webhook_failed' => route('dashboard.payments'),
            default => null,
        };
    }
}
