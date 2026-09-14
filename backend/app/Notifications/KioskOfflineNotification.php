<?php

namespace App\Notifications;

use App\Models\Device;
use App\Support\ApiTimestamp;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class KioskOfflineNotification extends Notification
{
    public function __construct(
        public Device $device,
        public int $staleAfterSeconds,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if (config('ops.notifications.mail')) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'kiosk.offline',
            'title' => 'Kiosk offline',
            'message' => $this->summary(),
            'device_pk' => (int) $this->device->id,
            'device_id' => $this->device->device_id,
            'destination_id' => $this->device->destination_id,
            'stale_after_seconds' => $this->staleAfterSeconds,
            'last_heartbeat_at' => ApiTimestamp::utc($this->device->last_heartbeat_at),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('[WanderDesa] Kiosk offline: '.$this->device->device_id)
            ->line($this->summary())
            ->line('Periksa armada kiosk di dashboard. Tidak ada rahasia perangkat dalam pesan ini.');
    }

    private function summary(): string
    {
        $name = $this->device->name !== '' ? $this->device->name : $this->device->device_id;

        return "Kiosk {$name} ({$this->device->device_id}) melewati ambang heartbeat {$this->staleAfterSeconds}s.";
    }
}
