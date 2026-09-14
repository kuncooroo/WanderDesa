<?php

namespace Tests\Feature\Catalog;

use App\Enums\RoleName;
use App\Enums\ValidityType;
use App\Livewire\Catalog\CatalogBoard;
use App\Models\Destination;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithRbac;
use Tests\TestCase;

class CatalogBoardTest extends TestCase
{
    use InteractsWithRbac;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
    }

    public function test_ticket_officer_can_open_catalog_as_view_only(): void
    {
        Destination::factory()->create(['code' => 'VIEW-01', 'name' => 'Desa Lihat']);

        $officer = $this->userWithRole(RoleName::TicketOfficer);

        $this->actingAs($officer)
            ->get(route('dashboard.catalog'))
            ->assertOk()
            ->assertSee('Destinasi & tiket')
            ->assertSee('VIEW-01')
            ->assertDontSee('Tambah destinasi')
            ->assertDontSee('belum diluncurkan');
    }

    public function test_ticket_officer_cannot_create_destination(): void
    {
        $officer = $this->userWithRole(RoleName::TicketOfficer);

        Livewire::actingAs($officer)
            ->test(CatalogBoard::class)
            ->call('startCreateDestination')
            ->assertForbidden();
    }

    public function test_admin_can_create_destination_and_ticket_type(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);

        Livewire::actingAs($admin)
            ->test(CatalogBoard::class)
            ->assertSee('Tambah destinasi')
            ->set('destinationCode', 'DESA-LW')
            ->set('destinationName', 'Desa Livewire')
            ->set('destinationTimezone', 'Asia/Jakarta')
            ->set('destinationDescription', 'Dari dashboard')
            ->set('destinationActive', true)
            ->call('saveDestination')
            ->assertSee('Destinasi dibuat.');

        $destination = Destination::query()->where('code', 'DESA-LW')->first();
        $this->assertNotNull($destination);
        $this->assertSame('Desa Livewire', $destination->name);

        Livewire::actingAs($admin)
            ->test(CatalogBoard::class)
            ->call('selectDestination', $destination->id)
            ->assertSet('tab', 'tickets')
            ->set('ticketCode', 'ADULT')
            ->set('ticketName', 'Dewasa')
            ->set('unitPrice', 75000)
            ->set('taxAmount', 0)
            ->set('serviceFeeAmount', 0)
            ->set('validityType', ValidityType::SameDay->value)
            ->set('maxPerOrder', 10)
            ->set('ticketActive', true)
            ->call('saveTicketType')
            ->assertSee('Jenis tiket dibuat.');

        $this->assertDatabaseHas('ticket_types', [
            'destination_id' => $destination->id,
            'code' => 'ADULT',
            'name' => 'Dewasa',
            'unit_price' => 75000,
            'is_active' => true,
        ]);
    }

    public function test_admin_can_deactivate_destination_and_ticket_type(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $destination = Destination::factory()->create(['code' => 'OFF-DEST', 'is_active' => true]);
        $ticketType = TicketType::factory()->create([
            'destination_id' => $destination->id,
            'code' => 'OFF-TT',
            'is_active' => true,
        ]);

        Livewire::actingAs($admin)
            ->test(CatalogBoard::class)
            ->call('deactivateDestination', $destination->id)
            ->assertSee('Destinasi dinonaktifkan.');

        $this->assertFalse((bool) $destination->fresh()->is_active);

        Livewire::actingAs($admin)
            ->test(CatalogBoard::class)
            ->set('selectedDestinationId', (string) $destination->id)
            ->set('tab', 'tickets')
            ->call('deactivateTicketType', $ticketType->id)
            ->assertSee('Jenis tiket dinonaktifkan.');

        $this->assertFalse((bool) $ticketType->fresh()->is_active);

        Livewire::actingAs($admin)
            ->test(CatalogBoard::class)
            ->call('activateDestination', $destination->id)
            ->assertSee('Destinasi diaktifkan.')
            ->set('selectedDestinationId', (string) $destination->id)
            ->call('activateTicketType', $ticketType->id)
            ->assertSee('Jenis tiket diaktifkan.');

        $this->assertTrue((bool) $destination->fresh()->is_active);
        $this->assertTrue((bool) $ticketType->fresh()->is_active);
    }

    public function test_user_without_destinations_view_cannot_open_catalog(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard.catalog'))
            ->assertForbidden();
    }
}
