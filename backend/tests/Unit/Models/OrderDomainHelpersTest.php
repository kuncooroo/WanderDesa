<?php

namespace Tests\Unit\Models;

use App\Enums\DeviceStatus;
use App\Models\Device;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderDomainHelpersTest extends TestCase
{
    use RefreshDatabase;

    public function test_device_is_sellable_only_when_active_and_not_maintenance(): void
    {
        $active = Device::factory()->active()->make();
        $maintenance = Device::factory()->maintenance()->make();
        $disabled = Device::factory()->disabled()->make();
        $registered = Device::factory()->make([
            'is_active' => true,
            'status' => DeviceStatus::Registered,
        ]);

        $this->assertTrue($active->isSellable());
        $this->assertFalse($maintenance->isSellable());
        $this->assertTrue($maintenance->isInMaintenance());
        $this->assertFalse($disabled->isSellable());
        $this->assertFalse($registered->isSellable());
    }

    public function test_order_payment_ttl_reads_settings_with_safe_default(): void
    {
        $this->assertSame(15, Setting::orderPaymentTtlMinutes());

        Setting::factory()->create([
            'key' => Setting::ORDER_PAYMENT_TTL_MINUTES,
            'value' => '20',
            'type' => 'int',
        ]);
        $this->assertSame(20, Setting::orderPaymentTtlMinutes());

        Setting::query()->where('key', Setting::ORDER_PAYMENT_TTL_MINUTES)->update(['value' => '0']);
        $this->assertSame(15, Setting::orderPaymentTtlMinutes());
    }
}
