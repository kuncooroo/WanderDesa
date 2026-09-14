<?php

namespace App\Actions\Payments;

use App\Exceptions\DomainException;
use App\Integrations\Payments\RemotePaymentStatus;
use App\Models\Payment;
use Illuminate\Support\Facades\Log;

final class ApplyProviderPaymentEvent
{
    public function __construct(
        private readonly MarkPaymentPaid $markPaid,
        private readonly MarkPaymentFailed $markFailed,
        private readonly MarkPaymentExpired $markExpired,
    ) {}

    public function handle(
        Payment $payment,
        RemotePaymentStatus $status,
        string $source = 'webhook',
        ?int $reportedAmount = null,
    ): void {
        if ($payment->isCash()) {
            Log::info('payment.provider_event.ignored', [
                'payment_id' => $payment->id,
                'reason' => 'cash',
                'source' => $source,
            ]);

            return;
        }

        if ($reportedAmount !== null && $reportedAmount !== (int) $payment->amount) {
            Log::warning('payment.provider_event.amount_mismatch', [
                'payment_id' => $payment->id,
                'expected_amount' => $payment->amount,
                'source' => $source,
            ]);

            return;
        }

        try {
            match ($status) {
                RemotePaymentStatus::Paid => $this->markPaid->handle($payment, source: $source),
                RemotePaymentStatus::Failed => $this->markFailed->handle(
                    $payment,
                    failureCode: 'provider.failed',
                    failureMessage: 'Provider reported payment failed.',
                    source: $source,
                ),
                RemotePaymentStatus::Expired => $this->markExpired->handle($payment, $source),
                RemotePaymentStatus::Pending, RemotePaymentStatus::Unknown => null,
            };
        } catch (DomainException $e) {
            Log::info('payment.provider_event.ignored', [
                'payment_id' => $payment->id,
                'status' => $status->value,
                'source' => $source,
                'reason' => $e->errorCode,
            ]);
        }
    }
}
