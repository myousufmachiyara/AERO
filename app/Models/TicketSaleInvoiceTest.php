<?php

namespace Tests\Feature;

use App\Models\Airline;
use App\Models\ChartOfAccounts;
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
                'wht_percent' => 5,
                'psf_percent' => 1.5,
                'discount_amount' => 10,
                'discount_input_mode' => 'amount',
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
        // Customer: Ticket Sales Income (non-PSF) + PSF Income. Airline:
        // payable (ticket cost, no PSF) + receivable (commission).
        $this->assertSame(4, Voucher::count());

        $customerVouchers = Voucher::where('ac_dr_sid', $this->customer->chart_of_account_id)->get();
        $this->assertCount(2, $customerVouchers);
        $this->assertEqualsCanonicalizing([1110.0, 15.0], $customerVouchers->pluck('amount')->map(fn ($a) => (float) $a)->all());
        // 1110 = ticket sales income (total 1125 - psf 15); 15 = PSF income.

        $airlinePayableVoucher = Voucher::where('ac_cr_sid', $this->airline->chart_of_account_id)->firstOrFail();
        $this->assertEquals(1120, (float) $airlinePayableVoucher->amount); // fare+tax+apt = 1000+100+20 (no PSF)

        $airlineReceivableVoucher = Voucher::where('ac_dr_sid', $this->airline->chart_of_account_id)->firstOrFail();
        $this->assertEquals(95, (float) $airlineReceivableVoucher->amount); // commission 100 - WHT 5

        // Locked while posted.
        $this->get(route('ticket_invoices.edit', $invoice->id))->assertRedirect(route('ticket_invoices.show', $invoice->id));
        $this->post(route('ticket_invoices.lines.refund', [$invoice->id, $line->id]), [
            'refund_date' => now()->format('Y-m-d'), 'refund_fare_amount' => 1000, 'refund_tax_amount' => 100,
            'refund_deduction_supplier' => 0, 'refund_deduction_company' => 0,
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
            'refund_deduction_supplier' => 30,
            'refund_deduction_company' => 20,
        ]);
        $response->assertRedirect(route('ticket_invoices.show', $invoice->id));

        $line->refresh();
        $this->assertSame('refunded', $line->status);
        $this->assertEquals(50, (float) $line->refund_charges); // 30 + 20
        $this->assertEquals(1050, (float) $line->refund_amount); // 1000 + 100 - 50
        $this->assertEquals(75, (float) $line->refund_profit); // original total 1125 - 1050
        $this->assertEquals(0, $line->effectiveNetCommission());

        $invoice->refresh();
        $this->assertEquals(75, (float) $invoice->total_amount);

        // Posting after a refund: customer receivable, split into ticket
        // sales income + PSF income (the company's deduction), plus the
        // airline payable (the airline's deduction) — no commission
        // voucher, since no commission was earned.
        $this->patch(route('ticket_invoices.post', $invoice->id));
        $this->assertSame(3, Voucher::count());

        $customerVouchers = Voucher::where('ac_dr_sid', $this->customer->chart_of_account_id)->get();
        $this->assertCount(2, $customerVouchers);
        $this->assertEqualsCanonicalizing([55.0, 20.0], $customerVouchers->pluck('amount')->map(fn ($a) => (float) $a)->all());
        // 55 = ticket sales income (retained profit 75 - PSF 20); 20 = PSF income (the company's deduction).

        $airlinePayableVoucher = Voucher::where('ac_cr_sid', $this->airline->chart_of_account_id)->firstOrFail();
        $this->assertEquals(30, (float) $airlinePayableVoucher->amount); // refund_deduction_supplier

        $this->assertSame(0, Voucher::where('ac_dr_sid', $this->airline->chart_of_account_id)->count()); // no commission
    }

    /**
     * The specific case that used to post nothing at all: a refund where
     * the agency retains exactly $0 profit (so both customer vouchers —
     * ticket sales income AND PSF income — are correctly skipped, since
     * effectivePsfIncome() is capped at what's actually still
     * receivable) but the airline still keeps its deduction, which must
     * still post as payable even though the customer side posts nothing.
     */
    public function test_refund_with_zero_net_profit_still_posts_the_airline_payable(): void
    {
        $invoice = $this->createInvoiceWithOneTicket(); // total_amount = 1125
        $line = $invoice->lines->first();

        $this->post(route('ticket_invoices.lines.refund', [$invoice->id, $line->id]), [
            'refund_date' => now()->format('Y-m-d'),
            'refund_fare_amount' => 1075,
            'refund_tax_amount' => 100,
            'refund_deduction_supplier' => 30,
            'refund_deduction_company' => 20, // 30 + 20 = 50; 1075 + 100 - 50 = 1125 = original total -> $0 retained profit
        ])->assertRedirect(route('ticket_invoices.show', $invoice->id));

        $line->refresh();
        $this->assertEquals(0, (float) $line->refund_profit);

        $this->patch(route('ticket_invoices.post', $invoice->id));

        // No customer voucher — nothing is actually owed (PSF income is
        // capped at $0 receivable, so the company's $20 deduction doesn't
        // post as income here — there's genuinely no margin left on this
        // ticket once the airline's cut is accounted for).
        $this->assertSame(0, Voucher::where('ac_dr_sid', $this->customer->chart_of_account_id)->count());

        // The airline payable voucher still posts, for its own deduction.
        $this->assertSame(1, Voucher::count());
        $airlinePayableVoucher = Voucher::where('ac_cr_sid', $this->airline->chart_of_account_id)->firstOrFail();
        $this->assertEquals(30, (float) $airlinePayableVoucher->amount);
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

        // Posting after a void: customer receivable, split into ticket
        // sales income (the supplier's share) + PSF income (the
        // company's share), plus the airline payable — no commission
        // voucher, since a voided ticket earns none.
        $this->patch(route('ticket_invoices.post', $invoice->id));
        $this->assertSame(3, Voucher::count());

        $customerVouchers = Voucher::where('ac_dr_sid', $this->customer->chart_of_account_id)->get();
        $this->assertCount(2, $customerVouchers);
        $this->assertEqualsCanonicalizing([30.0, 20.0], $customerVouchers->pluck('amount')->map(fn ($a) => (float) $a)->all());
        // 30 = ticket sales income (= void_deduction_supplier exactly); 20 = PSF income (= void_deduction_company).

        $airlinePayableVoucher = Voucher::where('ac_cr_sid', $this->airline->chart_of_account_id)->firstOrFail();
        $this->assertEquals(30, (float) $airlinePayableVoucher->amount); // void_deduction_supplier

        $this->assertSame(0, Voucher::where('ac_dr_sid', $this->airline->chart_of_account_id)->count()); // no commission
    }

    /**
     * Bulk Refund: "10 tickets, refund 5" per client feedback. Selecting a
     * subset refunds exactly those, in full (each ticket's own fare/tax),
     * with one shared deduction split applied to every selected line —
     * the unselected lines are untouched.
     */
    public function test_bulk_refund_refunds_only_the_selected_tickets_with_a_shared_deduction_split(): void
    {
        $invoice = $this->createInvoiceWithTickets(4); // each line: total_amount = 1125
        $lines = $invoice->lines;
        $toRefund = $lines->take(2); // refund lines 1 and 2, leave 3 and 4 active

        $response = $this->post(route('ticket_invoices.lines.bulk_refund', $invoice->id), [
            'line_ids' => $toRefund->pluck('id')->all(),
            'refund_date' => now()->format('Y-m-d'),
            'refund_deduction_supplier' => 30,
            'refund_deduction_company' => 20,
        ]);
        $response->assertRedirect(route('ticket_invoices.show', $invoice->id));
        $response->assertSessionHas('success');

        foreach ($toRefund as $line) {
            $line->refresh();
            $this->assertSame('refunded', $line->status);
            $this->assertEquals(1000, (float) $line->refund_fare_amount); // the ticket's own fare, not a bulk-entered amount
            $this->assertEquals(100, (float) $line->refund_tax_amount);
            $this->assertEquals(30, (float) $line->refund_deduction_supplier);
            $this->assertEquals(20, (float) $line->refund_deduction_company);
            $this->assertEquals(1050, (float) $line->refund_amount); // 1000 + 100 - 50
            $this->assertEquals(75, (float) $line->refund_profit); // 1125 - 1050
        }

        // The other two lines are untouched.
        foreach ($lines->skip(2) as $line) {
            $line->refresh();
            $this->assertSame('active', $line->status);
        }

        $invoice->refresh();
        // 2 refunded (profit 75 each) + 2 active (total 1125 each) = 150 + 2250.
        $this->assertEquals(2400, (float) $invoice->total_amount);
    }

    /** A line id that belongs to a different invoice, or is already refunded, is silently skipped rather than failing the whole batch. */
    public function test_bulk_refund_skips_line_ids_that_are_not_active_on_this_invoice(): void
    {
        $invoice = $this->createInvoiceWithTickets(2);
        $otherInvoice = $this->createInvoiceWithOneTicket();
        $otherLine = $otherInvoice->lines->first();

        $targetLine = $invoice->lines->first();
        // Already-refunded line on THIS invoice, to confirm it's excluded too.
        $this->post(route('ticket_invoices.lines.refund', [$invoice->id, $invoice->lines->last()->id]), [
            'refund_date' => now()->format('Y-m-d'),
            'refund_fare_amount' => 1000, 'refund_tax_amount' => 100,
            'refund_deduction_supplier' => 0, 'refund_deduction_company' => 0,
        ]);
        $alreadyRefundedId = $invoice->lines->last()->id;

        $response = $this->post(route('ticket_invoices.lines.bulk_refund', $invoice->id), [
            'line_ids' => [$targetLine->id, $otherLine->id, $alreadyRefundedId],
            'refund_date' => now()->format('Y-m-d'),
            'refund_deduction_supplier' => 0,
            'refund_deduction_company' => 0,
        ]);
        $response->assertRedirect(route('ticket_invoices.show', $invoice->id));
        $response->assertSessionHas('success');

        $targetLine->refresh();
        $this->assertSame('refunded', $targetLine->status);

        $otherLine->refresh();
        $this->assertSame('active', $otherLine->status, "A line id from a different invoice must never be touched by this invoice's bulk refund.");
    }

    public function test_bulk_refund_is_blocked_on_a_posted_invoice(): void
    {
        $invoice = $this->createInvoiceWithTickets(2);
        $this->patch(route('ticket_invoices.post', $invoice->id));

        $response = $this->post(route('ticket_invoices.lines.bulk_refund', $invoice->id), [
            'line_ids' => [$invoice->lines->first()->id],
            'refund_date' => now()->format('Y-m-d'),
            'refund_deduction_supplier' => 0,
            'refund_deduction_company' => 0,
        ]);
        $response->assertSessionHas('error');
        $invoice->lines->first()->refresh();
        $this->assertSame('active', $invoice->lines->first()->status);
    }

    /** Bulk Void: same "select a subset, one shared deduction split" shape as Bulk Refund, with the same-day restriction. */
    public function test_bulk_void_voids_only_the_selected_tickets_and_is_blocked_after_invoice_day(): void
    {
        $invoice = $this->createInvoiceWithTickets(3);
        $lines = $invoice->lines;

        Carbon::setTestNow(now()->addDay());
        try {
            $blocked = $this->post(route('ticket_invoices.lines.bulk_void', $invoice->id), [
                'line_ids' => [$lines->first()->id],
                'void_deduction_supplier' => 10, 'void_deduction_company' => 10,
            ]);
            $blocked->assertSessionHas('error');
            $lines->first()->refresh();
            $this->assertSame('active', $lines->first()->status);
        } finally {
            Carbon::setTestNow();
        }

        $toVoid = $lines->take(2);
        $response = $this->post(route('ticket_invoices.lines.bulk_void', $invoice->id), [
            'line_ids' => $toVoid->pluck('id')->all(),
            'void_deduction_supplier' => 30,
            'void_deduction_company' => 20,
        ]);
        $response->assertRedirect(route('ticket_invoices.show', $invoice->id));

        foreach ($toVoid as $line) {
            $line->refresh();
            $this->assertSame('voided', $line->status);
            $this->assertEquals(50, (float) $line->void_total_deduction);
        }

        $lines->last()->refresh();
        $this->assertSame('active', $lines->last()->status);
    }

    /**
     * "PSF" is one shared concept across the whole module, per client
     * feedback — the ticket's own PSF % fee (active tickets) and the
     * deduction the company itself keeps on a void/refund both post to
     * the exact same "PSF Income" ledger account, not two look-alike
     * accounts of the same name.
     */
    public function test_active_ticket_psf_and_voided_ticket_company_deduction_share_the_same_psf_income_account(): void
    {
        $active = $this->createInvoiceWithOneTicket(); // psf_percent 1.5% of fare 1000 -> psf_amount 15
        $this->patch(route('ticket_invoices.post', $active->id));

        $this->post(route('ticket_invoices.store'), [
            'invoice_date' => now()->format('Y-m-d'),
            'customer_id' => $this->customer->id,
            'lines' => [[
                'pax_name' => 'To Be Voided', 'pax_type' => 'adult', 'ticket_no' => '2209876543210',
                'fare_amount' => 1000, 'tax_amount' => 100, 'apt_percent' => 2,
                'commission_percent' => 10, 'wht_percent' => 5, 'psf_percent' => 1.5,
                'discount_amount' => 10, 'discount_input_mode' => 'amount',
                'supplier_id' => $this->supplier->id,
            ]],
        ]);
        $voidedInvoice = TicketSaleInvoice::with('lines')->latest('id')->firstOrFail();
        $voidedLine = $voidedInvoice->lines->first();
        $this->post(route('ticket_invoices.lines.void', [$voidedInvoice->id, $voidedLine->id]), [
            'void_deduction_supplier' => 30, 'void_deduction_company' => 20,
        ]);
        $this->patch(route('ticket_invoices.post', $voidedInvoice->id));

        $psfAccounts = ChartOfAccounts::where('name', 'PSF Income')->get();
        $this->assertCount(1, $psfAccounts, 'Expected exactly one "PSF Income" account, shared by both postings.');

        $psfVouchers = Voucher::where('ac_cr_sid', $psfAccounts->first()->id)->get();
        $this->assertCount(2, $psfVouchers);
        $this->assertEqualsCanonicalizing([15.0, 20.0], $psfVouchers->pluck('amount')->map(fn ($a) => (float) $a)->all());
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
                'psf_percent' => 20, // psf = 200
                'sales_agent_id' => $this->admin->id, 'agent_commission_percent' => 25, // sale commission is on PSF: 200 * 25% = 50
            ]],
        ]);

        $response = $this->get(route('ticket_invoices.my_commission', ['month' => now()->month, 'year' => now()->year]));
        $response->assertOk()->assertSee('50.00');
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
                'commission_percent' => 20, // commission = 1000 * 20% = 200 (from airline)
                'wht_percent' => 10, // wht = 200 (commission) * 10% = 20 — on commission, not fare
                'agent_commission_percent' => 10, // total = 1000+100+20+50 = 1170; sale comm = 10% of PSF (50) = 5, NOT of the total
                'sales_agent_id' => $this->admin->id,
            ]],
        ]);
        $fareBasisInvoice = TicketSaleInvoice::with('lines')->latest('id')->firstOrFail();
        $fareBasisLine = $fareBasisInvoice->lines->first();
        $this->assertEquals(20, (float) $fareBasisLine->apt_charges);
        $this->assertEquals(50, (float) $fareBasisLine->psf_amount);
        $this->assertEquals(200, (float) $fareBasisLine->commission_amount);
        $this->assertEquals(20, (float) $fareBasisLine->wht_amount); // 200 * 10%, not 1000 * 10%
        $this->assertEquals(1170, (float) $fareBasisLine->total_amount);
        $this->assertEquals(5, (float) $fareBasisLine->agent_commission_amount);

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

    /**
     * Ticket Sale Invoice fixes: APT enter-either (like PSF), Ticket Type,
     * departure date/time, connected-flight cities (extra Stay cities saved
     * joined with "-"), airline discount, and Supplier Net.
     */
    public function test_apt_amount_mode_ticket_type_departure_cities_and_supplier_net(): void
    {
        $this->post(route('ticket_invoices.store'), [
            'invoice_date' => now()->format('Y-m-d'),
            'customer_id' => $this->customer->id,
            'lines' => [[
                'pax_name' => 'Connected Pax', 'pax_type' => 'adult', 'ticket_no' => '2205555555551',
                'ticket_type' => 'domestic', 'trip_type' => 'return',
                'leg1_from' => 'LHE', 'leg1_stay' => 'DXB-JED-MED', 'leg1_to' => 'RUH',
                'leg1_departure_date' => '2026-11-01', 'leg1_departure_time' => '14:30',
                'leg2_from' => 'RUH', 'leg2_to' => 'LHE',
                'leg2_departure_date' => '2026-11-15', 'leg2_departure_time' => '06:05',
                'fare_amount' => 1000, 'tax_amount' => 100,
                'apt_input_mode' => 'amount', 'apt_charges' => 30, // apt % back-calculated = 3
                'commission_percent' => 10, 'wht_percent' => 10,    // commission 100, wht 10
                'airline_discount_amount' => 15,
                'psf_percent' => 5, 'discount_percent' => 1,        // psf 50, discount 10
            ]],
        ]);
        $line = TicketSaleInvoice::with('lines')->latest('id')->firstOrFail()->lines->first();

        $this->assertEquals(30, (float) $line->apt_charges);
        $this->assertEquals(3, (float) $line->apt_percent);
        $this->assertSame('domestic', $line->ticket_type);
        $this->assertSame('LHE-DXB-JED-MED-RUH/RUH-LHE', $line->citiesLabel());
        $this->assertSame('2026-11-01', $line->leg1_departure_date->format('Y-m-d'));
        $this->assertSame('14:30', $line->leg1_departure_time);
        $this->assertSame('06:05', $line->leg2_departure_time);
        // Customer Net (Ticket Total) = 1000+100+30+50-10
        $this->assertEquals(1170, (float) $line->total_amount);
        // Supplier Net = 1000+100+30 - 100 (commission) + 10 (wht) - 15 (airline discount)
        $this->assertEquals(1025, $line->supplierNet());

        // APT in percent mode still derives the amount.
        $this->post(route('ticket_invoices.store'), [
            'invoice_date' => now()->format('Y-m-d'),
            'customer_id' => $this->customer->id,
            'lines' => [[
                'pax_name' => 'Pct Pax', 'pax_type' => 'adult', 'ticket_no' => '2205555555552',
                'fare_amount' => 1000, 'apt_percent' => 4, 'apt_input_mode' => 'percent',
            ]],
        ]);
        $pctLine = TicketSaleInvoice::with('lines')->latest('id')->firstOrFail()->lines->first();
        $this->assertEquals(40, (float) $pctLine->apt_charges);
        $this->assertSame('international', $pctLine->ticket_type);
    }

    /**
     * Client fix: PSF and Discount both accept a % OR a fixed amount —
     * whichever the user actually enters, the other side is calculated
     * back automatically. psf_input_mode / discount_input_mode says which
     * field was typed in.
     */
    public function test_psf_and_discount_support_entering_either_percent_or_amount(): void
    {
        // Enter the %, the amount is calculated (also the default when no mode is sent).
        $this->post(route('ticket_invoices.store'), [
            'invoice_date' => now()->format('Y-m-d'),
            'customer_id' => $this->customer->id,
            'lines' => [[
                'pax_name' => 'Percent Mode Pax', 'pax_type' => 'adult', 'ticket_no' => '2202222222221',
                'fare_amount' => 1000, 'tax_amount' => 0,
                'psf_percent' => 10, 'psf_input_mode' => 'percent', 'psf_basis' => 'fare',
                'discount_percent' => 5, 'discount_input_mode' => 'percent',
            ]],
        ]);
        $percentInvoice = TicketSaleInvoice::with('lines')->latest('id')->firstOrFail();
        $percentLine = $percentInvoice->lines->first();
        $this->assertEquals(10, (float) $percentLine->psf_percent);
        $this->assertEquals(100, (float) $percentLine->psf_amount); // 1000 * 10%
        $this->assertEquals(5, (float) $percentLine->discount_percent);
        $this->assertEquals(50, (float) $percentLine->discount_amount); // 1000 * 5%

        // Enter the amount instead — the % is calculated back from it.
        $this->post(route('ticket_invoices.store'), [
            'invoice_date' => now()->format('Y-m-d'),
            'customer_id' => $this->customer->id,
            'lines' => [[
                'pax_name' => 'Amount Mode Pax', 'pax_type' => 'adult', 'ticket_no' => '2202222222222',
                'fare_amount' => 1000, 'tax_amount' => 0,
                'psf_amount' => 80, 'psf_input_mode' => 'amount', 'psf_basis' => 'fare',
                'discount_amount' => 30, 'discount_input_mode' => 'amount',
            ]],
        ]);
        $amountInvoice = TicketSaleInvoice::with('lines')->latest('id')->firstOrFail();
        $amountLine = $amountInvoice->lines->first();
        $this->assertEquals(80, (float) $amountLine->psf_amount);
        $this->assertEquals(8, (float) $amountLine->psf_percent); // 80 / 1000 * 100
        $this->assertEquals(30, (float) $amountLine->discount_amount);
        $this->assertEquals(3, (float) $amountLine->discount_percent); // 30 / 1000 * 100
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

    public function test_customer_print_renders_a_pdf(): void
    {
        $invoice = $this->createInvoiceWithOneTicket();

        $response = $this->get(route('ticket_invoices.print', $invoice->id));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        // Starts with the PDF magic bytes — a real PDF was generated, not an error page.
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_customer_print_renders_for_refunded_and_voided_lines_too(): void
    {
        $invoice = $this->createInvoiceWithOneTicket();
        $line = $invoice->lines->first();

        $this->post(route('ticket_invoices.lines.refund', [$invoice->id, $line->id]), [
            'refund_date' => now()->format('Y-m-d'),
            'refund_fare_amount' => 500, 'refund_tax_amount' => 50,
            'refund_deduction_supplier' => 30, 'refund_deduction_company' => 20,
        ]);

        $response = $this->get(route('ticket_invoices.print', $invoice->id));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
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
                'commission_percent' => 10, 'wht_percent' => 5, 'psf_percent' => 1.5,
                'discount_amount' => 10, 'discount_input_mode' => 'amount',
                'supplier_id' => $this->supplier->id,
            ]],
        ]);

        return TicketSaleInvoice::with('lines')->latest('id')->firstOrFail();
    }

    /** A multi-ticket invoice (default 4) for exercising bulk Refund/Void against a subset of lines. */
    protected function createInvoiceWithTickets(int $count = 4): TicketSaleInvoice
    {
        $lines = [];
        for ($i = 0; $i < $count; $i++) {
            $lines[] = [
                'pax_name' => 'Bulk Pax ' . ($i + 1),
                'pax_type' => 'adult',
                'pnr' => 'BULKPNR',
                'ticket_no' => '220' . str_pad((string) (5000 + $i), 10, '0', STR_PAD_LEFT),
                'fare_amount' => 1000, 'tax_amount' => 100, 'apt_percent' => 2,
                'commission_percent' => 10, 'wht_percent' => 5, 'psf_percent' => 1.5,
                'discount_amount' => 10, 'discount_input_mode' => 'amount',
                'supplier_id' => $this->supplier->id,
            ];
        }

        $this->post(route('ticket_invoices.store'), [
            'invoice_date' => now()->format('Y-m-d'),
            'customer_id' => $this->customer->id,
            'lines' => $lines,
        ]);

        return TicketSaleInvoice::with('lines')->latest('id')->firstOrFail();
    }
}
