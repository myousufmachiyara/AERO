<?php

namespace Tests\Feature\E2E;

use App\Models\TicketInvoiceLedgerEntry;
use App\Models\TicketSaleInvoice;
use App\Models\TicketSaleInvoiceLine;
use App\Models\User;
use App\Models\Voucher;
use App\Services\CustomerStatementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Ticket Sale Invoice — end-to-end: CRUD, validation, Post/Unpost, Refund
 * and Void (single + bulk, pending + posted), the vouchers/ledgers each
 * action writes, prints, My Commission and permissions.
 *
 * Reference ticket (used throughout):
 *   Fare 1000, Tax 100, APT 2% = 20, Commission 10% = 100, WHT 5,
 *   PSF 1.5% = 15, Discount 10
 *   → Customer total 1125, Supplier net 1025, Airline payable 1120,
 *     Net commission 95, Income 100.
 */
class TicketSaleInvoiceE2ETest extends TestCase
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

    protected function ticket(string $pax, string $ticketNo, array $over = []): array
    {
        return array_merge([
            'pax_name' => $pax, 'pax_type' => 'adult', 'pnr' => 'ABC123', 'ticket_no' => $ticketNo,
            'trip_type' => 'one_way', 'leg1_from' => 'KHI', 'leg1_to' => 'JED',
            'supplier_id' => $this->supplier->id,
            'fare_amount' => 1000, 'tax_amount' => 100,
            'apt_percent' => 2, 'apt_input_mode' => 'percent',
            'commission_percent' => 10, 'commission_input_mode' => 'percent',
            'wht_amount' => 5, 'wht_input_mode' => 'amount',
            'psf_percent' => 1.5, 'psf_input_mode' => 'percent', 'psf_basis' => 'fare',
            'discount_amount' => 10, 'discount_input_mode' => 'amount',
        ], $over);
    }

    protected function createInvoice(array $tickets, string $date = '2026-11-20'): TicketSaleInvoice
    {
        $this->postJson(route('ticket_invoices.store'), [
            'invoice_date' => $date, 'customer_id' => $this->customer->id, 'lines' => $tickets,
        ])->assertOk()->assertJsonStructure(['redirect']);

        return TicketSaleInvoice::with('lines')->latest('id')->firstOrFail();
    }

    protected function refundPayload(array $over = []): array
    {
        return array_merge([
            'refund_date' => '2026-11-20', 'refund_fare_amount' => 1000, 'refund_tax_amount' => 100,
            'refund_deduction_supplier' => 30, 'refund_deduction_company' => 20,
        ], $over);
    }

    // ═══════════════════════ CRUD ═══════════════════════

    public function test_crud_01_create_calculates_every_figure_and_numbers_invoice(): void
    {
        $this->get(route('ticket_invoices.create'))->assertOk();
        $inv = $this->createInvoice([$this->ticket('John Doe', '2201234567890')]);
        $l = $inv->lines->first();

        $this->assertSame('SIN-26-0001', $inv->invoice_no);
        $this->assertSame('pending', $inv->status);
        $this->assertEquals(20, (float) $l->apt_charges);
        $this->assertEquals(100, (float) $l->commission_amount);
        $this->assertEquals(5, (float) $l->wht_amount);
        $this->assertEquals(15, (float) $l->psf_amount);
        $this->assertEquals(1125, (float) $l->total_amount);
        $this->assertEquals(1025, $l->supplierNet());
        $this->assertSame('220-1234-567-890', $l->ticket_no);
        $this->assertSame($this->airline->id, $l->airline_id); // auto-detected from "220"
        $this->assertEquals(1125, (float) $inv->total_amount);
        $this->assertSame(0, Voucher::count(), 'A pending invoice must not touch the ledger.');

        $this->assertSame('SIN-26-0002', $this->createInvoice([$this->ticket('Jane', '2201234567891')])->invoice_no);
    }

    public function test_crud_02_index_show_edit_pages_and_search(): void
    {
        $inv = $this->createInvoice([$this->ticket('John Doe', '2201234567890', ['pnr' => 'XYZ789'])]);

        $this->get(route('ticket_invoices.index'))->assertOk()->assertSee($inv->invoice_no);
        foreach (['2201234567890', '220-1234', 'XYZ789', 'Vacations', 'SIN-26-0001'] as $term) {
            $this->get(route('ticket_invoices.index', ['search' => $term]))->assertOk()->assertSee($inv->invoice_no);
        }
        $this->get(route('ticket_invoices.index', ['search' => 'NOPE-999']))->assertOk()->assertDontSee($inv->invoice_no);
        $this->get(route('ticket_invoices.index', ['status' => 'posted']))->assertOk()->assertDontSee($inv->invoice_no);
        $this->get(route('ticket_invoices.show', $inv->id))->assertOk()->assertSee('John Doe');
        $this->get(route('ticket_invoices.edit', $inv->id))->assertOk();
    }

    public function test_crud_03_update_adds_changes_and_removes_tickets(): void
    {
        $inv = $this->createInvoice([$this->ticket('A', '2201111111111'), $this->ticket('B', '2201111111112')]);
        [$a, $b] = $inv->lines;

        $this->putJson(route('ticket_invoices.update', $inv->id), [
            'invoice_date' => '2026-11-20', 'customer_id' => $this->customer->id,
            'lines' => [
                $this->ticket('A renamed', '2201111111111', ['id' => $a->id, 'fare_amount' => 2000]),
                $this->ticket('C new', '1761111111113'),
            ],
        ])->assertOk();

        $inv->refresh()->load('lines');
        $this->assertCount(2, $inv->lines);
        $this->assertNull(TicketSaleInvoiceLine::find($b->id), 'Removed ticket is deleted.');
        $this->assertSame('A renamed', $a->fresh()->pax_name);
        $this->assertEquals(2000, (float) $a->fresh()->fare_amount);
        $this->assertSame($this->airline2->id, $inv->lines->firstWhere('pax_name', 'C new')->airline_id);
        $this->assertEquals($inv->lines->sum('total_amount'), (float) $inv->total_amount);
    }

    public function test_crud_04_delete_pending_frees_ticket_numbers_and_posted_cannot_be_deleted(): void
    {
        $inv = $this->createInvoice([$this->ticket('A', '2201111111111')]);
        $this->delete(route('ticket_invoices.destroy', $inv->id))->assertSessionHas('success');
        $this->assertSoftDeleted($inv);
        $this->assertSame(0, TicketSaleInvoiceLine::count());

        // Ticket number can be used again, and the next number doesn't reuse SIN-26-0001.
        $again = $this->createInvoice([$this->ticket('A', '2201111111111')]);
        $this->assertSame('SIN-26-0002', $again->invoice_no);

        $this->patch(route('ticket_invoices.post', $again->id));
        $this->delete(route('ticket_invoices.destroy', $again->id))->assertSessionHas('error');
        $this->assertNotSoftDeleted($again);
    }

    public function test_crud_05_validation_errors_come_back_as_json_without_saving(): void
    {
        $base = ['invoice_date' => '2026-11-20', 'customer_id' => $this->customer->id];
        $this->postJson(route('ticket_invoices.store'), ['lines' => [$this->ticket('A', '')]])
            ->assertStatus(422)->assertJsonValidationErrors(['customer_id', 'invoice_date']);
        $this->postJson(route('ticket_invoices.store'), $base + ['lines' => [$this->ticket('A', '22012')]])
            ->assertStatus(422)->assertJsonValidationErrors(['lines.0.ticket_no']);
        $this->postJson(route('ticket_invoices.store'), $base + ['lines' => [$this->ticket('A', '2201111111111'), $this->ticket('B', '220-1111-111-111')]])
            ->assertStatus(422)->assertJsonValidationErrors(['lines.1.ticket_no']);
        $this->postJson(route('ticket_invoices.store'), $base + ['lines' => [$this->ticket('A', '2201111111111', ['fare_amount' => -5])]])
            ->assertStatus(422)->assertJsonValidationErrors(['lines.0.fare_amount']);
        $this->assertSame(0, TicketSaleInvoice::count());

        $this->createInvoice([$this->ticket('A', '2201111111111')]);
        $this->postJson(route('ticket_invoices.store'), $base + ['lines' => [$this->ticket('B', '2201111111111')]])
            ->assertStatus(422)->assertJsonValidationErrors(['lines.0.ticket_no']);
    }

    // ═══════════════════════ POST / UNPOST ═══════════════════════

    public function test_ledger_01_post_writes_the_four_vouchers_and_balances(): void
    {
        $inv = $this->createInvoice([$this->ticket('A', '2201111111111'), $this->ticket('B', '2201111111112')]);
        $this->patch(route('ticket_invoices.post', $inv->id))->assertSessionHas('success');
        $inv->refresh();

        $this->assertSame('posted', $inv->status);
        $this->assertNotNull($inv->posted_at);
        $this->assertSame(4, Voucher::count());
        $this->assertEquals([
            TicketInvoiceLedgerEntry::TYPE_CUSTOMER => 2220.0,          // (1125 − 15) × 2
            TicketInvoiceLedgerEntry::TYPE_CUSTOMER_PSF => 30.0,
            TicketInvoiceLedgerEntry::TYPE_AIRLINE_PAYABLE => 2240.0,   // 1120 × 2
            TicketInvoiceLedgerEntry::TYPE_AIRLINE_COMMISSION => 190.0, // 95 × 2
        ], $inv->ledgerEntries()->pluck('amount', 'entry_type')->map(fn ($v) => (float) $v)->all());

        $this->assertEquals(2250, $this->balance($this->customer->chart_of_account_id));          // = invoice total
        $this->assertEquals(-2050, $this->balance($this->supplier->chart_of_account_id));         // owe supplier net 1025 × 2
        $this->assertEquals(0, $this->balance($this->airline->chart_of_account_id), 'Ticket payable goes to the supplier, not the airline.');
        $this->assertEquals(-2220, $this->balance($this->systemAccountId('ticket_sales_income_account_name')));
        $this->assertEquals(-30, $this->balance($this->systemAccountId('psf_income_account_name')));
        $this->assertEquals(2240, $this->balance($this->systemAccountId('ticket_cost_expense_account_name')));
        $this->assertEquals(-190, $this->balance($this->systemAccountId('airline_commission_income_account_name')));
        $this->assertBooksBalance();

        // Profit in the ledger = invoice income (PSF − Discount + Commission − WHT) × 2 = 200
        $profit = -($this->balance($this->systemAccountId('ticket_sales_income_account_name'))
            + $this->balance($this->systemAccountId('psf_income_account_name'))
            + $this->balance($this->systemAccountId('airline_commission_income_account_name'))
            + $this->balance($this->systemAccountId('ticket_cost_expense_account_name')));
        $this->assertEquals(200, round($profit, 2));
    }

    public function test_ledger_02_post_splits_payable_vouchers_per_supplier(): void
    {
        $supplier2 = \App\Models\Supplier::factory()->create();
        $supplier2->update(['chart_of_account_id' => app(\App\Services\PartyLedgerService::class)->syncSupplier($supplier2)->id]);

        $inv = $this->createInvoice([$this->ticket('A', '2201111111111'), $this->ticket('B', '1761111111112', ['supplier_id' => $supplier2->id])]);
        $this->patch(route('ticket_invoices.post', $inv->id))->assertSessionHas('success');

        $this->assertEquals(-1025, $this->balance($this->supplier->chart_of_account_id));
        $this->assertEquals(-1025, $this->balance($supplier2->chart_of_account_id));
        $this->assertEquals(0, $this->balance($this->airline->chart_of_account_id));
        $this->assertEquals(0, $this->balance($this->airline2->chart_of_account_id));
        $this->assertSame(2, $inv->ledgerEntries()->where('entry_type', TicketInvoiceLedgerEntry::TYPE_AIRLINE_PAYABLE)->count());
        $this->assertBooksBalance();
    }

    public function test_ledger_04_post_refused_without_a_supplier(): void
    {
        $inv = $this->createInvoice([$this->ticket('A', '2201111111111', ['supplier_id' => null])]);
        $this->patch(route('ticket_invoices.post', $inv->id))->assertSessionHas('error', fn ($m) => str_contains($m, 'supplier'));
        $this->assertSame('pending', $inv->fresh()->status);
        $this->assertSame(0, Voucher::count());
    }

    public function test_ledger_03_unpost_removes_every_voucher_and_repost_rebuilds_them(): void
    {
        $inv = $this->createInvoice([$this->ticket('A', '2201111111111')]);
        $this->patch(route('ticket_invoices.post', $inv->id));
        $posted = $this->allBalances();

        $this->patch(route('ticket_invoices.post', $inv->id))->assertSessionHas('error'); // already posted
        $this->get(route('ticket_invoices.edit', $inv->id))->assertRedirect(route('ticket_invoices.show', $inv->id));
        $this->putJson(route('ticket_invoices.update', $inv->id), ['invoice_date' => '2026-11-20', 'customer_id' => $this->customer->id, 'lines' => [$this->ticket('X', '2201111111111')]])
            ->assertStatus(422);

        $this->patch(route('ticket_invoices.unpost', $inv->id))->assertSessionHas('success');
        $this->assertSame('pending', $inv->fresh()->status);
        $this->assertSame(0, Voucher::count());
        $this->assertSame(0, TicketInvoiceLedgerEntry::count());
        $this->patch(route('ticket_invoices.unpost', $inv->id))->assertSessionHas('error'); // not posted

        $this->patch(route('ticket_invoices.post', $inv->id));
        $this->assertEquals($posted, $this->allBalances());
    }

    public function test_ledger_04_post_fails_cleanly_when_customer_has_no_ledger_account(): void
    {
        $inv = $this->createInvoice([$this->ticket('A', '2201111111111')]);
        $this->customer->update(['chart_of_account_id' => null]);

        $this->patch(route('ticket_invoices.post', $inv->id))->assertSessionHas('error');
        $this->assertSame('pending', $inv->fresh()->status);
        $this->assertSame(0, Voucher::count());
    }

    public function test_ledger_05_adjustment_date_is_the_voucher_date(): void
    {
        $this->postJson(route('ticket_invoices.store'), [
            'invoice_date' => '2026-11-20', 'adjustment_date' => '2026-11-25',
            'customer_id' => $this->customer->id, 'lines' => [$this->ticket('A', '2201111111111')],
        ])->assertOk();
        $inv = TicketSaleInvoice::latest('id')->first();
        $this->patch(route('ticket_invoices.post', $inv->id));
        $this->assertSame(['2026-11-25'], Voucher::pluck('date')->map(fn ($d) => Carbon::parse($d)->toDateString())->unique()->values()->all());
    }

    // ═══════════════════════ REFUND ═══════════════════════

    public function test_refund_01_on_pending_invoice_then_post(): void
    {
        $inv = $this->createInvoice([$this->ticket('A', '2201111111111')]);
        $line = $inv->lines->first();

        $this->postJson(route('ticket_invoices.lines.refund', [$inv->id, $line->id]), $this->refundPayload())->assertOk();
        $line->refresh();
        $this->assertSame('refunded', $line->status);
        $this->assertEquals(1050, (float) $line->refund_amount);  // 1100 − 50
        $this->assertEquals(75, (float) $line->refund_profit);    // 1125 − 1050
        $this->assertEquals(75, (float) $inv->fresh()->total_amount);
        $this->assertSame(0, Voucher::count(), 'Refund on a pending invoice writes no vouchers.');

        $this->patch(route('ticket_invoices.post', $inv->id));
        $this->assertEquals(75, $this->balance($this->customer->chart_of_account_id));
        // No profit on a refunded ticket: everything the customer is still charged goes to the supplier.
        $this->assertEquals(-75, $this->balance($this->supplier->chart_of_account_id));
        $this->assertEquals(0, $this->balance($this->systemAccountId('psf_income_account_name')));
        $this->assertEquals(0, (float) $inv->fresh('lines')->total_income);
        $this->assertBooksBalance();
    }

    public function test_refund_02_on_posted_invoice_credits_then_debits_and_matches_repost(): void
    {
        $inv = $this->createInvoice([$this->ticket('A', '2201111111111'), $this->ticket('B', '2201111111112')]);
        $this->patch(route('ticket_invoices.post', $inv->id));
        $line = $inv->lines->first();

        $this->postJson(route('ticket_invoices.lines.refund', [$inv->id, $line->id]), $this->refundPayload())->assertOk();

        $entries = TicketInvoiceLedgerEntry::where('ticket_sale_invoice_line_id', $line->id)->get();
        $this->assertSame(6, $entries->count()); // 4 reversals + 2 charges (customer sale, supplier payable — no PSF)
        $this->assertTrue($entries->every(fn ($e) => $e->adjustment === 'refund'));
        $this->assertEquals(75 + 1125, $this->balance($this->customer->chart_of_account_id));
        $this->assertEquals(-(75 + 1025), $this->balance($this->supplier->chart_of_account_id));
        $this->assertEquals(100, (float) $inv->fresh('lines')->total_income, 'Only the active ticket earns.');
        $this->assertBooksBalance();
        $viaAdjustment = $this->allBalances();

        // Same end state as unposting and re-posting the refunded invoice.
        $this->patch(route('ticket_invoices.unpost', $inv->id));
        $this->patch(route('ticket_invoices.post', $inv->id));
        $this->assertEquals($viaAdjustment, $this->allBalances());

        $this->get(route('ticket_invoices.show', $inv->id))->assertOk()->assertSee('Ledger Vouchers');
    }

    public function test_refund_03_refund_vouchers_use_the_adjustment_date(): void
    {
        $inv = $this->createInvoice([$this->ticket('A', '2201111111111')]);
        $this->patch(route('ticket_invoices.post', $inv->id));
        $line = $inv->lines->first();
        $this->postJson(route('ticket_invoices.lines.refund', [$inv->id, $line->id]),
            $this->refundPayload(['refund_date' => '2026-11-21', 'refund_adjustment_date' => '2026-11-28']))->assertOk();

        $dates = Voucher::whereIn('id', TicketInvoiceLedgerEntry::where('ticket_sale_invoice_line_id', $line->id)->pluck('voucher_id'))
            ->pluck('date')->map(fn ($d) => Carbon::parse($d)->toDateString())->unique()->values()->all();
        $this->assertSame(['2026-11-28'], $dates);
    }

    public function test_refund_04_rules_required_fields_and_only_active_tickets(): void
    {
        $inv = $this->createInvoice([$this->ticket('A', '2201111111111')]);
        $line = $inv->lines->first();

        $this->postJson(route('ticket_invoices.lines.refund', [$inv->id, $line->id]), [])
            ->assertStatus(422)->assertJsonValidationErrors(['refund_date', 'refund_fare_amount', 'refund_tax_amount']);
        $this->postJson(route('ticket_invoices.lines.refund', [$inv->id, $line->id]), $this->refundPayload())->assertOk();
        $this->postJson(route('ticket_invoices.lines.refund', [$inv->id, $line->id]), $this->refundPayload())->assertStatus(422);
        $this->postJson(route('ticket_invoices.lines.void', [$inv->id, $line->id]), ['void_deduction_supplier' => 0, 'void_deduction_company' => 0])->assertStatus(422);
    }

    public function test_refund_05_cannot_refund_more_than_the_ticket_or_deduct_more_than_refunded(): void
    {
        $inv = $this->createInvoice([$this->ticket('A', '2201111111111')]);
        $line = $inv->lines->first();

        $this->postJson(route('ticket_invoices.lines.refund', [$inv->id, $line->id]), $this->refundPayload(['refund_fare_amount' => 5000]))
            ->assertStatus(422)->assertJsonValidationErrors(['refund_fare_amount']);
        $this->postJson(route('ticket_invoices.lines.refund', [$inv->id, $line->id]), $this->refundPayload(['refund_tax_amount' => 500]))
            ->assertStatus(422)->assertJsonValidationErrors(['refund_tax_amount']);
        $this->postJson(route('ticket_invoices.lines.refund', [$inv->id, $line->id]), $this->refundPayload(['refund_deduction_supplier' => 900, 'refund_deduction_company' => 900]))
            ->assertStatus(422)->assertJsonValidationErrors(['refund_deduction_supplier']);
        $this->assertSame('active', $line->fresh()->status);
    }

    public function test_refund_06_bulk_refund_on_posted_invoice_skips_inactive_tickets(): void
    {
        $inv = $this->createInvoice([$this->ticket('A', '2201111111111'), $this->ticket('B', '2201111111112'), $this->ticket('C', '2201111111113')]);
        $this->patch(route('ticket_invoices.post', $inv->id));
        [$a, $b, $c] = $inv->lines;
        $this->postJson(route('ticket_invoices.lines.refund', [$inv->id, $a->id]), $this->refundPayload())->assertOk();

        $this->postJson(route('ticket_invoices.lines.bulk_refund', $inv->id), [
            'line_ids' => [$a->id, $b->id, $c->id], 'refund_date' => '2026-11-20',
            'refund_deduction_supplier' => 10, 'refund_deduction_company' => 10,
        ])->assertOk()->assertJsonFragment(['message' => '2 ticket(s) refunded. (1 selected ticket(s) were skipped — no longer active.)']);

        $this->assertSame(['refunded', 'refunded', 'refunded'], $inv->lines()->pluck('status')->all());
        $this->assertEquals((float) $inv->fresh()->total_amount, $this->balance($this->customer->chart_of_account_id));
        $this->assertBooksBalance();
    }

    // ═══════════════════════ VOID ═══════════════════════

    public function test_void_01_same_day_on_posted_invoice_matches_repost(): void
    {
        $inv = $this->createInvoice([$this->ticket('A', '2201111111111'), $this->ticket('B', '2201111111112')]);
        $this->patch(route('ticket_invoices.post', $inv->id));
        $line = $inv->lines->first();

        $this->postJson(route('ticket_invoices.lines.void', [$inv->id, $line->id]), ['void_deduction_supplier' => 15, 'void_deduction_company' => 10])->assertOk();
        $this->assertSame('voided', $line->fresh()->status);
        $this->assertEquals(25 + 1125, (float) $inv->fresh()->total_amount);
        $this->assertEquals(25 + 1125, $this->balance($this->customer->chart_of_account_id));
        $this->assertBooksBalance();
        $viaAdjustment = $this->allBalances();

        $this->patch(route('ticket_invoices.unpost', $inv->id));
        $this->patch(route('ticket_invoices.post', $inv->id));
        $this->assertEquals($viaAdjustment, $this->allBalances());
    }

    public function test_void_02_only_on_the_invoice_date(): void
    {
        $inv = $this->createInvoice([$this->ticket('A', '2201111111111')], '2026-11-19');
        $line = $inv->lines->first();

        $this->postJson(route('ticket_invoices.lines.void', [$inv->id, $line->id]), ['void_deduction_supplier' => 0, 'void_deduction_company' => 0])
            ->assertStatus(422);
        $this->postJson(route('ticket_invoices.lines.bulk_void', $inv->id), ['line_ids' => [$line->id], 'void_deduction_supplier' => 0, 'void_deduction_company' => 0])
            ->assertStatus(422);
        $this->assertSame('active', $line->fresh()->status);
    }

    public function test_void_03_deduction_cannot_exceed_the_ticket_total(): void
    {
        $inv = $this->createInvoice([$this->ticket('A', '2201111111111')]);
        $line = $inv->lines->first();
        $this->postJson(route('ticket_invoices.lines.void', [$inv->id, $line->id]), ['void_deduction_supplier' => 2000, 'void_deduction_company' => 0])
            ->assertStatus(422)->assertJsonValidationErrors(['void_deduction_supplier']);
        $this->assertSame('active', $line->fresh()->status);
    }

    public function test_void_04_bulk_void_pending_then_post(): void
    {
        $inv = $this->createInvoice([$this->ticket('A', '2201111111111'), $this->ticket('B', '2201111111112')]);
        $this->postJson(route('ticket_invoices.lines.bulk_void', $inv->id), [
            'line_ids' => $inv->lines->pluck('id')->all(), 'void_deduction_supplier' => 5, 'void_deduction_company' => 5,
        ])->assertOk();
        $this->assertSame(['voided', 'voided'], $inv->lines()->pluck('status')->all());
        $this->assertEquals(20, (float) $inv->fresh()->total_amount);
        $this->assertSame(0, Voucher::count());

        $this->patch(route('ticket_invoices.post', $inv->id));
        $this->assertEquals(20, $this->balance($this->customer->chart_of_account_id));
        $this->assertEquals(-20, $this->balance($this->supplier->chart_of_account_id), 'Both void charges go to the supplier.');
        $this->assertEquals(0, (float) $inv->fresh('lines')->total_income);
        $this->assertBooksBalance();
    }

    // ═══════════════════════ REVERT REFUND / VOID ═══════════════════════

    public function test_summary_01_shows_sale_as_issued_then_refund_and_void_adjustments(): void
    {
        $inv = $this->createInvoice([$this->ticket('A', '2201111111111'), $this->ticket('B', '2201111111112'), $this->ticket('C', '2201111111113')]);
        $this->patch(route('ticket_invoices.post', $inv->id));
        [$a, $b] = $inv->lines;
        $this->postJson(route('ticket_invoices.lines.refund', [$inv->id, $a->id]), $this->refundPayload())->assertOk();          // 1,050 back, 75 kept
        $this->postJson(route('ticket_invoices.lines.void', [$inv->id, $b->id]), ['void_date' => '2026-11-20', 'void_deduction_supplier' => 5, 'void_deduction_company' => 5])->assertOk(); // 1,115 back, 10 kept
        $inv = $inv->fresh('lines');

        $this->assertEquals(3000, (float) $inv->total_fare, 'Totals are the sale as issued.');
        $this->assertEquals(1125 + 75 + 10, (float) $inv->total_amount);
        $this->get(route('ticket_invoices.show', $inv->id))->assertOk()
            ->assertSee('Sale as issued')->assertSee('3,000.00')->assertSee('3,375.00')
            ->assertSee('Less: Refunded to Customer')->assertSee('-1,050.00')
            ->assertSee('Less: Voided, returned to Customer')->assertSee('-1,115.00')
            ->assertSee('1 active · 1 refunded · 1 voided')
            ->assertSee('1,210.00')
            ->assertSee('-1,110.00')
            ->assertSee('Final Receivable from Customer')->assertSee('Balance to receive')
            ->assertSee('Final Payable to Supplier');
        $this->get(route('ticket_invoices.print_detailed', $inv->id))->assertOk()
            ->assertSee('Gross Sale (as issued)')->assertSee('3,375.00')->assertSee('1,210.00');
    }

    public function test_revert_01_refund_on_posted_invoice_restores_the_original_posting(): void
    {
        $inv = $this->createInvoice([$this->ticket('A', '2201111111111'), $this->ticket('B', '2201111111112')]);
        $this->patch(route('ticket_invoices.post', $inv->id));
        $posted = $this->allBalances();
        $line = $inv->lines->first();

        $this->postJson(route('ticket_invoices.lines.refund', [$inv->id, $line->id]), $this->refundPayload())->assertOk();
        $this->assertNotEquals($posted, $this->allBalances());

        $this->postJson(route('ticket_invoices.lines.revert', [$inv->id, $line->id]))->assertOk()->assertJsonPath('redirect', route('ticket_invoices.show', $inv->id));
        $line->refresh();
        $this->assertSame('active', $line->status);
        $this->assertNull($line->refund_amount);
        $this->assertEquals(2250, (float) $inv->fresh()->total_amount);
        $this->assertSame(0, TicketInvoiceLedgerEntry::where('ticket_sale_invoice_line_id', $line->id)->count());
        $this->assertEquals($posted, $this->allBalances(), 'Ledgers are back to the original Post.');
        $this->assertBooksBalance();

        $this->postJson(route('ticket_invoices.lines.revert', [$inv->id, $line->id]))->assertStatus(422);
    }

    public function test_revert_02_void_on_posted_invoice_and_refund_made_before_posting(): void
    {
        $inv = $this->createInvoice([$this->ticket('A', '2201111111111'), $this->ticket('B', '2201111111112')]);
        [$a, $b] = $inv->lines;
        // b refunded while pending, then posted: its refund has no vouchers of its own.
        $this->postJson(route('ticket_invoices.lines.refund', [$inv->id, $b->id]), $this->refundPayload())->assertOk();
        $this->patch(route('ticket_invoices.post', $inv->id));
        $this->postJson(route('ticket_invoices.lines.void', [$inv->id, $a->id]), ['void_date' => '2026-11-20', 'void_deduction_supplier' => 5, 'void_deduction_company' => 5])->assertOk();

        $this->postJson(route('ticket_invoices.lines.revert', [$inv->id, $a->id]))->assertOk();
        $this->postJson(route('ticket_invoices.lines.revert', [$inv->id, $b->id]))->assertOk();
        $this->assertSame(['active', 'active'], $inv->lines()->pluck('status')->all());
        $this->assertSame('posted', $inv->fresh()->status);
        $this->assertEquals(2250, $this->balance($this->customer->chart_of_account_id));
        $this->assertEquals(-2050, $this->balance($this->supplier->chart_of_account_id));
        $this->assertEquals(200, (float) $inv->fresh('lines')->total_income);
        $this->assertBooksBalance();

        $this->get(route('ticket_invoices.show', $inv->id))->assertOk()->assertDontSee('Revert Refund');
    }

    public function test_revert_03_pending_invoice_and_show_page_button(): void
    {
        $inv = $this->createInvoice([$this->ticket('A', '2201111111111')]);
        $line = $inv->lines->first();
        $this->postJson(route('ticket_invoices.lines.void', [$inv->id, $line->id]), ['void_date' => '2026-11-20', 'void_deduction_supplier' => 5, 'void_deduction_company' => 5])->assertOk();
        $this->get(route('ticket_invoices.show', $inv->id))->assertOk()->assertSee('Revert Void')
            ->assertSee('Charges 10.00 deducted from the customer');

        $this->postJson(route('ticket_invoices.lines.revert', [$inv->id, $line->id]))->assertOk();
        $this->assertSame('active', $line->fresh()->status);
        $this->assertEquals(1125, (float) $inv->fresh()->total_amount);
        $this->assertSame(0, Voucher::count());
    }

    // ═══════════════════════ EDIT AFTER ADJUSTMENTS ═══════════════════════

    public function test_edit_01_editing_never_touches_refunded_or_voided_tickets(): void
    {
        $inv = $this->createInvoice([$this->ticket('A', '2201111111111'), $this->ticket('B', '2201111111112')]);
        [$a, $b] = $inv->lines;
        $this->postJson(route('ticket_invoices.lines.refund', [$inv->id, $a->id]), $this->refundPayload())->assertOk();

        $this->putJson(route('ticket_invoices.update', $inv->id), [
            'invoice_date' => '2026-11-20', 'customer_id' => $this->customer->id,
            'lines' => [$this->ticket('B edited', '2201111111112', ['id' => $b->id])],
        ])->assertOk();

        $this->assertSame('refunded', $a->fresh()->status);
        $this->assertSame('A', $a->fresh()->pax_name);
        $this->assertEquals(75 + 1125, (float) $inv->fresh()->total_amount);
    }

    // ═══════════════════════ PRINTS ═══════════════════════

    public function test_print_01_customer_pdf_and_detailed_print_for_every_state(): void
    {
        $inv = $this->createInvoice([$this->ticket('A', '2201111111111'), $this->ticket('B', '2201111111112'), $this->ticket('C', '2201111111113')]);
        [$a, $b] = $inv->lines;

        $check = function () use ($inv) {
            $pdf = $this->get(route('ticket_invoices.print', $inv->id))->assertOk()->assertHeader('Content-Type', 'application/pdf');
            $this->assertStringStartsWith('%PDF', $pdf->getContent());
            $this->get(route('ticket_invoices.print_detailed', $inv->id))->assertOk()->assertSee($inv->invoice_no);
        };

        $check(); // pending
        $this->patch(route('ticket_invoices.post', $inv->id));
        $check(); // posted
        $this->postJson(route('ticket_invoices.lines.refund', [$inv->id, $a->id]), $this->refundPayload())->assertOk();
        $this->postJson(route('ticket_invoices.lines.void', [$inv->id, $b->id]), ['void_deduction_supplier' => 0, 'void_deduction_company' => 0])->assertOk();
        $check(); // with refunded + voided tickets
        $this->get(route('ticket_invoices.print_detailed', $inv->id))->assertSee('Refunded')->assertSee('Voided');
    }

    // ═══════════════════════ REPORTS / OTHER ═══════════════════════

    public function test_report_01_my_commission_shows_the_agents_active_tickets(): void
    {
        $agent = User::factory()->create();
        $inv = $this->createInvoice([
            $this->ticket('A', '2201111111111', ['sales_agent_id' => $agent->id, 'agent_commission_percent' => 50]),
            $this->ticket('B', '2201111111112', ['sales_agent_id' => $agent->id, 'agent_commission_percent' => 50]),
        ]);
        $this->postJson(route('ticket_invoices.lines.refund', [$inv->id, $inv->lines[1]->id]), $this->refundPayload())->assertOk();

        $this->actingAs($agent)->get(route('ticket_invoices.my_commission', ['month' => 11, 'year' => 2026]))
            ->assertOk()->assertSee('7.50'); // 50% of PSF 15, refunded ticket excluded
    }

    public function test_report_02_customer_statement_net_equals_customer_ledger_when_everything_is_posted(): void
    {
        $inv = $this->createInvoice([$this->ticket('A', '2201111111111'), $this->ticket('B', '2201111111112')]);
        $this->patch(route('ticket_invoices.post', $inv->id));
        $this->postJson(route('ticket_invoices.lines.refund', [$inv->id, $inv->lines[0]->id]), $this->refundPayload())->assertOk();
        $this->postJson(route('ticket_invoices.lines.void', [$inv->id, $inv->lines[1]->id]), ['void_deduction_supplier' => 5, 'void_deduction_company' => 5])->assertOk();

        $s = app(CustomerStatementService::class)->build($this->customer->fresh(), '2026-11-01', '2026-11-30');
        $this->assertEquals($this->balance($this->customer->chart_of_account_id), $s['summary']['net']);
    }

    public function test_report_03_travel_sales_report_income_equals_ledger_profit(): void
    {
        $inv = $this->createInvoice([$this->ticket('A', '2201111111111'), $this->ticket('B', '2201111111112')]);
        $this->patch(route('ticket_invoices.post', $inv->id));
        $inv = $inv->fresh('lines');

        $this->assertEquals(2250, $inv->total_receivable);
        $this->assertEquals(2050, $inv->total_payable, 'Net owed to the airline = 2 x 1,025.');
        $this->assertEquals(200, $inv->total_income, 'PSF - discount + commission - WHT = 2 x 100.');
        $income = -collect($this->allBalances())->filter(fn ($b, $n) => in_array($n, ['Ticket Sales', 'PSF Income', 'Airline Commission Income'], true))->sum();
        $cost = $this->balance($this->systemAccountId('ticket_cost_expense_account_name'));
        $this->assertEquals($inv->total_income, round($income - $cost, 2));

        $range = ['from_date' => '2026-11-01', 'to_date' => '2026-11-30'];
        $this->get(route('reports.travel_sales', $range))->assertOk()->assertSee($inv->invoice_no)
            ->assertSee('2,250.00')->assertSee('2,050.00')->assertSee('200.00');
        $this->get(route('reports.travel_sales', $range + ['tab' => 'by_service']))->assertOk()->assertSee('2,250.00');
        $this->get(route('reports.travel_accounts', $range))->assertOk()->assertSee($inv->invoice_no);
    }

    public function test_security_01_actions_require_permission(): void
    {
        $inv = $this->createInvoice([$this->ticket('A', '2201111111111')]);
        $nobody = User::factory()->create();
        $this->actingAs($nobody);

        $this->get(route('ticket_invoices.index'))->assertForbidden();
        $this->postJson(route('ticket_invoices.store'), [])->assertForbidden();
        $this->patch(route('ticket_invoices.post', $inv->id))->assertForbidden();
        $this->postJson(route('ticket_invoices.lines.refund', [$inv->id, $inv->lines[0]->id]), $this->refundPayload())->assertForbidden();
        $this->delete(route('ticket_invoices.destroy', $inv->id))->assertForbidden();
        $this->get(route('ticket_invoices.print', $inv->id))->assertForbidden();
        $this->assertSame('pending', $inv->fresh()->status);
    }
}
