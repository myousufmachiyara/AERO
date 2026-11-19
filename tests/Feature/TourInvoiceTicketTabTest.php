<?php

namespace Tests\Feature;

use App\Models\Airline;
use App\Models\Customer;
use App\Models\InvoiceTicketDetail;
use App\Models\Supplier;
use App\Models\TicketSaleInvoiceLine;
use App\Models\TravelInvoice;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tour Invoice → Ticket tab is identical to the Ticket Sale Invoice: same
 * fields, same pricing math, same ticket-number rules, same in-page errors.
 */
class TourInvoiceTicketTabTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Customer $customer;
    protected Supplier $supplier;
    protected Airline $airline;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('username', 'hassan')->firstOrFail();
        $this->actingAs($this->admin);
        $this->customer = Customer::factory()->create();
        $this->supplier = Supplier::factory()->create();
        $this->airline = Airline::factory()->create(['numeric_code' => '220']);
    }

    /** Master Details shared by every ticket — what the form's JS copies into each row. */
    protected function master(array $overrides = []): array
    {
        return array_merge([
            'pnr' => 'ABC123', 'ticket_type' => 'international', 'trip_type' => 'return',
            'leg1_from' => 'KHI', 'leg1_stay' => 'DXB', 'leg1_to' => 'LHR',
            'leg2_from' => 'LHR', 'leg2_stay' => '', 'leg2_to' => 'KHI',
            'leg1_departure_date' => '2026-12-01', 'leg1_departure_time' => '10:30',
            'fare_amount' => 1000, 'tax_amount' => 100,
            'apt_percent' => 2, 'apt_charges' => 0, 'apt_input_mode' => 'percent',          // 20
            'commission_percent' => 0, 'commission_amount' => 70, 'commission_input_mode' => 'amount', // 7%
            'wht_percent' => 10, 'wht_amount' => 0, 'wht_input_mode' => 'percent',          // 7
            'psf_percent' => 3, 'psf_amount' => 999, 'psf_basis' => 'fare', 'psf_input_mode' => 'percent', // 30
            'discount_percent' => 0, 'discount_amount' => 10, 'discount_input_mode' => 'amount',
            'agent_commission_percent' => 50, 'agent_commission_amount' => 0, 'agent_commission_input_mode' => 'percent', // 15
        ], $overrides);
    }

    protected function ticketLine(string $pax, ?string $ticketNo, array $master = []): array
    {
        return [
            'service_type' => 'ticket',
            'supplier_id' => $this->supplier->id,
            'description' => $pax,
            'detail' => $this->master($master) + ['pax_type' => 'adult', 'ticket_no' => $ticketNo],
        ];
    }

    protected function payload(array $lines): array
    {
        return [
            'invoice_date' => now()->format('Y-m-d'),
            'customer_id' => $this->customer->id,
            'lines' => $lines,
        ];
    }

    public function test_ticket_tab_prices_tickets_exactly_like_the_ticket_sale_invoice(): void
    {
        $this->postJson(route('tour_invoices.store'), $this->payload([
            $this->ticketLine('John Doe', '2201234567890'),
            $this->ticketLine('Jane Doe', '220-1234-567-891'),
            $this->ticketLine('', ''), // blank grid row — skipped
        ]))->assertOk()->assertJsonStructure(['redirect']);

        $invoice = TravelInvoice::with('serviceLines.ticketDetail')->latest('id')->firstOrFail();
        $this->assertCount(2, $invoice->serviceLines);

        $line = $invoice->serviceLines->first();
        $d = $line->ticketDetail;

        // Same numbers a TicketSaleInvoiceLine produces from the same inputs.
        $ref = new TicketSaleInvoiceLine(collect($this->master())->only(array_merge(InvoiceTicketDetail::PRICING_FIELDS, ['psf_basis']))->all());
        $ref->psf_basis = 'fare';
        $ref->recalculate();

        $this->assertEquals(20, (float) $d->apt_charges);
        $this->assertEquals(7, (float) $d->commission_percent);
        $this->assertEquals(7, (float) $d->wht_amount);
        $this->assertEquals(30, (float) $d->psf_amount);           // % mode wins over the stale 999
        $this->assertEquals(1, (float) $d->discount_percent);
        $this->assertEquals(15, (float) $d->agent_commission_amount);
        $this->assertEquals((float) $ref->total_amount, (float) $d->total_amount);
        $this->assertEquals(1140, (float) $d->total_amount);       // 1000+100+20+30−10
        $this->assertEquals($ref->supplierNet(), (float) $d->supplier_net);
        $this->assertEquals(1057, (float) $d->supplier_net);       // 1000+100+20−70+7

        // Service line: Receivable = Ticket Total, Payable = Supplier Net.
        $this->assertSame('John Doe', $line->description);
        $this->assertEquals(1140, (float) $line->receivable_l_amount);
        $this->assertEquals(1057, (float) $line->payable_l_amount);
        $this->assertEquals(83, (float) $line->income_l_amount);
        $this->assertEquals(2280, (float) $invoice->total_receivable);

        // Ticket # normalised, airline auto-detected from "220", cities joined.
        $this->assertSame('220-1234-567-890', $d->ticket_no);
        $this->assertSame($this->airline->id, $d->airline_id);
        $this->assertSame($this->airline->name, $d->airline);
        $this->assertSame('KHI-DXB-LHR/LHR-KHI', $d->sector);
        $this->assertSame('return', $d->trip_type);

        $this->get(route('tour_invoices.show', $invoice->id))->assertOk()->assertSee('John Doe')->assertSee('220-1234-567-890');
        $this->get(route('tour_invoices.edit', $invoice->id))->assertOk()->assertSee('tkm-root', false);
        $this->get(route('tour_invoices.print', $invoice->id))->assertOk();
    }

    public function test_ticket_number_rules_return_json_errors_without_reload(): void
    {
        $this->postJson(route('tour_invoices.store'), $this->payload([
            $this->ticketLine('Partial', '22012'),
        ]))->assertStatus(422)->assertJsonValidationErrors(['lines.0.detail.ticket_no']);

        $this->postJson(route('tour_invoices.store'), $this->payload([
            $this->ticketLine('A', '2201234567890'),
            $this->ticketLine('B', '2201234567890'),
        ]))->assertStatus(422)->assertJsonValidationErrors(['lines.1.detail.ticket_no']);

        $this->postJson(route('tour_invoices.store'), ['invoice_date' => now()->format('Y-m-d')])
            ->assertStatus(422)->assertJsonValidationErrors(['customer_id']);

        // Used on another tour invoice → rejected; editing the same invoice → allowed.
        $this->postJson(route('tour_invoices.store'), $this->payload([$this->ticketLine('A', '2205555555551')]))->assertOk();
        $first = TravelInvoice::latest('id')->firstOrFail();
        $this->postJson(route('tour_invoices.store'), $this->payload([$this->ticketLine('B', '2205555555551')]))
            ->assertStatus(422)->assertJsonValidationErrors(['lines.0.detail.ticket_no']);
        $this->putJson(route('tour_invoices.update', $first->id), $this->payload([$this->ticketLine('A edited', '2205555555551')]))
            ->assertOk();
        $this->assertSame('A edited', $first->fresh()->serviceLines->first()->description);
    }

    public function test_other_tabs_still_save_alongside_tickets(): void
    {
        $this->postJson(route('tour_invoices.store'), $this->payload([
            $this->ticketLine('John Doe', '2201234567890'),
            ['service_type' => 'other', 'supplier_id' => $this->supplier->id, 'currency' => 'PKR', 'exchange_rate' => 1,
             'receivable_f_amount' => 500, 'payable_f_amount' => 300],
        ]))->assertOk();

        $invoice = TravelInvoice::with('serviceLines')->latest('id')->firstOrFail();
        $this->assertEqualsCanonicalizing(['ticket', 'other'], $invoice->serviceLines->pluck('service_type')->all());
        $this->assertEquals(1140 + 500, (float) $invoice->total_receivable);
    }
}
