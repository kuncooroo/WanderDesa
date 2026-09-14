<?php

namespace App\Support\Idempotency;

use App\Enums\IdempotencyScope;
use App\Exceptions\DomainException;
use App\Models\IdempotencyKey;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use InvalidArgumentException;
use JsonException;
use Throwable;

final class IdempotencyManager
{
    public const HEADER = 'Idempotency-Key';

    public const MAX_KEY_LENGTH = 128;

    public const LOCK_TTL_SECONDS = 60;

    /**
     * Acquire a lock for this principal+action, or return a completed replay.
     *
     * Same key + same payload → replay the original resource.
     * Same key + different payload → 409 `idempotency.payload_conflict`.
     * Same key while still locked → 409 `idempotency.in_progress`.
     *
     * @param  array<string, mixed>  $payload
     */
    public function begin(
        string $key,
        IdempotencyScope|string $scope,
        IdempotencyActor $actor,
        array $payload,
    ): IdempotencyReservation {
        $normalizedKey = $this->normalizeKey($key);
        $scopeValue = $this->normalizeScope($scope);
        $keyHash = $this->hashKey($normalizedKey, $actor);
        $requestHash = $this->hashPayload($payload);

        $existing = $this->findRecord($scopeValue, $actor, $keyHash);

        if ($existing !== null) {
            return $this->handleExisting($existing, $requestHash);
        }

        try {
            $record = IdempotencyKey::query()->create([
                'key_hash' => $keyHash,
                'scope' => $scopeValue,
                'actor_type' => $actor->type->value,
                'actor_id' => $actor->id,
                'request_hash' => $requestHash,
                'locked_at' => now(),
            ]);

            return IdempotencyReservation::fresh($record);
        } catch (UniqueConstraintViolationException $e) {
            return $this->handleRace($scopeValue, $actor, $keyHash, $requestHash, $e);
        } catch (QueryException $e) {
            if (! $this->isUniqueConstraint($e)) {
                throw $e;
            }

            return $this->handleRace($scopeValue, $actor, $keyHash, $requestHash, $e);
        }
    }

    public function commit(IdempotencyKey $record, IdempotencyOutcome $outcome): IdempotencyKey
    {
        $record->forceFill([
            'resource_type' => $outcome->resourceType,
            'resource_id' => $outcome->resourceId,
            'response_code' => $outcome->responseCode,
            'locked_at' => null,
        ])->save();

        return $record->refresh();
    }

    public function replay(IdempotencyKey $record): IdempotencyResult
    {
        if (! $record->isCompleted()) {
            throw new DomainException(
                'idempotency.in_progress',
                'A request with this Idempotency-Key is already in progress.',
                409,
            );
        }

        return IdempotencyResult::replay($record);
    }

    /**
     * Release an uncommitted lock so the same key can be retried after failure.
     */
    public function release(IdempotencyKey $record): void
    {
        if ($record->isCompleted()) {
            return;
        }

        $record->delete();
    }

    /**
     * Begin + execute + commit, or replay the original resource without re-running.
     *
     * CreateOrder / InitiatePayment (and later check-in / refund) should call this
     * with a canonical business payload — never client money fields.
     *
     * @param  array<string, mixed>  $payload
     * @param  callable(): IdempotencyOutcome  $execute
     */
    public function run(
        string $key,
        IdempotencyScope|string $scope,
        IdempotencyActor $actor,
        array $payload,
        callable $execute,
    ): IdempotencyResult {
        $reservation = $this->begin($key, $scope, $actor, $payload);

        if ($reservation->isReplay()) {
            return $this->replay($reservation->record);
        }

        try {
            $outcome = $execute();
            $this->commit($reservation->record, $outcome);

            return IdempotencyResult::fromOutcome($outcome);
        } catch (Throwable $e) {
            $this->release($reservation->record);

            throw $e;
        }
    }

    public function keyFromRequest(Request $request): string
    {
        $fromAttribute = $request->attributes->get('idempotency_key');

        if (is_string($fromAttribute) && $fromAttribute !== '') {
            return $this->normalizeKey($fromAttribute);
        }

        $header = $request->header(self::HEADER);

        if (! is_string($header)) {
            throw new DomainException(
                'validation.failed',
                'The Idempotency-Key header is required.',
                422,
                [[
                    'field' => self::HEADER,
                    'code' => 'validation.required',
                    'message' => 'The Idempotency-Key header is required.',
                ]],
            );
        }

        return $this->normalizeKey($header);
    }

    /**
     * Hash of the raw key + actor scope (docs/06). Unique also includes scope/actor.
     */
    public function hashKey(string $key, IdempotencyActor $actor): string
    {
        return hash('sha256', $actor->type->value.'|'.$actor->id.'|'.$key);
    }

    /**
     * Canonical SHA-256 of the business payload. Associative keys are sorted;
     * list order is preserved so callers should canonicalize carts themselves.
     *
     * @param  array<string, mixed>  $payload
     */
    public function hashPayload(array $payload): string
    {
        try {
            $json = json_encode(
                $this->canonicalize($payload),
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            );
        } catch (JsonException $e) {
            throw new InvalidArgumentException('Idempotency payload could not be hashed.', 0, $e);
        }

        return hash('sha256', $json);
    }

    public function normalizeKey(string $key): string
    {
        $normalized = trim($key);

        if ($normalized === '') {
            throw new DomainException(
                'validation.failed',
                'The Idempotency-Key header is required.',
                422,
                [[
                    'field' => self::HEADER,
                    'code' => 'validation.required',
                    'message' => 'The Idempotency-Key header is required.',
                ]],
            );
        }

        if (strlen($normalized) > self::MAX_KEY_LENGTH) {
            throw new DomainException(
                'validation.failed',
                'The Idempotency-Key header may not be greater than '.self::MAX_KEY_LENGTH.' characters.',
                422,
                [[
                    'field' => self::HEADER,
                    'code' => 'validation.max',
                    'message' => 'The Idempotency-Key header may not be greater than '.self::MAX_KEY_LENGTH.' characters.',
                ]],
            );
        }

        return $normalized;
    }

    private function normalizeScope(IdempotencyScope|string $scope): string
    {
        $value = $scope instanceof IdempotencyScope ? $scope->value : $scope;

        if ($value === '' || strlen($value) > 40) {
            throw new InvalidArgumentException('Idempotency scope must be 1-40 characters.');
        }

        return $value;
    }

    private function findRecord(string $scope, IdempotencyActor $actor, string $keyHash): ?IdempotencyKey
    {
        return IdempotencyKey::query()
            ->where('scope', $scope)
            ->where('actor_type', $actor->type->value)
            ->where('actor_id', $actor->id)
            ->where('key_hash', $keyHash)
            ->first();
    }

    private function handleRace(
        string $scope,
        IdempotencyActor $actor,
        string $keyHash,
        string $requestHash,
        QueryException $previous,
    ): IdempotencyReservation {
        $existing = $this->findRecord($scope, $actor, $keyHash);

        if ($existing === null) {
            throw $previous;
        }

        return $this->handleExisting($existing, $requestHash);
    }

    private function handleExisting(IdempotencyKey $existing, string $requestHash): IdempotencyReservation
    {
        if ($existing->isCompleted()) {
            if ($existing->request_hash !== $requestHash) {
                $this->throwPayloadConflict();
            }

            return IdempotencyReservation::replay($existing);
        }

        if ($existing->isLockStale(self::LOCK_TTL_SECONDS)) {
            if ($existing->request_hash !== $requestHash) {
                $this->throwPayloadConflict();
            }

            $existing->forceFill(['locked_at' => now()])->save();

            return IdempotencyReservation::fresh($existing);
        }

        if ($existing->request_hash !== $requestHash) {
            $this->throwPayloadConflict();
        }

        throw new DomainException(
            'idempotency.in_progress',
            'A request with this Idempotency-Key is already in progress.',
            409,
        );
    }

    private function throwPayloadConflict(): never
    {
        throw new DomainException(
            'idempotency.payload_conflict',
            'Idempotency-Key was reused with a different payload.',
            409,
        );
    }

    private function isUniqueConstraint(QueryException $e): bool
    {
        if ($e instanceof UniqueConstraintViolationException) {
            return true;
        }

        $sqlState = $e->errorInfo[0] ?? $e->getCode();

        return $sqlState === '23000' || str_contains(strtolower($e->getMessage()), 'unique');
    }

    /**
     * @param  array<mixed>  $value
     * @return array<mixed>
     */
    private function canonicalize(array $value): array
    {
        if (array_is_list($value)) {
            return array_map(function (mixed $item): mixed {
                return is_array($item) ? $this->canonicalize($item) : $item;
            }, $value);
        }

        ksort($value);

        foreach ($value as $key => $item) {
            $value[$key] = is_array($item) ? $this->canonicalize($item) : $item;
        }

        return $value;
    }
}
