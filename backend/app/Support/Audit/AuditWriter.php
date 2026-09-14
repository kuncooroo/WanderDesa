<?php

namespace App\Support\Audit;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Append-only business audit writer (not technical application logs).
 *
 * Fail-closed policy (docs/05 §32): critical money/commerce audits must be written
 * inside the same DB transaction as the mutation via {@see writeCritical()}. A failed
 * insert rolls back the business change. Never catch-and-skip forever.
 *
 * Secrets (passwords, tokens, activation secrets, …) are redacted before persistence.
 */
class AuditWriter
{
    /**
     * Keys (case-insensitive, nested) that must never appear in audit payloads.
     *
     * @var list<string>
     */
    private const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'current_password',
        'token',
        'plain_text_token',
        'access_token',
        'refresh_token',
        'activation_secret',
        'activation_code',
        'secret',
        'api_key',
        'apikey',
        'webhook_secret',
        'client_secret',
        'authorization',
        'pin',
        'otp',
        'private_key',
    ];

    private const REDACTED = '[REDACTED]';

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     * @param  array<string, mixed>|null  $meta
     */
    public function write(
        string $action,
        string $actorType,
        ?int $actorId = null,
        ?string $entityType = null,
        ?int $entityId = null,
        ?array $before = null,
        ?array $after = null,
        ?array $meta = null,
        ?Request $request = null,
        ?int $destinationId = null,
    ): AuditLog {
        return $this->persist(
            action: $action,
            actorType: $actorType,
            actorId: $actorId,
            entityType: $entityType,
            entityId: $entityId,
            before: $before,
            after: $after,
            meta: $meta,
            request: $request,
            destinationId: $destinationId,
            critical: false,
        );
    }

    /**
     * Money / commerce audit. Prefer calling inside an open DB transaction so
     * insert failure rolls back the authoritative mutation (fail-closed).
     *
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     * @param  array<string, mixed>|null  $meta
     */
    public function writeCritical(
        string $action,
        string $actorType,
        ?int $actorId = null,
        ?string $entityType = null,
        ?int $entityId = null,
        ?array $before = null,
        ?array $after = null,
        ?array $meta = null,
        ?Request $request = null,
        ?int $destinationId = null,
    ): AuditLog {
        if (DB::transactionLevel() < 1) {
            Log::warning('audit.critical_outside_transaction', [
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
            ]);
        }

        return $this->persist(
            action: $action,
            actorType: $actorType,
            actorId: $actorId,
            entityType: $entityType,
            entityId: $entityId,
            before: $before,
            after: $after,
            meta: $meta,
            request: $request,
            destinationId: $destinationId,
            critical: true,
        );
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array<string, mixed>|null
     */
    public function redact(?array $payload): ?array
    {
        if ($payload === null) {
            return null;
        }

        return $this->redactArray($payload);
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     * @param  array<string, mixed>|null  $meta
     */
    private function persist(
        string $action,
        string $actorType,
        ?int $actorId,
        ?string $entityType,
        ?int $entityId,
        ?array $before,
        ?array $after,
        ?array $meta,
        ?Request $request,
        ?int $destinationId,
        bool $critical,
    ): AuditLog {
        $request ??= request();

        try {
            return AuditLog::query()->create([
                'actor_type' => $actorType,
                'actor_id' => $actorId,
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'destination_id' => $destinationId ?? ($meta['destination_id'] ?? null),
                'ip_address' => $request?->ip(),
                'user_agent' => $this->truncateUserAgent($request?->userAgent()),
                'before_json' => $this->redact($before),
                'after_json' => $this->redact($after),
                'meta_json' => $this->redact($meta),
            ]);
        } catch (Throwable $e) {
            Log::error('audit.write_failed', [
                'action' => $action,
                'critical' => $critical,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'message' => $e->getMessage(),
            ]);

            // Fail-closed: never swallow — critical or not. Callers in a transaction roll back.
            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function redactArray(array $payload): array
    {
        $clean = [];

        foreach ($payload as $key => $value) {
            $keyString = (string) $key;

            if ($this->isSensitiveKey($keyString)) {
                $clean[$keyString] = self::REDACTED;

                continue;
            }

            if (is_array($value)) {
                /** @var array<string, mixed> $value */
                $clean[$keyString] = $this->redactArray($value);

                continue;
            }

            $clean[$keyString] = $value;
        }

        return $clean;
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower($key);

        foreach (self::SENSITIVE_KEYS as $sensitive) {
            if ($normalized === $sensitive || str_contains($normalized, $sensitive)) {
                return true;
            }
        }

        return false;
    }

    private function truncateUserAgent(?string $userAgent): ?string
    {
        if ($userAgent === null) {
            return null;
        }

        return mb_substr($userAgent, 0, 255);
    }
}
