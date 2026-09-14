<?php

namespace Tests\Feature\Ops;

use App\Actions\Ops\NotifyOpsAlerts;
use App\Actions\Payments\ApplyProviderPaymentEvent;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\RoleName;
use App\Enums\WebhookProcessStatus;
use App\Integrations\Payments\DecodedWebhook;
use App\Integrations\Payments\PaymentGateway;
use App\Integrations\Payments\PaymentInitiation;
use App\Integrations\Payments\VerifiesPaymentWebhooks;
use App\Jobs\DetectOfflineDevices;
use App\Jobs\ProcessPaymentWebhook;
use App\Livewire\Ops\NotificationInbox;
use App\Models\Device;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentWebhookEvent;
use App\Support\Audit\AuditWriter;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use RuntimeException;
use Tests\Concerns\AssertsApiEnvelope;
use Tests\Concerns\InteractsWithOrders;
use Tests\Concerns\InteractsWithPaymentWebhooks;
use Tests\Concerns\InteractsWithRbac;
use Tests\TestCase;

class OpsNotificationTest extends TestCase
{
    use AssertsApiEnvelope;
    use InteractsWithOrders;
    use InteractsWithPaymentWebhooks;
    use InteractsWithRbac;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        config(['payments.webhook_secret' => $this->webhookSecret()]);
    }

    public function test_offline_detection_notifies_kiosks_view_once_per_outage(): void
    {
        CarbonImmutable::setTestNow('2026-09-14 12:00:00');

        $operator = $this->userWithRole(RoleName::Operator);
        $officer = $this->userWithRole(RoleName::TicketOfficer);
        $inactive = $this->userWithRole(RoleName::Operator, ['is_active' => false]);

        $device = Device::factory()->active()->create([
            'name' => 'Pos Utama',
            'last_heartbeat_at' => now()->subMinutes(5),
        ]);

        $this->runOfflineJob();
        $this->runOfflineJob();

        $this->assertSame(1, $operator->notifications()->count());
        $this->assertSame(0, $officer->notifications()->count());
        $this->assertSame(0, $inactive->notifications()->count());

        $data = $operator->notifications()->first()?->data;
        $this->assertIsArray($data);
        $this->assertSame('kiosk.offline', $data['kind']);
        $this->assertSame($device->device_id, $data['device_id']);
        $this->assertArrayNotHasKey('last_ip', $data);
        $this->assertArrayNotHasKey('activation_secret', $data);
        $this->assertArrayNotHasKey('token', $data);

        CarbonImmutable::setTestNow();
    }

    public function test_webhook_processing_failure_notifies_admin_once_without_secrets(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $operator = $this->userWithRole(RoleName::Operator);
        $officer = $this->userWithRole(RoleName::TicketOfficer);

        $payment = $this->processingDigitalPayment();
        $payload = [
            'event_id' => 'evt_ops_fail_1',
            'type' => 'payment.paid',
            'payment_number' => $payment->payment_number,
            'raw_body' => '{"secret":"should-not-persist"}',
            'signature' => 'sig-should-not-persist',
        ];

        $event = $this->receivedWebhookEvent($payload);

        $this->processWebhookThrowing($event->id, $payload);

        $this->assertSame(WebhookProcessStatus::Failed, $event->fresh()->process_status);
        $this->assertSame(1, $admin->notifications()->count());
        $this->assertSame(0, $operator->notifications()->count());
        $this->assertSame(0, $officer->notifications()->count());

        $data = $admin->notifications()->first()?->data;
        $this->assertIsArray($data);
        $this->assertSame('payment.webhook_failed', $data['kind']);
        $this->assertSame('evt_ops_fail_1', $data['event_id']);
        $this->assertSame(RuntimeException::class, $data['exception_class']);
        $this->assertArrayNotHasKey('payload', $data);
        $this->assertArrayNotHasKey('raw_body', $data);
        $this->assertArrayNotHasKey('signature', $data);
        $this->assertArrayNotHasKey('secret', $data);

        $this->processWebhookThrowing($event->id, $payload);
        $this->assertSame(1, $admin->notifications()->count());
    }

    public function test_invalid_webhook_signature_does_not_notify(): void
    {
        $this->userWithRole(RoleName::Admin);
        $payment = $this->processingDigitalPayment();

        $this->postSandboxWebhook(
            [
                'event_id' => 'evt_bad_sig_ops',
                'type' => 'payment.paid',
                'payment_number' => $payment->payment_number,
            ],
            signature: 'deadbeef',
        )->assertUnauthorized();

        $this->assertDatabaseCount('notifications', 0);
        $this->assertDatabaseCount('payment_webhook_events', 0);
    }

    public function test_staff_api_lists_own_notifications_and_marks_read(): void
    {
        $operator = $this->userWithRole(RoleName::Operator);
        $admin = $this->userWithRole(RoleName::Admin);
        $token = $operator->createToken('phpunit')->plainTextToken;

        Device::factory()->active()->create([
            'last_heartbeat_at' => now()->subMinutes(5),
        ]);
        $this->runOfflineJob();

        $list = $this->withToken($token)->getJson('/api/v1/notifications');
        $this->assertSuccessEnvelope($list);
        $list->assertJsonPath('meta.unread_count', 1)
            ->assertJsonPath('data.0.kind', 'kiosk.offline')
            ->assertJsonPath('data.0.read_at', null);

        $id = $list->json('data.0.id');
        $this->assertIsString($id);

        $read = $this->withToken($token)->postJson("/api/v1/notifications/{$id}/read");
        $this->assertSuccessEnvelope($read);
        $this->assertIsString($read->json('data.read_at'));
        $this->assertNotSame('', $read->json('data.read_at'));

        $this->withToken($token)
            ->getJson('/api/v1/notifications')
            ->assertJsonPath('meta.unread_count', 0);

        $adminNote = $admin->notifications()->first();
        $this->assertNotNull($adminNote);

        $this->assertErrorEnvelope(
            $this->withToken($token)->postJson("/api/v1/notifications/{$adminNote->id}/read"),
            404,
            'resource.not_found',
        );
    }

    public function test_device_token_cannot_read_staff_notifications(): void
    {
        [, $deviceToken] = $this->activeDeviceToken();

        $this->assertErrorEnvelope(
            $this->withToken($deviceToken)->getJson('/api/v1/notifications'),
            403,
            'auth.forbidden',
        );
    }

    public function test_guest_cannot_list_notifications(): void
    {
        $this->getJson('/api/v1/notifications')->assertUnauthorized();
    }

    public function test_dashboard_inbox_shows_empty_state_and_marks_read(): void
    {
        $officer = $this->userWithRole(RoleName::TicketOfficer);
        $operator = $this->userWithRole(RoleName::Operator);

        Device::factory()->active()->create([
            'last_heartbeat_at' => now()->subMinutes(5),
        ]);
        $this->runOfflineJob();

        $this->actingAs($operator)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Notifikasi');

        Livewire::actingAs($officer)
            ->test(NotificationInbox::class)
            ->assertSee('Tidak ada notifikasi')
            ->assertDontSee('Kiosk offline');

        $id = $operator->notifications()->value('id');
        $this->assertNotNull($id);

        Livewire::actingAs($operator)
            ->test(NotificationInbox::class)
            ->assertSee('Kiosk offline')
            ->assertSee('Dibaca')
            ->call('markRead', $id)
            ->assertDontSee('Dibaca');

        $this->assertNotNull($operator->notifications()->first()?->read_at);
    }

    public function test_optional_mail_channel_does_not_block_database(): void
    {
        config(['ops.notifications.mail' => true]);

        $operator = $this->userWithRole(RoleName::Operator);
        Device::factory()->active()->create([
            'last_heartbeat_at' => now()->subMinutes(5),
        ]);

        $this->runOfflineJob();

        $this->assertSame(1, $operator->notifications()->count());
    }

    private function runOfflineJob(): void
    {
        $this->app->make(DetectOfflineDevices::class)->handle(
            $this->app->make(AuditWriter::class),
            $this->app->make(NotifyOpsAlerts::class),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function receivedWebhookEvent(array $payload): PaymentWebhookEvent
    {
        return PaymentWebhookEvent::query()->create([
            'provider' => 'sandbox',
            'event_id' => (string) $payload['event_id'],
            'payload_hash' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)),
            'process_status' => WebhookProcessStatus::Received,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function processWebhookThrowing(int $eventId, array $payload): void
    {
        $gateway = new class implements PaymentGateway, VerifiesPaymentWebhooks
        {
            public function initiate(Payment $payment, Order $order): PaymentInitiation
            {
                throw new RuntimeException('unused');
            }

            public function refund(Payment $payment): void {}

            public function verifyWebhookSignature(string $rawBody, ?string $signatureHeader): void {}

            public function decodeWebhookPayload(array $payload): DecodedWebhook
            {
                throw new RuntimeException('simulated processing failure');
            }
        };

        (new ProcessPaymentWebhook($eventId, $payload, 'sandbox'))->handle(
            $gateway,
            $this->app->make(ApplyProviderPaymentEvent::class),
            $this->app->make(NotifyOpsAlerts::class),
        );
    }

    private function processingDigitalPayment(): Payment
    {
        ['destination' => $destination, 'adult' => $adult] = $this->sellableCatalog();
        $order = Order::factory()->create([
            'destination_id' => $destination->id,
            'status' => OrderStatus::PendingPayment,
            'grand_total' => 103000,
        ]);
        $this->attachOrderLine($order, $adult, 2);

        return Payment::factory()->processing()->create([
            'order_id' => $order->id,
            'method' => PaymentMethod::EWallet,
            'provider' => 'sandbox',
            'amount' => 103000,
            'provider_payment_id' => 'sandbox-PAY-OPS1',
            'payment_number' => 'PAY-OPS1',
        ]);
    }
}
