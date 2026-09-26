<?php

namespace Tests\Feature;

use App\Models\Airline;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\TicketSaleInvoice;
use App\Models\User;
use App\Services\PartyLedgerService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 6 reports, extended to include Ticket Sale Invoice.
 *
 * Ticket Sale Invoice was rebuilt as its own dedicated module (see
 * TicketSaleInvoiceTest) and stopped feeding the Vendor/Sales/Accounts
 * reports, which still read the old travel_invoices/service_lines tables
 * that only Tour Invoice writes to now. These tests cover the follow-up
 * that merges Ticket Sale Invoice data back into those three reports.
 *
 * One ticket line is used throughout, chosen so every figure is easy to
 * hand-check: fare 1000, tax 100, apt 20, commission 10% (=100), WHT 5,
 * PSF 15, discount 10.
 *   total_amount (customer receivable)      = 1000+100+20+15-10 = 1125
 *   effectiveSupplierPayable() (active)      = 1000+100+20+15   = 1135
 *   effectiveNetCommission()                 = 100-5            =   95
 */
class TicketSaleInvoiceReportsTest extends TestCase
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
        $supplierAccount = app(PartyLedgerService::class)->syncSupplier($this->supplier);
        $this->supplier->update(['chart_of_account_id' => $supplierAccount->id]);

        $this->airline = Airline::factory()->create(['numeric_code' => '220']);
        $airlineAccount = app(PartyLedgerService::class)->syncAirline($this->airline);
        $this->airline->update(['chart_of_account_id' => $airlineAccount->id]);
    }

    protected function createTicketInvoice(): TicketSaleInvoice
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

    public function test_vendor_summary_and_spend_tabs_include_ticket_sale_invoice(): void
    {
        $this->createTicketInvoice();

        $summaryResponse = $this->get(route('reports.travel_vendor', ['tab' => 'summary']));
        $summaryResponse->assertOk();
        $row = $summaryResponse->viewData('summary')->firstWhere('supplier.id', $this->supplier->id);
        $this->assertNotNull($row);
        $this->assertSame(1, $row->lines_count);
        $this->assertEquals(1135, $row->total_payable);
        $this->assertEquals(1135, $row->outstanding);

        $spendResponse = $this->get(route('reports.travel_vendor', ['tab' => 'spend']));
        $spendResponse->assertOk();
        $spendRow = $spendResponse->viewData('spendByType')
            ->first(fn ($r) => $r->supplier === $this->supplier->name && $r->service_type === 'ticket');
        $this->assertNotNull($spendRow);
        $this->assertSame(1, $spendRow->lines_count);
        $this->assertEquals(1135, $spendRow->total_payable);
    }

    public function test_sales_register_by_service_and_by_customer_include_ticket_sale_invoice(): void
    {
        $invoice = $this->createTicketInvoice();

        $registerResponse = $this->get(route('reports.travel_sales', ['tab' => 'register']));
        $registerResponse->assertOk();
        $register = $registerResponse->viewData('register');
        $row = $register->firstWhere('id', $invoice->id);
        $this->assertNotNull($row);
        $this->assertSame('sale', $row->invoice_type);
        $this->assertEquals(1125, $row->total_receivable);
        $this->assertEquals(1135, $row->total_payable);
        $this->assertEquals(95, $row->total_income);
        $this->assertEquals(1125, $row->outstandingAmount());

        // Filtering to invoice_type=tour should now exclude it.
        $tourOnly = $this->get(route('reports.travel_sales', ['tab' => 'register', 'invoice_type' => 'tour']));
        $tourOnly->assertOk();
        $this->assertNull($tourOnly->viewData('register')->firstWhere('id', $invoice->id));

        $byServiceResponse = $this->get(route('reports.travel_sales', ['tab' => 'by_service']));
        $byServiceResponse->assertOk();
        $ticketRow = $byServiceResponse->viewData('byService')->firstWhere('service_type', 'ticket');
        $this->assertNotNull($ticketRow);
        $this->assertSame(1, $ticketRow->lines_count);
        $this->assertEquals(1125, $ticketRow->total_receivable);
        $this->assertEquals(1135, $ticketRow->total_payable);
        $this->assertEquals(95, $ticketRow->total_income);

        $byCustomerResponse = $this->get(route('reports.travel_sales', ['tab' => 'by_customer']));
        $byCustomerResponse->assertOk();
        $customerRow = $byCustomerResponse->viewData('byCustomer')->firstWhere('customer', $this->customer->name);
        $this->assertNotNull($customerRow);
        $this->assertEquals(1125, $customerRow->total_receivable);
        $this->assertEquals(95, $customerRow->total_income);
        $this->assertEquals(1125, $customerRow->total_outstanding);
    }

    public function test_accounts_receivables_and_payables_include_ticket_sale_invoice(): void
    {
        $invoice = $this->createTicketInvoice();

        $recvResponse = $this->get(route('reports.travel_accounts', ['tab' => 'receivables']));
        $recvResponse->assertOk();
        $recvRow = $recvResponse->viewData('receivables')->firstWhere('invoice.id', $invoice->id);
        $this->assertNotNull($recvRow);
        $this->assertEquals(1125, $recvRow->outstanding);

        $payResponse = $this->get(route('reports.travel_accounts', ['tab' => 'payables']));
        $payResponse->assertOk();
        $payRow = $payResponse->viewData('payables')->firstWhere('supplier.id', $this->supplier->id);
        $this->assertNotNull($payRow);
        $this->assertEquals(1135, $payRow->total_payable);
        $this->assertEquals(1135, $payRow->outstanding);
    }

    public function test_voided_ticket_reports_only_the_supplier_deduction_as_payable(): void
    {
        $invoice = $this->createTicketInvoice();
        $line = $invoice->lines->first();

        $this->post(route('ticket_invoices.lines.void', [$invoice->id, $line->id]), [
            'void_deduction_supplier' => 30,
            'void_deduction_company' => 20,
        ])->assertRedirect(route('ticket_invoices.show', $invoice->id));

        $spendResponse = $this->get(route('reports.travel_vendor', ['tab' => 'spend']));
        $spendRow = $spendResponse->viewData('spendByType')
            ->first(fn ($r) => $r->supplier === $this->supplier->name && $r->service_type === 'ticket');
        $this->assertNotNull($spendRow);
        $this->assertEquals(30, $spendRow->total_payable);
    }
}
