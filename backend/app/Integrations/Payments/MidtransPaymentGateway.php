<?php

namespace App\Integrations\Payments;

use App\Enums\PaymentMethod;
use App\Exceptions\AuthException;
use App\Exceptions\DomainException;
use App\Models\Order;
use App\Models\Payment;
use App\Support\ApiTimestamp;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use JsonException;
use Throwable;

final class MidtransPaymentGateway implements PaymentGateway, QueriesRemotePaymentStatus, VerifiesPaymentWebhooks
{
    public function initiate(Payment $payment, Order $order): PaymentInitiation
    {
        $method = $payment->method instanceof PaymentMethod
            ? $payment->method
            : PaymentMethod::tryFrom((string) $payment->method);

        if ($method !== PaymentMethod::Qris) {
            throw new PaymentGatewayException(
                'Midtrans MVP only charges QRIS.',
                'payment.method_unavailable',
            );
        }

        $serverKey = $this->serverKey();

        try {
            $response = $this->client($serverKey)
                ->post('/v2/charge', [
                    'payment_type' => 'qris',
                    'transaction_details' => [
                        'order_id' => $payment->payment_number,
                        'gross_amount' => (int) $payment->amount,
                    ],
                    'qris' => [
                        'acquirer' => (string) config('payments.midtrans.qris_acquirer', 'gopay'),
                    ],
                ]);
        } catch (ConnectionException) {
            throw new PaymentGatewayException(
                'Midtrans charge unreachable.',
                'upstream.payment_provider_unavailable',
            );
        }

        if ($response->failed()) {
            Log::warning('payment.midtrans.charge_failed', [
                'payment_id' => $payment->id,
                'status' => $response->status(),
            ]);

            throw new PaymentGatewayException(
                'Midtrans charge failed.',
                'upstream.payment_provider_unavailable',
            );
        }

        /** @var array<string, mixed> $body */
        $body = $response->json() ?? [];
        $statusCode = (string) ($body['status_code'] ?? '');
        $qrString = $body['qr_string'] ?? null;
        $transactionId = $body['transaction_id'] ?? null;

        if (! in_array($statusCode, ['200', '201'], true)
            || ! is_string($qrString)
            || $qrString === ''
            || ! is_string($transactionId)
            || $transactionId === '') {
            Log::warning('payment.midtrans.charge_invalid', [
                'payment_id' => $payment->id,
                'status_code' => $statusCode,
            ]);

            throw new PaymentGatewayException(
                'Midtrans did not return a QRIS payload.',
                'upstream.payment_provider_unavailable',
            );
        }

        $expiresAt = $this->parseExpireTime($body['expiry_time'] ?? $body['expire_time'] ?? null)
            ?? $order->expires_at
            ?? now()->addMinutes(15);

        return new PaymentInitiation(
            provider: 'midtrans',
            providerPaymentId: $transactionId,
            providerReference: $payment->payment_number,
            nextAction: [
                'type' => 'display_qr',
                'qr_content' => $qrString,
                'expires_at' => ApiTimestamp::utc($expiresAt),
            ],
        );
    }

    public function refund(Payment $payment): void
    {
        $serverKey = $this->serverKey();
        $orderId = $payment->provider_reference ?: $payment->payment_number;

        try {
            $response = $this->client($serverKey)
                ->post('/v2/'.$orderId.'/refund', [
                    'refund_key' => 'refund-'.$payment->payment_number,
                    'amount' => (int) $payment->amount,
                    'reason' => 'WanderDesa refund',
                ]);
        } catch (ConnectionException $e) {
            throw new PaymentGatewayException(
                'Midtrans refund unreachable.',
                'upstream.payment_provider_unavailable',
            );
        }

        if ($response->failed()) {
            Log::warning('payment.midtrans.refund_failed', [
                'payment_id' => $payment->id,
                'status' => $response->status(),
            ]);

            throw new PaymentGatewayException(
                'Midtrans refund failed.',
                'upstream.payment_provider_unavailable',
            );
        }
    }

    public function verifyWebhookSignature(string $rawBody, ?string $signatureHeader): void
    {
        unset($signatureHeader);

        $serverKey = $this->serverKey();

        try {
            /** @var array<string, mixed> $payload */
            $payload = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new AuthException(
                'webhook.signature_invalid',
                'Webhook payload is not valid JSON.',
                401,
            );
        }

        if (! is_array($payload) || array_is_list($payload)) {
            throw new AuthException(
                'webhook.signature_invalid',
                'Webhook payload must be a JSON object.',
                401,
            );
        }

        $provided = $payload['signature_key'] ?? null;
        $orderId = $payload['order_id'] ?? null;
        $statusCode = $payload['status_code'] ?? null;
        $grossAmount = $payload['gross_amount'] ?? null;

        if (! is_string($provided) || $provided === ''
            || ! is_string($orderId) || $orderId === ''
            || ! is_string($statusCode) || $statusCode === ''
            || (! is_string($grossAmount) && ! is_numeric($grossAmount))) {
            throw new AuthException(
                'webhook.signature_invalid',
                'Webhook signature fields are missing.',
                401,
            );
        }

        $expected = hash('sha512', $orderId.$statusCode.(string) $grossAmount.$serverKey);

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
        $transactionId = $payload['transaction_id'] ?? null;
        $orderId = $payload['order_id'] ?? null;
        $transactionStatus = strtolower((string) ($payload['transaction_status'] ?? ''));
        $fraudStatus = strtolower((string) ($payload['fraud_status'] ?? 'accept'));
        $statusCode = (string) ($payload['status_code'] ?? '');

        if (! is_string($transactionId) || $transactionId === '') {
            throw new DomainException(
                'validation.failed',
                'Webhook transaction_id is required.',
                422,
                [[
                    'field' => 'transaction_id',
                    'code' => 'validation.required',
                    'message' => 'Webhook transaction_id is required.',
                ]],
            );
        }

        $eventId = $transactionId.':'.$transactionStatus.':'.$statusCode;
        if (strlen($eventId) > 120) {
            $eventId = hash('sha256', $eventId);
        }

        $status = $this->mapTransactionStatus($transactionStatus, $fraudStatus);
        $reportedAmount = $this->parseGrossAmount($payload['gross_amount'] ?? null);

        return new DecodedWebhook(
            eventId: $eventId,
            status: $status,
            paymentNumber: is_string($orderId) && $orderId !== '' ? $orderId : null,
            providerPaymentId: $transactionId,
            reportedAmount: $reportedAmount,
        );
    }

    public function fetchRemoteStatus(Payment $payment): RemotePaymentStatus
    {
        $serverKey = $this->serverKey();
        $orderId = $payment->provider_reference ?: $payment->payment_number;

        try {
            $response = $this->client($serverKey)
                ->retry([100, 300], 0, function (Throwable $exception): bool {
                    return $exception instanceof ConnectionException
                        || ($exception instanceof RequestException
                            && ($exception->response?->serverError() || $exception->response?->status() === 429));
                })
                ->get('/v2/'.$orderId.'/status');
        } catch (ConnectionException) {
            return RemotePaymentStatus::Unknown;
        }

        if ($response->failed()) {
            return RemotePaymentStatus::Unknown;
        }

        /** @var array<string, mixed> $body */
        $body = $response->json() ?? [];
        $transactionStatus = strtolower((string) ($body['transaction_status'] ?? ''));
        $fraudStatus = strtolower((string) ($body['fraud_status'] ?? 'accept'));

        return $this->mapTransactionStatus($transactionStatus, $fraudStatus);
    }

    private function client(string $serverKey): PendingRequest
    {
        $base = config('payments.midtrans.is_production')
            ? 'https://api.midtrans.com'
            : 'https://api.sandbox.midtrans.com';

        return Http::baseUrl($base)
            ->withBasicAuth($serverKey, '')
            ->acceptJson()
            ->asJson()
            ->connectTimeout((int) config('payments.midtrans.connect_timeout', 3))
            ->timeout((int) config('payments.midtrans.timeout', 15));
    }

    private function serverKey(): string
    {
        $key = (string) config('payments.midtrans.server_key', '');

        if ($key === '') {
            throw new PaymentGatewayException(
                'Midtrans server key is not configured.',
                'upstream.payment_provider_unavailable',
            );
        }

        return $key;
    }

    private function mapTransactionStatus(string $transactionStatus, string $fraudStatus): RemotePaymentStatus
    {
        return match ($transactionStatus) {
            'settlement' => RemotePaymentStatus::Paid,
            'capture' => $fraudStatus === 'challenge'
                ? RemotePaymentStatus::Pending
                : RemotePaymentStatus::Paid,
            'pending' => RemotePaymentStatus::Pending,
            'deny', 'cancel', 'failure' => RemotePaymentStatus::Failed,
            'expire' => RemotePaymentStatus::Expired,
            default => RemotePaymentStatus::Unknown,
        };
    }

    private function parseGrossAmount(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            return (int) round($value);
        }

        if (is_string($value) && is_numeric($value)) {
            return (int) round((float) $value);
        }

        return null;
    }

    private function parseExpireTime(mixed $value): ?CarbonInterface
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value, 'Asia/Jakarta');
        } catch (Throwable) {
            return null;
        }
    }
}
