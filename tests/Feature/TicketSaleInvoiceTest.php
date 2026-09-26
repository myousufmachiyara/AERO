<?php

namespace Tests\Feature;

use App\Models\Airline;
use App\Models\Customer;
use App\Models\Quotation;
use App\Models\Supplier;
use App\Models\TicketSaleInvoice;
use App\Models\User;
use App\Models\Voucher;
use App\Services\PartyLedgerService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Ticket Sale Invoice — rebuilt per client feedback as its own dedicated
 * module (no tabs, no currency/FX, pending/posted workflow with per-ticket
 * refund/void, airline auto-detect from a 13-digit ticket #, and separate
 * customer/airline ledger postings on Post).
 */
class TicketSaleInvoiceTest extends TestCase
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
        $this->admin = User::where('username', 'atif')->firstOrFail();
        $this->actingAs($this->admin);

        $this->customer = Customer::factory()->create();
        $customerAccount = app(PartyLedgerService::class)->syncCustomer($this->customer);
        $this->customer->update(['chart_of_account_id' => $customerAccount->id]);

        $this->supplier = Supplier::factory()->create();

        $this->airline = Airline::factory()->create(['numeric_code' => '220']);
        $airlineAccount = app(PartyLedgerService::class)->syncAirline($this->airline);
        $this->airline->update(['chart_of_account_id' => $airlineAccount->id]);
    }

    public function test_store_computes_totals_and_auto_detects_airline_from_ticket_number(): void
    {
        $response = $this->post(route('ticket_invoices.store'), [
            'invoice_date' => now()->format('Y-m-d'),
            'customer_id' => $this->customer->id,
            'lines' => [[
                'pax_name' => 'John Doe',
                'pax_type' => 'adult',
                'pnr' => 'ABC123',
                'ticket_no' => '2201234567890', // 220 = this->airline's code
                'trip_type' => 'one_way',
                'leg1_from' => 'KHI', 'leg1_to' => 'JED',
                'fare_amount' => 1000,
                'tax_amount' => 100,
                'apt_percent' => 2,
                'commission_percent' => 10,
                'wht_amount' => 5,
                'psf_percent' => 1.5,
                'discount_amount' => 10,
                'supplier_id' => $this->supplier->id,
            ]],
        ]);

        $invoice = TicketSaleInvoice::with('lines')->latest('id')->firstOrFail();
        $response->assertRedirect(route('ticket_invoices.show', $invoice->id));
        $this->assertSame('pending', $invoice->status);

        $line = $invoice->lines->first();
        $this->assertSame('220-1234-567-890', $line->ticket_no);
        $this->assertEquals($this->airline->id, $line->airline_id);
        $this->assertEquals(20, (float) $line->apt_charges); // 1000 * 2% (apt_percent)
        $this->assertEquals(15, (float) $line->psf_amount); // 1000 (fare basis) * 1.5%
        $this->assertEquals(100, (float) $line->commission_amount); // 1000 * 10%
        // fare + tax + apt + psf - discount = 1000 + 100 + 20 + 15 - 10
        $this->assertEquals(1125, (float) $line->total_amount);
        $this->assertSame('KHI-JED', $line->citiesLabel());

        $invoice->refresh();
        $this->assertEquals(1125, (float) $invoice->total_amount);
    }

    public function test_posting_hits_customer_and_airline_ledgers_and_locks_edit_refund_void(): void
    {
        $invoice = $this->createInvoiceWithOneTicket();
        $line = $invoice->lines->first();

        $response = $this->patch(route('ticket_invoices.post', $invoice->id));
        $response->assertRedirect(route('ticket_invoices.show', $invoice->id));

        $invoice->refresh();
        $this->assertSame('posted', $invoice->status);
        $this->assertNotNull($invoice->posted_at);
        $this->assertSame(2, Voucher::count());

        $customerVoucher = Voucher::where('ac_dr_sid', $this->customer->chart_of_account_id)->firstOrFail();
        $this->assertEquals(1125, (float) $customerVoucher->amount);

        $airlineVoucher = Voucher::where('ac_dr_sid', $this->airline->chart_of_account_id)->firstOrFail();
        $this->assertEquals(95, (float) $airlineVoucher->amount); // commission 100 - WHT 5

        // Locked while posted.
        $this->get(route('ticket_invoices.edit', $invoice->id))->assertRedirect(route('ticket_invoices.show', $invoice->id));
        $this->post(route('ticket_invoices.lines.refund', [$invoice->id, $line->id]), [
            'refund_date' => now()->format('Y-m-d'), 'refund_fare_amount' => 1000, 'refund_tax_amount' => 100, 'refund_charges' => 0,
        ])->assertSessionHas('error');
        $this->post(route('ticket_invoices.lines.void', [$invoice->id, $line->id]), [
            'void_deduction_supplier' => 10, 'void_deduction_company' => 10,
        ])->assertSessionHas('error');

        // Unpost reverses everything.
        $this->patch(route('ticket_invoices.unpost', $invoice->id))->assertRedirect(route('ticket_invoices.show', $invoice->id));
        $invoice->refresh();
        $this->assertSame('pending', $invoice->status);
        $this->assertSame(0, Voucher::count());
    }

    public function test_refund_reduces_customer_receivable_and_zeroes_airline_commission(): void
    {
        $invoice = $this->createInvoiceWithOneTicket();
        $line = $invoice->lines->first();

        $response = $this->post(route('ticket_invoices.lines.refund', [$invoice->id, $line->id]), [
            'refund_date' => now()->format('Y-m-d'),
            'refund_fare_amount' => 1000,
            'refund_tax_amount' => 100,
            'refund_charges' => 50,
        ]);
        $response->assertRedirect(route('ticket_invoices.show', $invoice->id));

        $line->refresh();
        $this->assertSame('refunded', $line->status);
        $this->assertEquals(1050, (float) $line->refund_amount); // 1000 + 100 - 50
        $this->assertEquals(75, (float) $line->refund_profit); // original total 1125 - 1050
        $this->assertEquals(0, $line->effectiveNetCommission());

        $invoice->refresh();
        $this->assertEquals(75, (float) $invoice->total_amount);

        // Posting after a refund only creates the customer voucher — no commission was earned.
        $this->patch(route('ticket_invoices.post', $invoice->id));
        $this->assertSame(1, Voucher::count());
        $this->assertEquals(75, (float) Voucher::first()->amount);
    }

    public function test_void_leaves_only_the_deduction_receivable_and_is_blocked_after_invoice_day(): void
    {
        $invoice = $this->createInvoiceWithOneTicket();
        $line = $invoice->lines->first();

        Carbon::setTestNow(now()->addDay());
        try {
            $blocked = $this->post(route('ticket_invoices.lines.void', [$invoice->id, $line->id]), [
                'void_deduction_supplier' => 10, 'void_deduction_company' => 10,
            ]);
            $blocked->assertSessionHas('error');
            $line->refresh();
            $this->assertSame('active', $line->status);
        } finally {
            Carbon::setTestNow();
        }

        $response = $this->post(route('ticket_invoices.lines.void', [$invoice->id, $line->id]), [
            'void_deduction_supplier' => 30,
            'void_deduction_company' => 20,
        ]);
        $response->assertRedirect(route('ticket_invoices.show', $invoice->id));

        $line->refresh();
        $this->assertSame('voided', $line->status);
        $this->assertEquals(50, (float) $line->void_total_deduction);

        $invoice->refresh();
        $this->assertEquals(50, (float) $invoice->total_amount);
    }

    public function test_duplicate_ticket_number_is_rejected_across_invoices(): void
    {
        $this->createInvoiceWithOneTicket(); // uses 220-1234-567-890

        $response = $this->post(route('ticket_invoices.store'), [
            'invoice_date' => now()->format('Y-m-d'),
            'customer_id' => $this->customer->id,
            'lines' => [[
                'pax_name' => 'Jane Doe', 'pax_type' => 'adult',
                'ticket_no' => '2201234567890', // same number, unformatted
                'fare_amount' => 500, 'tax_amount' => 0,
            ]],
        ]);

        $response->assertSessionHasErrors();
        $this->assertSame(1, TicketSaleInvoice::count());
    }

    public function test_index_search_finds_invoice_by_ticket_number(): void
    {
        $invoice = $this->createInvoiceWithOneTicket();

        $found = $this->get(route('ticket_invoices.index', ['search' => '1234567890']));
        $found->assertOk()->assertSee($invoice->invoice_no);

        $notFound = $this->get(route('ticket_invoices.index', ['search' => 'no-such-ticket']));
        $notFound->assertOk()->assertDontSee($invoice->invoice_no);
    }

    public function test_my_commission_report_shows_only_own_active_lines_for_the_month(): void
    {
        $this->post(route('ticket_invoices.store'), [
            'invoice_date' => now()->format('Y-m-d'),
            'customer_id' => $this->customer->id,
            'lines' => [[
                'pax_name' => 'Agent Sale', 'pax_type' => 'adult', 'ticket_no' => '2209999999999',
                'fare_amount' => 1000, 'tax_amount' => 0,
                'sales_agent_id' => $this->admin->id, 'agent_commission_percent' => 25, // 1000 total * 25% = 250
            ]],
        ]);

        $response = $this->get(route('ticket_invoices.my_commission', ['month' => now()->month, 'year' => now()->year]));
        $response->assertOk()->assertSee('250.00');
    }

    public function test_approved_quotation_converts_to_ticket_sale_invoice(): void
    {
        $this->post(route('quotations.store'), [
            'quotation_date' => now()->format('Y-m-d'),
            'customer_id' => $this->customer->id,
            'lines' => [
                ['service_type' => 'ticket', 'supplier_id' => $this->supplier->id, 'currency' => 'PKR', 'exchange_rate' => 1, 'receivable_f_amount' => 1000, 'payable_f_amount' => 700],
            ],
        ]);
        $quotation = Quotation::latest('id')->firstOrFail();
        $quotation->update(['status' => 'approved']);

        $this->get(route('ticket_invoices.create', ['quotation_id' => $quotation->id]))->assertOk();

        $response = $this->post(route('ticket_invoices.store'), [
            'invoice_date' => now()->format('Y-m-d'),
            'customer_id' => $quotation->customer_id,
            'quotation_id' => $quotation->id,
            'lines' => [[
                'pax_name' => 'Conv Pax', 'pax_type' => 'adult', 'ticket_no' => '2201111111111',
                'fare_amount' => 1000, 'tax_amount' => 0, 'supplier_id' => $this->supplier->id,
            ]],
        ]);

        $invoice = TicketSaleInvoice::latest('id')->firstOrFail();
        $response->assertRedirect(route('ticket_invoices.show', $invoice->id));

        $quotation->refresh();
        $this->assertSame('converted', $quotation->status);
        $this->assertSame('sale', $quotation->converted_invoice_type);
        $this->assertEquals($invoice->id, $quotation->converted_invoice_id);
    }

    /**
     * Client fix: APT, PSF, and agent commission all became percentage-
     * driven instead of manually-typed amounts. Covers both PSF bases
     * ('fare' and 'total' = fare+tax+APT subtotal) and confirms agent
     * commission is a % of the ticket's final total, not of the fare.
     */
    public function test_apt_psf_and_agent_commission_are_computed_from_percentages(): void
    {
        // PSF on fare basis (default).
        $this->post(route('ticket_invoices.store'), [
            'invoice_date' => now()->format('Y-m-d'),
            'customer_id' => $this->customer->id,
            'lines' => [[
                'pax_name' => 'Fare Basis Pax', 'pax_type' => 'adult', 'ticket_no' => '2201111111112',
                'fare_amount' => 1000, 'tax_amount' => 100,
                'apt_percent' => 2, // apt = 1000 * 2% = 20
                'psf_percent' => 5, 'psf_basis' => 'fare', // psf = 1000 * 5% = 50
                'agent_commission_percent' => 10, // total = 1000+100+20+50 = 1170; agent comm = 117
                'sales_agent_id' => $this->admin->id,
            ]],
        ]);
        $fareBasisInvoice = TicketSaleInvoice::with('lines')->latest('id')->firstOrFail();
        $fareBasisLine = $fareBasisInvoice->lines->first();
        $this->assertEquals(20, (float) $fareBasisLine->apt_charges);
        $this->assertEquals(50, (float) $fareBasisLine->psf_amount);
        $this->assertEquals(1170, (float) $fareBasisLine->total_amount);
        $this->assertEquals(117, (float) $fareBasisLine->agent_commission_amount);

        // PSF on the fare+tax+APT subtotal instead.
        $this->post(route('ticket_invoices.store'), [
            'invoice_date' => now()->format('Y-m-d'),
            'customer_id' => $this->customer->id,
            'lines' => [[
                'pax_name' => 'Total Basis Pax', 'pax_type' => 'adult', 'ticket_no' => '2201111111113',
                'fare_amount' => 1000, 'tax_amount' => 100,
                'apt_percent' => 2, // apt = 20
                'psf_percent' => 5, 'psf_basis' => 'total', // psf basis = 1000+100+20 = 1120; psf = 1120 * 5% = 56
            ]],
        ]);
        $totalBasisInvoice = TicketSaleInvoice::with('lines')->latest('id')->firstOrFail();
        $totalBasisLine = $totalBasisInvoice->lines->first();
        $this->assertEquals(20, (float) $totalBasisLine->apt_charges);
        $this->assertEquals(56, (float) $totalBasisLine->psf_amount);
        $this->assertEquals(1176, (float) $totalBasisLine->total_amount); // 1000+100+20+56
    }

    public function test_pages_render(): void
    {
        $invoice = $this->createInvoiceWithOneTicket();

        $this->get(route('ticket_invoices.index'))->assertOk();
        $this->get(route('ticket_invoices.create'))->assertOk();
        $this->get(route('ticket_invoices.show', $invoice->id))->assertOk();
        $this->get(route('ticket_invoices.edit', $invoice->id))->assertOk();
        $this->get(route('ticket_invoices.print', $invoice->id))->assertOk();
        $this->get(route('ticket_invoices.print_detailed', $invoice->id))->assertOk();
        $this->get(route('airlines.index'))->assertOk();
        $this->get(route('airlines.create'))->assertOk();
        $this->get(route('airlines.show', $this->airline->id))->assertOk();
    }

    protected function createInvoiceWithOneTicket(): TicketSaleInvoice
    {
        $this->post(route('ticket_invoices.store'), [
            'invoice_date' => now()->format('Y-m-d'),
            'customer_id' => $this->customer->id,
            'lines' => [[
                'pax_name' => 'John Doe', 'pax_type' => 'adult', 'pnr' => 'ABC123',
                'ticket_no' => '2201234567890',
                'fare_amount' => 1000, 'tax_amount' => 100, 'apt_percent' => 2,
                'commission_percent' => 10, 'wht_amount' => 5, 'psf_percent' => 1.5, 'discount_amount' => 10,
                'supplier_id' => $this->supplier->id,
            ]],
        ]);

        return TicketSaleInvoice::with('lines')->latest('id')->firstOrFail();
    }
}
