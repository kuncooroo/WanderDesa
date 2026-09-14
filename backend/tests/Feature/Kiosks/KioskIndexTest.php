<?php

namespace Tests\Feature\Kiosks;

use App\Enums\RoleName;
use App\Livewire\Kiosks\KioskIndex;
use App\Models\Destination;
use App\Models\Device;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithRbac;
use Tests\TestCase;

class KioskIndexTest extends TestCase
{
    use InteractsWithRbac;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
    }

    public function test_admin_can_edit_kiosk_name_and_destination(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $from = Destination::factory()->create(['name' => 'Asal', 'is_active' => true]);
        $to = Destination::factory()->create(['name' => 'Tujuan', 'is_active' => true]);
        $device = Device::factory()->create([
            'name' => 'Kiosk Lama',
            'destination_id' => $from->id,
        ]);

        Livewire::actingAs($admin)
            ->test(KioskIndex::class)
            ->call('editDevice', $device->id)
            ->set('editName', 'Kiosk Baru')
            ->set('editDestinationId', $to->id)
            ->call('saveDevice')
            ->assertSee('Kiosk diperbarui.');

        $device->refresh();
        $this->assertSame('Kiosk Baru', $device->name);
        $this->assertSame($to->id, $device->destination_id);
    }
}
