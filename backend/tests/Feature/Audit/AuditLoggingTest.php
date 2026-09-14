<?php

namespace Tests\Feature\Audit;

use App\Enums\RoleName;
use App\Livewire\Audit\AuditLogIndex;
use App\Models\AuditLog;
use App\Support\Audit\AuditEventCatalog;
use App\Support\Audit\AuditWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithRbac;
use Tests\TestCase;

class AuditLoggingTest extends TestCase
{
    use InteractsWithRbac;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
    }

    public function test_writer_redacts_secrets_from_meta_before_and_after(): void
    {
        $writer = $this->app->make(AuditWriter::class);

        $log = $writer->write(
            action: 'auth.login_failed',
            actorType: 'anonymous',
            meta: [
                'email' => 'staff@example.com',
                'password' => 'super-secret',
                'activation_secret' => 'act-123',
                'nested' => ['api_key' => 'key-should-hide', 'ok' => true],
            ],
            before: ['token' => 'bearer-xyz'],
            after: ['plain_text_token' => 'tok'],
        );

        $this->assertSame('[REDACTED]', $log->meta_json['password']);
        $this->assertSame('[REDACTED]', $log->meta_json['activation_secret']);
        $this->assertSame('[REDACTED]', $log->meta_json['nested']['api_key']);
        $this->assertTrue($log->meta_json['nested']['ok']);
        $this->assertSame('staff@example.com', $log->meta_json['email']);
        $this->assertSame('[REDACTED]', $log->before_json['token']);
        $this->assertSame('[REDACTED]', $log->after_json['plain_text_token']);

        $encoded = json_encode([$log->meta_json, $log->before_json, $log->after_json]);
        $this->assertStringNotContainsString('super-secret', (string) $encoded);
        $this->assertStringNotContainsString('act-123', (string) $encoded);
        $this->assertStringNotContainsString('bearer-xyz', (string) $encoded);
    }

    public function test_critical_write_participates_in_fail_closed_transaction(): void
    {
        $writer = $this->app->make(AuditWriter::class);

        try {
            DB::transaction(function () use ($writer): void {
                $writer->writeCritical(
                    action: 'payment.paid',
                    actorType: 'system',
                    entityType: 'payment',
                    entityId: 1,
                    after: ['status' => 'paid'],
                );

                throw new \RuntimeException('Simulated money mutation failure after audit.');
            });
            $this->fail('Expected transaction to fail');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Simulated money mutation failure', $e->getMessage());
        }

        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_write_failure_is_not_swallowed(): void
    {
        $writer = $this->app->make(AuditWriter::class);

        Schema::rename('audit_logs', 'audit_logs_offline');

        try {
            $this->expectException(\Throwable::class);
            $writer->writeCritical(
                action: 'refund.created',
                actorType: 'user',
                actorId: 1,
            );
        } finally {
            if (Schema::hasTable('audit_logs_offline')) {
                Schema::rename('audit_logs_offline', 'audit_logs');
            }
        }
    }

    public function test_audit_logs_cannot_be_updated_or_deleted(): void
    {
        $log = AuditLog::factory()->create(['action' => 'order.created']);

        $this->expectException(\LogicException::class);
        $log->update(['action' => 'tampered']);
    }

    public function test_no_http_routes_mutate_audit_logs(): void
    {
        $mutating = collect(Route::getRoutes())->filter(function ($route): bool {
            $uri = $route->uri();
            $methods = array_diff($route->methods(), ['HEAD', 'GET', 'OPTIONS']);

            if ($methods === []) {
                return false;
            }

            return str_contains($uri, 'audit');
        });

        $this->assertTrue(
            $mutating->isEmpty(),
            'Found mutating audit routes: '.$mutating->map(fn ($r) => implode('|', $r->methods()).' '.$r->uri())->implode(', '),
        );
    }

    public function test_auditor_can_view_audit_index_ticket_officer_cannot(): void
    {
        AuditLog::factory()->create(['action' => 'refund.created']);

        $auditor = $this->userWithRole(RoleName::Auditor);
        $this->actingAs($auditor)
            ->get(route('dashboard.audit-logs'))
            ->assertOk()
            ->assertSee('Audit logs')
            ->assertSee('refund.created');

        $officer = $this->userWithRole(RoleName::TicketOfficer);
        $this->actingAs($officer)
            ->get(route('dashboard.audit-logs'))
            ->assertForbidden();
    }

    public function test_livewire_filters_by_action(): void
    {
        AuditLog::factory()->create(['action' => 'order.created']);
        AuditLog::factory()->create(['action' => 'refund.created']);

        $auditor = $this->userWithRole(RoleName::Auditor);

        Livewire::actingAs($auditor)
            ->test(AuditLogIndex::class)
            ->set('actionFilter', 'refund')
            ->assertSee('refund.created')
            ->assertDontSee('order.created');
    }

    public function test_event_catalog_lists_implemented_and_deferred(): void
    {
        $implemented = AuditEventCatalog::implementedActions();
        $deferred = AuditEventCatalog::deferredActions();

        $this->assertContains('order.created', $implemented);
        $this->assertContains('refund.created', $implemented);
        $this->assertContains('payment.paid', $implemented);
        $this->assertContains('ticket.printed', $implemented);
        $this->assertContains('ticket.reprinted', $implemented);
        $this->assertContains('ticket.print_failed', $implemented);
        $this->assertContains('device.registered', $implemented);
        $this->assertContains('report.exported', $implemented);
        $this->assertContains('order.expired', $implemented);
        $this->assertContains('ticket.expired', $implemented);
        $this->assertContains('device.offline_detected', $implemented);
        $this->assertContains('gate.open_command', $deferred);
        $this->assertNotContains('order.expired', $deferred);
    }

    public function test_policy_denies_update_and_delete_for_auditor(): void
    {
        $auditor = $this->userWithRole(RoleName::Auditor);
        $log = AuditLog::factory()->create();

        $this->assertTrue($auditor->can('viewAny', AuditLog::class));
        $this->assertTrue($auditor->can('view', $log));
        $this->assertFalse($auditor->can('update', $log));
        $this->assertFalse($auditor->can('delete', $log));
        $this->assertFalse($auditor->can('create', AuditLog::class));
    }
}
