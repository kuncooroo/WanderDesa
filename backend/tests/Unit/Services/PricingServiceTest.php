<?php

namespace Tests\Unit\Services;

use App\Models\TicketType;
use App\Services\Pricing\PricingService;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PricingServiceTest extends TestCase
{
    private PricingService $pricing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pricing = new PricingService;
    }

    #[Test]
    public function it_calculates_line_and_order_totals_from_ticket_type_rows(): void
    {
        $adult = new TicketType([
            'code' => 'ADULT',
            'name' => 'Dewasa',
            'unit_price' => 50000,
            'tax_amount' => 1000,
            'service_fee_amount' => 500,
        ]);
        $adult->id = 1;

        $child = new TicketType([
            'code' => 'CHILD',
            'name' => 'Anak',
            'unit_price' => 25000,
            'tax_amount' => 0,
            'service_fee_amount' => 250,
        ]);
        $child->id = 2;

        $quote = $this->pricing->quote([
            ['ticket_type' => $adult, 'quantity' => 2, 'visit_date' => '2026-09-13'],
            ['ticket_type' => $child, 'quantity' => 1],
        ]);

        $this->assertSame('IDR', $quote['currency']);
        $this->assertSame(0, $quote['discount_total']);
        $this->assertSame(125000, $quote['subtotal']);
        $this->assertSame(2000, $quote['tax_total']);
        $this->assertSame(1250, $quote['service_fee_total']);
        $this->assertSame(128250, $quote['grand_total']);

        $this->assertSame(100000, $quote['items'][0]['line_subtotal']);
        $this->assertSame(2000, $quote['items'][0]['line_tax_total']);
        $this->assertSame(1000, $quote['items'][0]['line_service_fee_total']);
        $this->assertSame(103000, $quote['items'][0]['line_grand_total']);
        $this->assertSame('2026-09-13', $quote['items'][0]['visit_date']);

        $this->assertSame(25000, $quote['items'][1]['line_subtotal']);
        $this->assertSame(0, $quote['items'][1]['line_tax_total']);
        $this->assertSame(250, $quote['items'][1]['line_service_fee_total']);
        $this->assertSame(25250, $quote['items'][1]['line_grand_total']);
    }

    #[Test]
    public function identical_inputs_produce_identical_totals(): void
    {
        $type = new TicketType([
            'code' => 'ADULT',
            'name' => 'Dewasa',
            'unit_price' => 40000,
            'tax_amount' => 2000,
            'service_fee_amount' => 1000,
        ]);
        $type->id = 10;

        $lines = [
            ['ticket_type' => $type, 'quantity' => 3],
        ];

        $a = $this->pricing->quote($lines);
        $b = $this->pricing->quote($lines);

        $this->assertSame($a, $b);
        $this->assertSame(120000, $a['subtotal']);
        $this->assertSame(6000, $a['tax_total']);
        $this->assertSame(3000, $a['service_fee_total']);
        $this->assertSame(129000, $a['grand_total']);
    }

    #[Test]
    public function it_ignores_any_client_price_on_the_model_surface_by_reading_typed_columns_only(): void
    {
        $type = new TicketType([
            'code' => 'ADULT',
            'name' => 'Dewasa',
            'unit_price' => 10000,
            'tax_amount' => 0,
            'service_fee_amount' => 0,
        ]);
        $type->id = 5;

        $quote = $this->pricing->quote([
            ['ticket_type' => $type, 'quantity' => 2],
        ]);

        $this->assertSame(20000, $quote['grand_total']);
        $this->assertSame(10000, $quote['items'][0]['unit_price']);
    }

    #[Test]
    public function empty_lines_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->pricing->quote([]);
    }
}
