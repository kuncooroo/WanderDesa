<?php

namespace App\Actions\Payments;

use App\Enums\WebhookProcessStatus;
use App\Exceptions\AuthException;
use App\Exceptions\DomainException;
use App\Integrations\Payments\PaymentGateway;
use App\Integrations\Payments\VerifiesPaymentWebhooks;
use App\Jobs\ProcessPaymentWebhook;
use App\Models\PaymentWebhookEvent;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use JsonException;

final class AcceptPaymentWebhook
{
    public function __construct(
        private readonly PaymentGateway $gateway,
    ) {}

    public function handle(string $provider, Request $request): PaymentWebhookEvent
    {
        $provider = strtolower($provider);

        if ($provider !== 'sandbox' || ! $this->gateway instanceof VerifiesPaymentWebhooks) {
            throw new DomainException(
                'resource.not_found',
                'Unknown payment provider.',
                404,
            );
        }

        $rawBody = $request->getContent();
        $signatureHeader = $request->header((string) config('payments.signature_header'));

        try {
            $this->gateway->verifyWebhookSignature($rawBody, $signatureHeader);
        } catch (AuthException $e) {
            Log::warning('payment.webhook.signature_invalid', [
                'provider' => $provider,
                'ip' => $request->ip(),
            ]);

            throw $e;
        }

        $payload = $this->decodeJson($rawBody);
        $decoded = $this->gateway->decodeWebhookPayload($payload);
        $payloadHash = hash('sha256', $rawBody);

        try {
            $event = PaymentWebhookEvent::query()->create([
                'provider' => $provider,
                'event_id' => $decoded->eventId,
                'payload_hash' => $payloadHash,
                'process_status' => WebhookProcessStatus::Received,
            ]);
        } catch (UniqueConstraintViolationException) {
            return $this->replayOrRedispatch($provider, $decoded->eventId, $payloadHash, $payload);
        } catch (QueryException $e) {
            if (! $this->isUniqueConstraint($e)) {
                throw $e;
            }

            return $this->replayOrRedispatch($provider, $decoded->eventId, $payloadHash, $payload);
        }

        Log::info('payment.webhook.accepted', [
            'provider' => $provider,
            'event_id' => $decoded->eventId,
            'payload_hash' => $payloadHash,
        ]);

        ProcessPaymentWebhook::dispatch($event->id, $payload, $provider);

        return $event;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(string $rawBody): array
    {
        if ($rawBody === '') {
            throw new DomainException(
                'request.malformed',
                'Webhook payload is empty.',
                422,
            );
        }

        try {
            $decoded = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new DomainException(
                'request.malformed',
                'Webhook payload is not valid JSON.',
                422,
            );
        }

        if (! is_array($decoded) || array_is_list($decoded)) {
            throw new DomainException(
                'request.malformed',
                'Webhook payload must be a JSON object.',
                422,
            );
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function replayOrRedispatch(
        string $provider,
        string $eventId,
        string $payloadHash,
        array $payload,
    ): PaymentWebhookEvent {
        $event = PaymentWebhookEvent::query()
            ->where('provider', $provider)
            ->where('event_id', $eventId)
            ->firstOrFail();

        if ($event->payload_hash !== $payloadHash) {
            throw new DomainException(
                'idempotency.payload_conflict',
                'Webhook event_id was reused with a different payload.',
                409,
            );
        }

        if (in_array($event->process_status, [
            WebhookProcessStatus::Processed,
            WebhookProcessStatus::Ignored,
        ], true)) {
            return $event;
        }

        ProcessPaymentWebhook::dispatch($event->id, $payload, $provider);

        return $event;
    }

    private function isUniqueConstraint(QueryException $e): bool
    {
        if ($e instanceof UniqueConstraintViolationException) {
            return true;
        }

        $sqlState = $e->errorInfo[0] ?? $e->getCode();

        return $sqlState === '23000' || str_contains(strtolower($e->getMessage()), 'unique');
    }
}
