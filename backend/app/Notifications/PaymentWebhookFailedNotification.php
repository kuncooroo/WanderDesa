<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class PaymentWebhookFailedNotification extends Notification
{
    public function __construct(
        public string $provider,
        public string $eventId,
        public string $exceptionClass,
        public ?int $paymentId = null,
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
            'kind' => 'payment.webhook_failed',
            'title' => 'Webhook pembayaran gagal diproses',
            'message' => $this->summary(),
            'provider' => $this->provider,
            'event_id' => $this->eventId,
            'exception_class' => $this->exceptionClass,
            'payment_id' => $this->paymentId,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('[WanderDesa] Webhook pembayaran gagal')
            ->line($this->summary())
            ->line('Payload penyedia dan tanda tangan tidak disertakan.');
    }

    private function summary(): string
    {
        return "Pemrosesan webhook {$this->provider} gagal (event {$this->eventId}).";
    }
}
