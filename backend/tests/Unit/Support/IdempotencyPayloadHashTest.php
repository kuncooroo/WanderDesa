<?php

namespace Tests\Unit\Support;

use App\Enums\IdempotencyActorType;
use App\Enums\IdempotencyScope;
use App\Support\Idempotency\IdempotencyActor;
use App\Support\Idempotency\IdempotencyManager;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IdempotencyPayloadHashTest extends TestCase
{
    private IdempotencyManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = new IdempotencyManager;
    }

    #[Test]
    public function associative_key_order_does_not_change_payload_hash(): void
    {
        $left = $this->manager->hashPayload([
            'items' => [
                ['ticket_type_id' => 2, 'quantity' => 1],
            ],
            'destination_id' => 1,
        ]);
        $right = $this->manager->hashPayload([
            'destination_id' => 1,
            'items' => [
                ['ticket_type_id' => 2, 'quantity' => 1],
            ],
        ]);

        $this->assertSame($left, $right);
    }

    #[Test]
    public function list_order_is_preserved_in_payload_hash(): void
    {
        $left = $this->manager->hashPayload([
            'items' => [
                ['ticket_type_id' => 1, 'quantity' => 1],
                ['ticket_type_id' => 2, 'quantity' => 1],
            ],
        ]);
        $right = $this->manager->hashPayload([
            'items' => [
                ['ticket_type_id' => 2, 'quantity' => 1],
                ['ticket_type_id' => 1, 'quantity' => 1],
            ],
        ]);

        $this->assertNotSame($left, $right);
    }

    #[Test]
    public function key_hash_includes_actor_scope(): void
    {
        $device = new IdempotencyActor(IdempotencyActorType::Device, 7);
        $user = new IdempotencyActor(IdempotencyActorType::User, 7);

        $this->assertNotSame(
            $this->manager->hashKey('same-key', $device),
            $this->manager->hashKey('same-key', $user),
        );
    }

    #[Test]
    public function required_endpoints_cover_orders_payments_checkins_and_refunds(): void
    {
        $paths = array_column(IdempotencyScope::requiredEndpoints(), 'path');

        $this->assertContains('/api/v1/orders', $paths);
        $this->assertContains('/api/v1/orders/{order_id}/payments', $paths);
        $this->assertContains('/api/v1/payments/{id}/confirm-cash', $paths);
        $this->assertContains('/api/v1/payments/{id}/refund', $paths);
        $this->assertContains('/api/v1/check-ins', $paths);
    }
}
