<?php

namespace App\Integrations\Payments;

use App\Exceptions\AuthException;
use App\Exceptions\DomainException;
use App\Models\Order;
use App\Models\Payment;
use App\Support\ApiTimestamp;

final class SandboxPaymentGateway implements PaymentGateway, QueriesRemotePaymentStatus, VerifiesPaymentWebhooks
{
    public function initiate(Payment $payment, Order $order): PaymentInitiation
    {
        $expiresAt = $order->expires_at ?? now()->addMinutes(15);

        return new PaymentInitiation(
            provider: 'sandbox',
            providerPaymentId: 'sandbox-'.$payment->payment_number,
            providerReference: $payment->payment_number,
            nextAction: [
                'type' => 'display_qr',
                'qr_content' => 'wanderdesa-sandbox:'.$payment->payment_number,
                'expires_at' => ApiTimestamp::utc($expiresAt),
            ],
        );
    }

    public function refund(Payment $payment): void
    {
        // Sandbox accepts full refunds without a remote call.
    }

    public function verifyWebhookSignature(string $rawBody, ?string $signatureHeader): void
    {
        $secret = (string) config('payments.webhook_secret', '');

        if ($secret === '') {
            throw new AuthException(
                'webhook.signature_invalid',
                'Webhook secret is not configured.',
                401,
            );
        }

        if (! is_string($signatureHeader) || $signatureHeader === '') {
            throw new AuthException(
                'webhook.signature_invalid',
                'Webhook signature is missing.',
                401,
            );
        }

        $provided = $this->normalizeSignature($signatureHeader);
        $expected = hash_hmac('sha256', $rawBody, $secret);

        if (! hash_equals($expected, $provided)) {
            throw new AuthException(
                'webhook.signature_invalid',
                'Webhook signature is invalid.',
                401,
            );
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function decodeWebhookPayload(array $payload): DecodedWebhook
    {
        $eventId = $payload['event_id'] ?? null;

        if (! is_string($eventId) || $eventId === '' || strlen($eventId) > 120) {
            throw new DomainException(
                'validation.failed',
                'Webhook event_id is required.',
                422,
                [[
                    'field' => 'event_id',
                    'code' => 'validation.required',
                    'message' => 'Webhook event_id is required.',
                ]],
            );
        }

        $type = strtolower((string) ($payload['type'] ?? $payload['event_type'] ?? ''));
        $status = match ($type) {
            'payment.paid', 'paid' => RemotePaymentStatus::Paid,
            'payment.failed', 'failed' => RemotePaymentStatus::Failed,
            'payment.expired', 'expired' => RemotePaymentStatus::Expired,
            default => RemotePaymentStatus::Unknown,
        };

        $paymentNumber = $payload['payment_number'] ?? null;
        $providerPaymentId = $payload['provider_payment_id'] ?? null;
        $reportedAmount = $payload['amount'] ?? null;

        return new DecodedWebhook(
            eventId: $eventId,
            status: $status,
            paymentNumber: is_string($paymentNumber) && $paymentNumber !== '' ? $paymentNumber : null,
            providerPaymentId: is_string($providerPaymentId) && $providerPaymentId !== '' ? $providerPaymentId : null,
            reportedAmount: is_numeric($reportedAmount) ? (int) $reportedAmount : null,
        );
    }

    public function fetchRemoteStatus(Payment $payment): RemotePaymentStatus
    {
        return RemotePaymentStatus::Unknown;
    }

    private function normalizeSignature(string $header): string
    {
        $header = trim($header);

        if (str_starts_with(strtolower($header), 'sha256=')) {
            return substr($header, 7);
        }

        return $header;
    }
}
