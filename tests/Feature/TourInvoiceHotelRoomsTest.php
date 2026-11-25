<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Hotel;
use App\Models\HotelRoom;
use App\Models\Supplier;
use App\Models\TravelInvoice;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tour Invoice → Hotel tab → Room Details:
 *   Single Room Total = Rate × Nights, Rooms Total = Single × No. of Rooms → Σ = Receivable
 *   Single Room Cost  = Cost × Nights, Rooms Cost  = Single × No. of Rooms → Σ = Payable
 */
class TourInvoiceHotelRoomsTest extends TestCase
{
    use RefreshDatabase;

    protected Customer $customer;
    protected Supplier $supplier;
    protected Hotel $hotel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->actingAs(User::where('username', 'hassan')->firstOrFail());
        $this->customer = Customer::factory()->create();
        $this->supplier = Supplier::factory()->create();
        $this->hotel = Hotel::factory()->create();
    }

    protected function hotelLine(array $rooms, array $extra = []): array
    {
        return array_replace_recursive([
            'service_type' => 'hotel',
            'supplier_id' => $this->supplier->id,
            'currency' => 'PKR',
            'receivable_f_amount' => 1, // ignored when rooms carry rates (server recalculates)
            'payable_f_amount' => 1,
            'detail' => [
                'hotel_id' => $this->hotel->id,
                'check_in' => '2026-12-01',
                'check_out' => '2026-12-04', // 3 nights
                'nights' => 99,               // server trusts the dates, not this
                'receivable_currency' => 'PKR', 'receivable_exchange_rate' => 1,
                'payable_currency' => 'PKR', 'payable_exchange_rate' => 1,
                'rooms' => $rooms,
            ],
        ], $extra);
    }

    protected function store(array $lines)
    {
        return $this->postJson(route('tour_invoices.store'), [
            'invoice_date' => now()->format('Y-m-d'),
            'customer_id' => $this->customer->id,
            'lines' => $lines,
        ]);
    }

    public function test_rooms_totals_fill_receivable_and_payable(): void
    {
        $double = HotelRoom::factory()->create(['hotel_id' => $this->hotel->id]);
        $triple = HotelRoom::factory()->create(['hotel_id' => $this->hotel->id]);

        $this->store([$this->hotelLine([
            ['hotel_room_id' => $double->id, 'qty' => 2, 'rate' => 500, 'cost' => 400],  // 1500×2=3000 / 1200×2=2400
            ['hotel_room_id' => $triple->id, 'qty' => 1, 'rate' => 750, 'cost' => 600],  // 2250 / 1800
        ])])->assertOk();

        $invoice = TravelInvoice::with('serviceLines.hotelDetail.rooms')->latest('id')->firstOrFail();
        $line = $invoice->serviceLines->first();
        $rooms = $line->hotelDetail->rooms;

        $this->assertSame(3, (int) $line->hotelDetail->nights);
        $this->assertEquals(3000, (float) $rooms[0]->total_amount);
        $this->assertEquals(2400, (float) $rooms[0]->total_cost);
        $this->assertEquals(400, (float) $rooms[0]->cost);
        $this->assertEquals(2250, (float) $rooms[1]->total_amount);
        $this->assertEquals(1800, (float) $rooms[1]->total_cost);

        $this->assertEquals(5250, (float) $line->receivable_f_amount);
        $this->assertEquals(4200, (float) $line->payable_f_amount);
        $this->assertEquals(1050, (float) $line->income_l_amount);

        $this->get(route('tour_invoices.show', $invoice->id))->assertOk()->assertSee('Cost 400.00');
        $this->get(route('tour_invoices.edit', $invoice->id))->assertOk();
    }

    public function test_foreign_currency_converts_with_each_sides_rate(): void
    {
        $this->store([$this->hotelLine(
            [['qty' => 1, 'rate' => 100, 'cost' => 80]], // SAR: 300 receivable, 240 payable
            ['currency' => 'SAR', 'exchange_rate' => 75, 'detail' => [
                'receivable_currency' => 'SAR', 'receivable_exchange_rate' => 75,
                'payable_currency' => 'SAR', 'payable_exchange_rate' => 74,
            ]]
        )])->assertOk();

        $line = TravelInvoice::with('serviceLines')->latest('id')->firstOrFail()->serviceLines->first();
        $this->assertEquals(300, (float) $line->receivable_f_amount);
        $this->assertEquals(240, (float) $line->payable_f_amount);
    }

    public function test_rooms_without_rates_keep_hand_typed_amounts(): void
    {
        $this->store([$this->hotelLine(
            [['qty' => 1, 'rate' => 0, 'cost' => 0]],
            ['receivable_f_amount' => 9000, 'payable_f_amount' => 7000]
        )])->assertOk();

        $line = TravelInvoice::with('serviceLines')->latest('id')->firstOrFail()->serviceLines->first();
        $this->assertEquals(9000, (float) $line->receivable_f_amount);
        $this->assertEquals(7000, (float) $line->payable_f_amount);
    }

    public function test_transport_line_saves_booking_name_and_remarks(): void
    {
        $this->store([[
            'service_type' => 'transport',
            'supplier_id' => $this->supplier->id,
            'currency' => 'PKR',
            'description' => 'Airport pickup 3am',
            'receivable_f_amount' => 500, 'payable_f_amount' => 400,
            'detail' => ['booking_name' => 'Sara Khan', 'receivable_exchange_rate' => 1, 'payable_exchange_rate' => 1],
        ]])->assertOk();

        $invoice = TravelInvoice::with('serviceLines.transportDetail')->latest('id')->firstOrFail();
        $line = $invoice->serviceLines->first();
        $this->assertSame('Airport pickup 3am', $line->description);
        $this->assertSame('Sara Khan', $line->transportDetail->booking_name);
        $this->get(route('tour_invoices.show', $invoice->id))->assertOk()->assertSee('Airport pickup 3am')->assertSee('Sara Khan');
    }

    public function test_other_service_line_saves_booking_name_travel_date_and_remarks(): void
    {
        $this->store([[
            'service_type' => 'other',
            'supplier_id' => $this->supplier->id,
            'currency' => 'PKR', 'exchange_rate' => 1,
            'description' => 'Ziyarat tour, morning slot',
            'receivable_f_amount' => 300, 'payable_f_amount' => 200,
            'detail' => ['qty' => 2, 'booking_name' => 'Ali Khan', 'travel_date' => '2026-12-05'],
        ]])->assertOk();

        $invoice = TravelInvoice::with('serviceLines.otherDetail')->latest('id')->firstOrFail();
        $line = $invoice->serviceLines->first();
        $this->assertSame('Ziyarat tour, morning slot', $line->description);
        $this->assertSame('Ali Khan', $line->otherDetail->booking_name);
        $this->assertSame('2026-12-05', $line->otherDetail->travel_date->format('Y-m-d'));
        $this->get(route('tour_invoices.show', $invoice->id))->assertOk()
            ->assertSee('Ziyarat tour, morning slot')->assertSee('Ali Khan')->assertSee('05/12/2026');
        $this->get(route('tour_invoices.edit', $invoice->id))->assertOk();

        $this->store([[
            'service_type' => 'other', 'currency' => 'PKR', 'exchange_rate' => 1,
            'detail' => ['travel_date' => 'not-a-date'],
        ]])->assertStatus(422)->assertJsonValidationErrors(['lines.0.detail.travel_date']);
    }
}
