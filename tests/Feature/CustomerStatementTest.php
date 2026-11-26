<?php

namespace Tests\Feature;

use App\Models\Airline;
use App\Models\ChartOfAccounts;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\TicketSaleInvoice;
use App\Models\User;
use App\Models\Voucher;
use App\Services\CustomerStatementService;
use App\Services\PartyLedgerService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Accounts Reports → Customer Statement: Sale Invoices by service, Refund
 * Invoices, Vouchers and the closing summary must add up, on screen and in
 * the PDF.
 */
class CustomerStatementTest extends TestCase
{
    use RefreshDatabase;

    protected Customer $customer;
    protected Supplier $supplier;
    protected int $cashAccountId;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-20 10:00:00');
        $this->seed(DatabaseSeeder::class);
        $this->actingAs(User::where('username', 'hassan')->firstOrFail());

        $this->customer = Customer::factory()->create(['name' => 'Vacations Gala', 'opening_balance' => 500]);
        $this->customer->update(['chart_of_account_id' => app(PartyLedgerService::class)->syncCustomer($this->customer)->id]);
        $this->supplier = Supplier::factory()->create();
        $airline = Airline::factory()->create(['numeric_code' => '220']);
        $airline->update(['chart_of_account_id' => app(PartyLedgerService::class)->syncAirline($airline)->id]);
        $this->cashAccountId = ChartOfAccounts::where('id', '!=', $this->customer->chart_of_account_id)->value('id');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function ticketInvoice(string $date, array $tickets): TicketSaleInvoice
    {
        $this->post(route('ticket_invoices.store'), [
            'invoice_date' => $date,
            'customer_id' => $this->customer->id,
            'lines' => collect($tickets)->map(fn ($t) => $t + [
                'pax_type' => 'adult', 'trip_type' => 'one_way', 'leg1_from' => 'KHI', 'leg1_to' => 'JED',
                'supplier_id' => $this->supplier->id,
            ])->all(),
        ])->assertSessionHasNoErrors();

        return TicketSaleInvoice::with('lines')->latest('id')->firstOrFail();
    }

    protected function voucher(string $date, string $type, float $amount, bool $receipt): void
    {
        Voucher::create([
            'voucher_type' => $type, 'date' => $date, 'amount' => $amount,
            'ac_dr_sid' => $receipt ? $this->cashAccountId : $this->customer->chart_of_account_id,
            'ac_cr_sid' => $receipt ? $this->customer->chart_of_account_id : $this->cashAccountId,
            'description' => $receipt ? 'VACATION GALA' : 'Refund paid',
        ]);
    }

    public function test_statement_sections_and_summary_add_up(): void
    {
        // ── Before the period: opening = 500 (ledger) + 1000 (ticket) − 300 (receipt) = 1200
        $this->ticketInvoice('2026-10-15', [['pax_name' => 'Old Pax', 'ticket_no' => '2200000000001', 'fare_amount' => 1000]]);
        $this->voucher('2026-10-20', 'receipt', 300, true);

        // ── In the period
        $inv = $this->ticketInvoice('2026-11-20', [
            ['pax_name' => 'Ahmed Ali', 'ticket_no' => '2201111111111', 'fare_amount' => 1000, 'tax_amount' => 100],
            ['pax_name' => 'Mujahid', 'ticket_no' => '2201111111112', 'fare_amount' => 1000, 'tax_amount' => 100],
            ['pax_name' => 'Sara', 'ticket_no' => '2201111111113', 'fare_amount' => 1000, 'tax_amount' => 100],
        ]);
        $this->patch(route('ticket_invoices.post', $inv->id));
        [$t1, $t2] = [$inv->lines[0], $inv->lines[1]];
        $this->post(route('ticket_invoices.lines.refund', [$inv->id, $t1->id]), [
            'refund_date' => '2026-11-20', 'refund_fare_amount' => 1000, 'refund_tax_amount' => 100,
            'refund_deduction_supplier' => 50, 'refund_deduction_company' => 50,
        ])->assertSessionHas('success'); // returns 1000
        $this->post(route('ticket_invoices.lines.void', [$inv->id, $t2->id]), [
            'void_deduction_supplier' => 60, 'void_deduction_company' => 40,
        ])->assertSessionHas('success'); // reverses 1100 − 100 = 1000

        $this->postJson(route('tour_invoices.store'), [
            'invoice_date' => '2026-11-10',
            'customer_id' => $this->customer->id,
            'passengers' => [['name' => 'Ahmed Ali Munsif', 'pax_type' => 'adult', 'passport_no_nic' => 'AR0718012']],
            'lines' => [
                ['service_type' => 'hotel', 'supplier_id' => $this->supplier->id, 'currency' => 'PKR', 'receivable_f_amount' => 5000, 'payable_f_amount' => 4000,
                 'detail' => ['check_in' => '2026-11-20', 'check_out' => '2026-11-25', 'booking_name' => 'Ahmed Ali Munsif', 'receivable_exchange_rate' => 1, 'payable_exchange_rate' => 1]],
                ['service_type' => 'visa', 'currency' => 'PKR', 'exchange_rate' => 1, 'receivable_f_amount' => 2000, 'payable_f_amount' => 1500,
                 'detail' => ['receivable_exchange_rate' => 1, 'payable_exchange_rate' => 1]],
                ['service_type' => 'transport', 'currency' => 'PKR', 'receivable_f_amount' => 800, 'payable_f_amount' => 600, 'description' => 'Airport pickup',
                 'detail' => ['booking_name' => 'Ahmed Ali Munsif', 'receivable_exchange_rate' => 1, 'payable_exchange_rate' => 1]],
                ['service_type' => 'other', 'currency' => 'PKR', 'exchange_rate' => 1, 'receivable_f_amount' => 300, 'payable_f_amount' => 200,
                 'detail' => ['booking_name' => 'Ahmed Ali Munsif', 'travel_date' => '2026-11-22', 'qty' => 1]],
                ['service_type' => 'ticket', 'supplier_id' => $this->supplier->id, 'description' => 'Tour Pax',
                 'detail' => ['ticket_no' => '2202222222221', 'fare_amount' => 2000, 'tax_amount' => 0]],
            ],
        ])->assertOk();

        $this->voucher('2026-11-12', 'receipt', 4000, true);
        $this->voucher('2026-11-25', 'payment', 1000, false);
        $this->voucher('2026-12-05', 'receipt', 999, true); // after the period — ignored

        $s = app(CustomerStatementService::class)->build($this->customer->fresh(), '2026-11-01', '2026-11-30');

        // Sale Invoices by service
        $this->assertCount(4, $s['sections']['ticket']);           // 3 sale tickets + 1 tour-invoice ticket
        $this->assertEquals(3300 + 2000, $s['sections']['ticket']->sum('net'));
        $this->assertSame(['sale' => 3, 'void' => 1, 'total' => 4], $s['ticket_counts']);
        $this->assertEquals(5000, $s['sections']['hotel']->sum('net'));
        $this->assertSame('Ahmed Ali Munsif', $s['sections']['hotel'][0]['passenger']);
        $this->assertSame(5, $s['sections']['hotel'][0]['nights']);
        $this->assertEquals(2000, $s['sections']['visa']->sum('net'));
        $this->assertSame('AR0718012', $s['sections']['visa'][0]['passport']);
        $this->assertSame(1, $s['sections']['visa'][0]['adults']);
        $this->assertEquals(800, $s['sections']['transport']->sum('net'));
        $this->assertEquals(300, $s['sections']['other']->sum('net'));

        // Date + invoice no only on the first row of an invoice
        $saleRows = $s['sections']['ticket']->filter(fn ($r) => $r['invoice'] === $inv->invoice_no)->values();
        $this->assertTrue($saleRows[0]['show_invoice']);
        $this->assertFalse($saleRows[1]['show_invoice']);

        // Refund Invoices
        $this->assertSame(['refund' => 1, 'void' => 1, 'total' => 2], $s['refund_counts']);
        $this->assertEquals(1000, $s['refunds']->firstWhere('kind', 'refund')['net']);
        $this->assertEquals(100, $s['refunds']->firstWhere('kind', 'refund')['cc']);
        $this->assertEquals(1000, $s['refunds']->firstWhere('kind', 'void')['net']);

        // Vouchers: only the receipt + payment in the period (the invoice's own Post/refund/void entries are left out)
        $this->assertCount(2, $s['vouchers']);
        $this->assertSame('RV', substr($s['vouchers'][0]['voucher_no'], 0, 2));

        // Summary
        $this->assertEquals(1200, $s['summary']['opening']);
        $this->assertEquals(13400, $s['summary']['sales']);
        $this->assertEquals(2000, $s['summary']['refunds']);
        $this->assertEquals(4000, $s['summary']['receipts']);
        $this->assertEquals(1000, $s['summary']['payments']);
        $this->assertEquals(9600, $s['summary']['net']);

        // The next period opens where this one closed.
        $next = app(CustomerStatementService::class)->build($this->customer->fresh(), '2026-12-01', '2026-12-31');
        $this->assertEquals(9600, $next['summary']['opening']);
        $this->assertEquals(9600 - 999, $next['summary']['net']);

        // Screen tab + PDF
        $this->get(route('reports.accounts', ['tab' => 'customer_statement', 'customer_id' => $this->customer->id, 'from_date' => '2026-11-01', 'to_date' => '2026-11-30']))
            ->assertOk()->assertSee('Customer Statement')->assertSee('Hotel Booking')->assertSee('Refund Invoices')->assertSee('9,600.00');

        $pdf = $this->get(route('reports.customer_statement_pdf', ['customer_id' => $this->customer->id, 'from_date' => '2026-11-01', 'to_date' => '2026-11-30']));
        $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
    }

    public function test_tab_shows_prompt_until_a_customer_is_picked(): void
    {
        $this->get(route('reports.accounts', ['tab' => 'customer_statement']))
            ->assertOk()->assertSee('Pick a date range and a customer');
        $this->get(route('reports.customer_statement_pdf'))->assertSessionHasErrors(['customer_id', 'from_date', 'to_date']);
    }
}
