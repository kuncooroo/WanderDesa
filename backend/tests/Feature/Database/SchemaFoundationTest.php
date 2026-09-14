<?php

namespace Tests\Feature\Database;

use App\Models\AuditLog;
use App\Models\CheckIn;
use App\Models\Destination;
use App\Models\Device;
use App\Models\IdempotencyKey;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentWebhookEvent;
use App\Models\Ticket;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\TestCase;

class SchemaFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_mvp_tables_exist(): void
    {
        foreach ([
            'users',
            'roles',
            'permissions',
            'role_user',
            'permission_role',
            'destinations',
            'ticket_types',
            'visitors',
            'orders',
            'order_items',
            'payments',
            'payment_webhook_events',
            'idempotency_keys',
            'tickets',
            'check_ins',
            'gates',
            'devices',
            'device_heartbeats',
            'audit_logs',
            'notifications',
            'settings',
            'integration_settings',
            'files',
            'jobs',
            'failed_jobs',
            'sessions',
            'cache',
            'personal_access_tokens',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing table {$table}");
        }
    }

    public function test_critical_unique_indexes_exist(): void
    {
        $this->assertHasUnique('orders', ['order_number']);
        $this->assertHasUnique('payments', ['payment_number']);
        $this->assertHasUnique('payments', ['provider_payment_id']);
        $this->assertHasUnique('tickets', ['ticket_code']);
        $this->assertHasUnique('tickets', ['qr_payload_hash']);
        $this->assertHasUnique('devices', ['device_id']);
        $this->assertHasUnique('devices', ['terminal_id']);
        $this->assertHasUnique('check_ins', ['ticket_id']);
        $this->assertHasUnique('idempotency_keys', ['scope', 'actor_type', 'actor_id', 'key_hash']);
        $this->assertHasUnique('payment_webhook_events', ['provider', 'event_id']);
        $this->assertHasUnique('destinations', ['code']);
        $this->assertHasUnique('ticket_types', ['destination_id', 'code']);
        $this->assertHasUnique('settings', ['key']);
        $this->assertHasUnique('integration_settings', ['integration', 'provider']);
        $this->assertHasUnique('files', ['disk', 'path']);
    }

    public function test_money_columns_are_integers(): void
    {
        $this->assertMoneyColumn('ticket_types', 'unit_price');
        $this->assertMoneyColumn('ticket_types', 'tax_amount');
        $this->assertMoneyColumn('ticket_types', 'service_fee_amount');
        $this->assertMoneyColumn('orders', 'subtotal');
        $this->assertMoneyColumn('orders', 'discount_total');
        $this->assertMoneyColumn('orders', 'tax_total');
        $this->assertMoneyColumn('orders', 'service_fee_total');
        $this->assertMoneyColumn('orders', 'grand_total');
        $this->assertMoneyColumn('order_items', 'unit_price');
        $this->assertMoneyColumn('order_items', 'line_grand_total');
        $this->assertMoneyColumn('payments', 'amount');
        $this->assertMoneyColumn('tickets', 'unit_price_snapshot');
        $this->assertMoneyColumn('tickets', 'tax_snapshot');
        $this->assertMoneyColumn('tickets', 'service_fee_snapshot');
    }

    public function test_financial_and_audit_tables_do_not_soft_delete(): void
    {
        foreach ([
            'orders',
            'order_items',
            'payments',
            'tickets',
            'check_ins',
            'audit_logs',
            'idempotency_keys',
            'payment_webhook_events',
            'devices',
            'visitors',
        ] as $table) {
            $this->assertFalse(Schema::hasColumn($table, 'deleted_at'), "{$table} must not soft-delete");
        }

        foreach (['users', 'destinations', 'ticket_types', 'gates'] as $table) {
            $this->assertTrue(Schema::hasColumn($table, 'deleted_at'), "{$table} should soft-delete");
        }
    }

    public function test_order_number_is_unique(): void
    {
        $destination = Destination::factory()->create();
        Order::factory()->for($destination)->create(['order_number' => 'ORD-DUP-1']);

        $this->expectException(QueryException::class);
        Order::factory()->for($destination)->create(['order_number' => 'ORD-DUP-1']);
    }

    public function test_payment_number_is_unique(): void
    {
        Payment::factory()->create(['payment_number' => 'PAY-DUP-1']);

        $this->expectException(QueryException::class);
        Payment::factory()->create(['payment_number' => 'PAY-DUP-1']);
    }

    public function test_nullable_provider_payment_id_allows_multiple_nulls(): void
    {
        Payment::factory()->count(2)->create(['provider_payment_id' => null]);

        $this->assertSame(2, Payment::query()->whereNull('provider_payment_id')->count());
    }

    public function test_provider_payment_id_is_unique_when_present(): void
    {
        Payment::factory()->create(['provider_payment_id' => 'prov-1']);

        $this->expectException(QueryException::class);
        Payment::factory()->create(['provider_payment_id' => 'prov-1']);
    }

    public function test_ticket_code_and_qr_hash_are_unique(): void
    {
        Ticket::factory()->create([
            'ticket_code' => 'TCK-DUP-1',
            'qr_payload' => 'payload-a',
            'qr_payload_hash' => hash('sha256', 'payload-a'),
        ]);

        $this->expectException(QueryException::class);
        Ticket::factory()->create([
            'ticket_code' => 'TCK-DUP-1',
            'qr_payload' => 'payload-b',
            'qr_payload_hash' => hash('sha256', 'payload-b'),
        ]);
    }

    public function test_check_in_ticket_id_is_unique(): void
    {
        $ticket = Ticket::factory()->create();
        CheckIn::factory()->create([
            'ticket_id' => $ticket->id,
            'destination_id' => $ticket->destination_id,
        ]);

        $this->expectException(QueryException::class);
        CheckIn::factory()->create([
            'ticket_id' => $ticket->id,
            'destination_id' => $ticket->destination_id,
        ]);
    }

    public function test_device_id_is_unique(): void
    {
        $destination = Destination::factory()->create();
        Device::factory()->for($destination)->create(['device_id' => 'kiosk-unique-1']);

        $this->expectException(QueryException::class);
        Device::factory()->for($destination)->create(['device_id' => 'kiosk-unique-1']);
    }

    public function test_idempotency_scope_actor_key_is_unique(): void
    {
        $attrs = [
            'scope' => 'order.create',
            'actor_type' => 'device',
            'actor_id' => 1,
            'key_hash' => str_repeat('a', 64),
        ];
        IdempotencyKey::factory()->create($attrs);

        $this->expectException(QueryException::class);
        IdempotencyKey::factory()->create($attrs);
    }

    public function test_webhook_provider_event_id_is_unique(): void
    {
        PaymentWebhookEvent::factory()->create([
            'provider' => 'tbd',
            'event_id' => 'evt-1',
        ]);

        $this->expectException(QueryException::class);
        PaymentWebhookEvent::factory()->create([
            'provider' => 'tbd',
            'event_id' => 'evt-1',
        ]);
    }

    public function test_audit_logs_are_append_only(): void
    {
        $log = AuditLog::factory()->create();

        $this->expectException(LogicException::class);
        $log->update(['action' => 'tampered']);
    }

    /**
     * @param  list<string>  $columns
     */
    private function assertHasUnique(string $table, array $columns): void
    {
        $found = collect(Schema::getIndexes($table))->contains(
            fn (array $index): bool => ($index['unique'] ?? false) === true
                && ($index['columns'] ?? []) === $columns,
        );

        $this->assertTrue($found, 'Missing unique index on '.$table.' ('.implode(',', $columns).')');
    }

    private function assertMoneyColumn(string $table, string $column): void
    {
        $definition = collect(Schema::getColumns($table))->firstWhere('name', $column);
        $this->assertNotNull($definition, "Missing {$table}.{$column}");

        $type = strtolower((string) ($definition['type_name'] ?? $definition['type']));
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            $this->assertSame('bigint', $type, "{$table}.{$column} must be BIGINT");

            return;
        }

        $this->assertContains(
            $type,
            ['bigint', 'integer', 'int'],
            "{$table}.{$column} should be an integer money column, got {$type}",
        );
    }
}
