<?php

namespace Tests\Feature;

use App\Models\TicketSaleInvoice;
use App\Models\User;
use App\Services\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Feature\E2E\LedgerHelpers;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;
    use LedgerHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-20 10:00:00');
        $this->bootFixtures();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function ticket(string $pax, string $no, array $over = []): array
    {
        return array_merge([
            'pax_name' => $pax, 'pax_type' => 'adult', 'pnr' => 'ABC123', 'ticket_no' => $no,
            'trip_type' => 'one_way', 'leg1_from' => 'KHI', 'leg1_to' => 'JED', 'leg1_departure_date' => '2026-11-23',
            'supplier_id' => $this->supplier->id, 'fare_amount' => 1000, 'tax_amount' => 100,
            'apt_percent' => 2, 'apt_input_mode' => 'percent', 'commission_percent' => 10, 'commission_input_mode' => 'percent',
            'wht_amount' => 5, 'wht_input_mode' => 'amount', 'psf_percent' => 1.5, 'psf_input_mode' => 'percent', 'psf_basis' => 'fare',
            'discount_amount' => 10, 'discount_input_mode' => 'amount',
        ], $over);
    }

    public function test_empty_dashboard_renders_with_empty_states(): void
    {
        $this->get(route('dashboard'))->assertOk()
            ->assertSee('Sales this month')->assertSee('Needs attention')
            ->assertSee('No invoices yet')->assertSee('No departures or check-ins in the next 7 days.');
    }

    public function test_figures_match_the_invoices_and_ledgers(): void
    {
        $this->postJson(route('ticket_invoices.store'), [
            'invoice_date' => '2026-11-20', 'customer_id' => $this->customer->id,
            'lines' => [$this->ticket('Ahmed Ali', '2201111111111'), $this->ticket('Sara Ali', '2201111111112')],
        ])->assertOk();
        $inv = TicketSaleInvoice::latest('id')->first();
        $this->postJson(route('ticket_invoices.store'), [
            'invoice_date' => '2026-10-05', 'customer_id' => $this->customer->id,
            'lines' => [$this->ticket('Old Trip', '2201111111113', ['leg1_departure_date' => '2026-10-10'])],
        ])->assertOk();

        $d = app(DashboardService::class)->build();
        $this->assertEquals(2250, $d['kpi']['sales_month']);
        $this->assertEquals(200, $d['kpi']['income_month']);
        $this->assertEquals(2250, $d['kpi']['sales_today']);
        $this->assertEquals(100.0, $d['kpi']['sales_change'], 'Nov 1–20: 2,250 vs Oct 1–20: 1,125.');
        $this->assertEquals(0, $d['kpi']['receivable'], 'Nothing posted yet, so no ledger balance.');
        $this->assertEquals(200, $d['byService']['ticket']['income']);
        $this->assertSame(['Jun 2026', 'Jul 2026', 'Aug 2026', 'Sep 2026', 'Oct 2026', 'Nov 2026'], array_column($d['trend'], 'label'));
        $this->assertEquals([1125, 2250], array_slice(array_column($d['trend'], 'sales'), -2));
        $this->assertCount(2, $d['departures'], 'Two flights on 23 Nov; the October one is not upcoming.');
        $this->assertSame(2, collect($d['attention'])->firstWhere('label', 'Ticket invoices not posted')['count']);

        $this->patch(route('ticket_invoices.post', $inv->id));
        $d = app(DashboardService::class)->build();
        $this->assertEquals(2250, $d['kpi']['receivable']);
        $this->assertEquals(2050, $d['kpi']['payable'], 'Airline owed 2 × 1,025.');
        $this->assertSame('Vacations Gala', $d['topCustomers'][0]['name']);

        $this->get(route('dashboard'))->assertOk()
            ->assertSee('2,250')->assertSee($inv->invoice_no)->assertSee('Ahmed Ali')->assertSee('KHI-JED')
            ->assertSee('Vacations Gala')->assertSee('dashTrend');
    }

    public function test_user_without_report_rights_sees_no_money_figures(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('dashboard'))->assertOk()
            ->assertDontSee('Sales this month')->assertDontSee('Receivable from customers')->assertSee('Upcoming travel');
    }
}
