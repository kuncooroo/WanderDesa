<?php

namespace App\Jobs;

use App\Actions\Ops\NotifyOpsAlerts;
use App\Actions\Payments\ApplyProviderPaymentEvent;
use App\Enums\WebhookProcessStatus;
use App\Integrations\Payments\PaymentGateway;
use App\Integrations\Payments\RemotePaymentStatus;
use App\Integrations\Payments\VerifiesPaymentWebhooks;
use App\Models\Payment;
use App\Models\PaymentWebhookEvent;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ProcessPaymentWebhook implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public int $webhookEventId,
        public array $payload,
        public string $provider,
    ) {}

    public function uniqueId(): string
    {
        return $this->provider.':'.$this->webhookEventId;
    }

    public function handle(
        PaymentGateway $gateway,
        ApplyProviderPaymentEvent $apply,
        NotifyOpsAlerts $notify,
    ): void {
        $failure = null;

        DB::transaction(function () use ($gateway, $apply, &$failure): void {
            /** @var PaymentWebhookEvent|null $event */
            $event = PaymentWebhookEvent::query()
                ->whereKey($this->webhookEventId)
                ->lockForUpdate()
                ->first();

            if ($event === null) {
                return;
            }

            if (in_array($event->process_status, [
                WebhookProcessStatus::Processed,
                WebhookProcessStatus::Ignored,
            ], true)) {
                return;
            }

            $alreadyFailed = $event->process_status === WebhookProcessStatus::Failed;

            if (! $gateway instanceof VerifiesPaymentWebhooks) {
                $this->finish($event, WebhookProcessStatus::Ignored);

                return;
            }

            $resolvedPaymentId = $event->payment_id;

            try {
                $decoded = $gateway->decodeWebhookPayload($this->payload);
                $payment = $this->resolvePayment($decoded->paymentNumber, $decoded->providerPaymentId);
                $resolvedPaymentId = $payment?->id ?? $resolvedPaymentId;

                if ($payment === null || $decoded->status === RemotePaymentStatus::Unknown) {
                    $this->finish($event, WebhookProcessStatus::Ignored);
                    Log::info('payment.webhook.ignored', [
                        'event_id' => $event->event_id,
                        'provider' => $this->provider,
                        'reason' => $payment === null ? 'unknown_payment' : 'unknown_type',
                    ]);

                    return;
                }

                $apply->handle(
                    $payment,
                    $decoded->status,
                    source: 'webhook',
                    reportedAmount: $decoded->reportedAmount,
                );

                $this->finish($event, WebhookProcessStatus::Processed, (int) $payment->id);
            } catch (Throwable $e) {
                $this->finish($event, WebhookProcessStatus::Failed, $resolvedPaymentId);
                Log::error('payment.webhook.process_failed', [
                    'event_id' => $event->event_id,
                    'provider' => $this->provider,
                    'exception' => $e::class,
                ]);

                if (! $alreadyFailed) {
                    $failure = [
                        'event_id' => $event->event_id,
                        'exception' => $e::class,
                        'payment_id' => $resolvedPaymentId,
                    ];
                }
            }
        });

        if ($failure !== null) {
            $notify->paymentWebhookFailed(
                $this->provider,
                $failure['event_id'],
                $failure['exception'],
                $failure['payment_id'],
            );
        }
    }

    private function resolvePayment(?string $paymentNumber, ?string $providerPaymentId): ?Payment
    {
        $query = Payment::query();

        if ($providerPaymentId !== null) {
            $match = (clone $query)->where('provider_payment_id', $providerPaymentId)->first();
            if ($match !== null) {
                return $match;
            }
        }

        if ($paymentNumber !== null) {
            return Payment::query()->where('payment_number', $paymentNumber)->first();
        }

        return null;
    }

    private function finish(
        PaymentWebhookEvent $event,
        WebhookProcessStatus $status,
        ?int $paymentId = null,
    ): void {
        $event->forceFill([
            'process_status' => $status,
            'processed_at' => now(),
            'payment_id' => $paymentId ?? $event->payment_id,
        ])->save();
    }
}
