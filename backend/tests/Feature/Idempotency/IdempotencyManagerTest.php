<?php

namespace Tests\Feature\Idempotency;

use App\Enums\IdempotencyActorType;
use App\Enums\IdempotencyScope;
use App\Exceptions\DomainException;
use App\Models\IdempotencyKey;
use App\Models\Order;
use App\Support\Idempotency\IdempotencyActor;
use App\Support\Idempotency\IdempotencyManager;
use App\Support\Idempotency\IdempotencyOutcome;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class IdempotencyManagerTest extends TestCase
{
    use RefreshDatabase;

    private IdempotencyManager $manager;

    private IdempotencyActor $deviceA;

    private IdempotencyActor $deviceB;

    private IdempotencyActor $userA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = $this->app->make(IdempotencyManager::class);
        $this->deviceA = new IdempotencyActor(IdempotencyActorType::Device, 11);
        $this->deviceB = new IdempotencyActor(IdempotencyActorType::Device, 22);
        $this->userA = new IdempotencyActor(IdempotencyActorType::User, 11);
    }

    public function test_replay_with_same_key_and_payload_returns_original_resource_without_duplicate(): void
    {
        $executions = 0;
        $payload = ['destination_id' => 1, 'items' => [['ticket_type_id' => 4, 'quantity' => 2]]];

        $execute = function () use (&$executions): IdempotencyOutcome {
            $executions++;
            $order = Order::factory()->create(['customer_note' => 'first']);

            return new IdempotencyOutcome('order', (int) $order->id, 201, $order);
        };

        $first = $this->manager->run('attempt-1', IdempotencyScope::OrderCreate, $this->deviceA, $payload, $execute);
        $second = $this->manager->run('attempt-1', IdempotencyScope::OrderCreate, $this->deviceA, $payload, $execute);

        $this->assertSame(1, $executions);
        $this->assertFalse($first->replayed);
        $this->assertTrue($second->replayed);
        $this->assertSame($first->resourceId, $second->resourceId);
        $this->assertSame(201, $second->responseCode);
        $this->assertSame(1, Order::query()->count());
        $this->assertSame(1, IdempotencyKey::query()->count());
    }

    public function test_same_key_with_different_payload_conflicts(): void
    {
        $order = Order::factory()->create();

        $this->manager->run(
            'attempt-1',
            IdempotencyScope::OrderCreate,
            $this->deviceA,
            ['destination_id' => 1],
            fn (): IdempotencyOutcome => new IdempotencyOutcome('order', (int) $order->id, 201, $order),
        );

        try {
            $this->manager->run(
                'attempt-1',
                IdempotencyScope::OrderCreate,
                $this->deviceA,
                ['destination_id' => 2],
                fn (): IdempotencyOutcome => new IdempotencyOutcome('order', 999, 201),
            );
            $this->fail('Expected payload conflict.');
        } catch (DomainException $e) {
            $this->assertSame('idempotency.payload_conflict', $e->errorCode);
            $this->assertSame(409, $e->statusCode);
        }

        $this->assertSame(1, Order::query()->count());
    }

    public function test_same_key_is_isolated_across_actors(): void
    {
        $payload = ['destination_id' => 1];
        $executions = 0;

        $execute = function () use (&$executions): IdempotencyOutcome {
            $executions++;
            $order = Order::factory()->create();

            return new IdempotencyOutcome('order', (int) $order->id, 201, $order);
        };

        $deviceResult = $this->manager->run('shared-key', IdempotencyScope::OrderCreate, $this->deviceA, $payload, $execute);
        $otherDevice = $this->manager->run('shared-key', IdempotencyScope::OrderCreate, $this->deviceB, $payload, $execute);
        $sameIdUser = $this->manager->run('shared-key', IdempotencyScope::OrderCreate, $this->userA, $payload, $execute);

        $this->assertSame(3, $executions);
        $this->assertNotSame($deviceResult->resourceId, $otherDevice->resourceId);
        $this->assertNotSame($deviceResult->resourceId, $sameIdUser->resourceId);
        $this->assertSame(3, Order::query()->count());
        $this->assertSame(3, IdempotencyKey::query()->count());
    }

    public function test_concurrent_same_key_is_rejected_while_in_progress(): void
    {
        $payload = ['destination_id' => 1];

        $this->manager->begin('attempt-lock', IdempotencyScope::PaymentInitiate, $this->deviceA, $payload);

        try {
            $this->manager->begin('attempt-lock', IdempotencyScope::PaymentInitiate, $this->deviceA, $payload);
            $this->fail('Expected in-progress conflict.');
        } catch (DomainException $e) {
            $this->assertSame('idempotency.in_progress', $e->errorCode);
            $this->assertSame(409, $e->statusCode);
        }
    }

    public function test_stale_in_progress_lock_can_be_taken_over(): void
    {
        $payload = ['method' => 'digital'];
        $reservation = $this->manager->begin('attempt-stale', IdempotencyScope::PaymentInitiate, $this->deviceA, $payload);

        $this->assertTrue($reservation->record->isInProgress());

        $this->travel(IdempotencyManager::LOCK_TTL_SECONDS + 1)->seconds();

        $executions = 0;
        $result = $this->manager->run(
            'attempt-stale',
            IdempotencyScope::PaymentInitiate,
            $this->deviceA,
            $payload,
            function () use (&$executions): IdempotencyOutcome {
                $executions++;

                return new IdempotencyOutcome('payment', 55, 201);
            },
        );

        $this->assertSame(1, $executions);
        $this->assertFalse($result->replayed);
        $this->assertSame(55, $result->resourceId);
        $this->assertSame(1, IdempotencyKey::query()->count());
        $this->assertTrue(IdempotencyKey::query()->first()?->isCompleted());
    }

    public function test_failed_execute_releases_lock_so_retry_can_proceed(): void
    {
        $payload = ['destination_id' => 1];

        try {
            $this->manager->run(
                'attempt-fail',
                IdempotencyScope::OrderCreate,
                $this->deviceA,
                $payload,
                function (): IdempotencyOutcome {
                    throw new RuntimeException('pricing exploded');
                },
            );
            $this->fail('Expected execute failure.');
        } catch (RuntimeException $e) {
            $this->assertSame('pricing exploded', $e->getMessage());
        }

        $this->assertSame(0, IdempotencyKey::query()->count());

        $result = $this->manager->run(
            'attempt-fail',
            IdempotencyScope::OrderCreate,
            $this->deviceA,
            $payload,
            function (): IdempotencyOutcome {
                $order = Order::factory()->create();

                return new IdempotencyOutcome('order', (int) $order->id, 201, $order);
            },
        );

        $this->assertFalse($result->replayed);
        $this->assertSame(1, Order::query()->count());
        $this->assertSame(1, IdempotencyKey::query()->count());
    }

    public function test_begin_commit_replay_helpers_round_trip(): void
    {
        $payload = ['reason' => 'visitor request'];
        $reservation = $this->manager->begin('refund-1', IdempotencyScope::PaymentRefund, $this->userA, $payload);

        $this->assertTrue($reservation->isFresh());

        $this->manager->commit($reservation->record, new IdempotencyOutcome('payment', 9, 200));

        $replayReservation = $this->manager->begin('refund-1', IdempotencyScope::PaymentRefund, $this->userA, $payload);
        $replay = $this->manager->replay($replayReservation->record);

        $this->assertTrue($replayReservation->isReplay());
        $this->assertTrue($replay->replayed);
        $this->assertSame('payment', $replay->resourceType);
        $this->assertSame(9, $replay->resourceId);
        $this->assertSame(200, $replay->responseCode);
    }

    public function test_different_scopes_do_not_share_a_key(): void
    {
        $payload = ['id' => 1];
        $order = Order::factory()->create();

        $this->manager->run(
            'multi-scope',
            IdempotencyScope::OrderCreate,
            $this->deviceA,
            $payload,
            fn (): IdempotencyOutcome => new IdempotencyOutcome('order', (int) $order->id, 201, $order),
        );

        $payment = $this->manager->run(
            'multi-scope',
            IdempotencyScope::PaymentInitiate,
            $this->deviceA,
            $payload,
            fn (): IdempotencyOutcome => new IdempotencyOutcome('payment', 70, 201),
        );

        $this->assertFalse($payment->replayed);
        $this->assertSame(2, IdempotencyKey::query()->count());
    }

    public function test_rejects_keys_longer_than_128_characters(): void
    {
        $this->expectException(DomainException::class);

        $this->manager->run(
            str_repeat('k', IdempotencyManager::MAX_KEY_LENGTH + 1),
            IdempotencyScope::CheckInCreate,
            $this->userA,
            ['qr_payload' => 'x'],
            fn (): IdempotencyOutcome => new IdempotencyOutcome('check_in', 1, 200),
        );
    }
}
