<?php

namespace Tests\Feature\E2E;

use App\Models\ChartOfAccounts;
use App\Models\Customer;
use App\Models\Hotel;
use App\Models\HotelRoom;
use App\Models\InvoicePayment;
use App\Models\Quotation;
use App\Models\ServiceLine;
use App\Models\TravelInvoice;
use App\Models\User;
use App\Models\Voucher;
use App\Services\CustomerStatementService;
use App\Services\PartyLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Tour Invoice — end-to-end: CRUD across every tab, status changes,
 * quotation conversion, receipts/payments and their vouchers, prints,
 * the customer statement, and permissions.
 */
class TourInvoiceE2ETest extends TestCase
{
    use RefreshDatabase;
    use LedgerHelpers;

    protected Hotel $hotel;
    protected int $cashAccountId;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-20 10:00:00');
        $this->bootFixtures();
        $this->hotel = Hotel::factory()->create(['name' => 'Kiswa Tower']);
        $this->cashAccountId = 1; // "Cash in Hand" (seeded, Cash sub-head)
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** A tour invoice touching every tab: 2 tickets, hotel (rooms), transport, visa, other. */
    protected function fullPayload(array $over = []): array
    {
        $double = HotelRoom::factory()->create(['hotel_id' => $this->hotel->id, 'room_type' => 'Double']);
        $tkMaster = [
            'pnr' => 'PNR1', 'ticket_type' => 'international', 'trip_type' => 'return',
            'leg1_from' => 'KHI', 'leg1_to' => 'JED', 'leg2_from' => 'JED', 'leg2_to' => 'KHI',
            'fare_amount' => 1000, 'tax_amount' => 100,
            'apt_percent' => 2, 'apt_input_mode' => 'percent',
            'commission_amount' => 100, 'commission_input_mode' => 'amount',
            'wht_percent' => 5, 'wht_input_mode' => 'percent',
            'psf_amount' => 50, 'psf_input_mode' => 'amount', 'psf_basis' => 'fare',
            'discount_amount' => 0, 'discount_input_mode' => 'amount',
        ];

        return array_replace_recursive([
            'invoice_date' => '2026-11-20',
            'customer_id' => $this->customer->id,
            'passengers' => [
                ['name' => 'Ahmed Ali', 'pax_type' => 'adult', 'passport_no_nic' => 'AR0718012'],
                ['name' => 'Sara Ali', 'pax_type' => 'child', 'passport_no_nic' => 'AY5988032'],
            ],
            'lines' => [
                ['service_type' => 'ticket', 'supplier_id' => $this->supplier->id, 'description' => 'Ahmed Ali', 'detail' => $tkMaster + ['ticket_no' => '2201111111111', 'pax_type' => 'adult']],
                ['service_type' => 'ticket', 'supplier_id' => $this->supplier->id, 'description' => 'Sara Ali', 'detail' => $tkMaster + ['ticket_no' => '2201111111112', 'pax_type' => 'child']],
                ['service_type' => 'hotel', 'supplier_id' => $this->supplier->id, 'currency' => 'PKR', 'receivable_f_amount' => 0, 'payable_f_amount' => 0,
                 'detail' => ['hotel_id' => $this->hotel->id, 'check_in' => '2026-12-01', 'check_out' => '2026-12-05', 'booking_name' => 'Ahmed Ali',
                              'receivable_currency' => 'PKR', 'receivable_exchange_rate' => 1, 'payable_currency' => 'PKR', 'payable_exchange_rate' => 1,
                              'rooms' => [['hotel_room_id' => $double->id, 'qty' => 2, 'rate' => 500, 'cost' => 400]]]],
                ['service_type' => 'transport', 'supplier_id' => $this->supplier->id, 'currency' => 'PKR', 'receivable_f_amount' => 800, 'payable_f_amount' => 600, 'description' => 'Airport pickup',
                 'detail' => ['booking_name' => 'Ahmed Ali', 'reference_no' => 'TR-1', 'receivable_exchange_rate' => 1, 'payable_exchange_rate' => 1]],
                ['service_type' => 'visa', 'supplier_id' => $this->supplier->id, 'currency' => 'SAR', 'exchange_rate' => 75, 'receivable_f_amount' => 40, 'payable_f_amount' => 30,
                 'detail' => ['reference_no' => 'V-1', 'receivable_exchange_rate' => 75, 'payable_exchange_rate' => 75]],
                ['service_type' => 'other', 'supplier_id' => $this->supplier->id, 'currency' => 'PKR', 'exchange_rate' => 1, 'receivable_f_amount' => 300, 'payable_f_amount' => 200, 'description' => 'Ziyarat',
                 'detail' => ['qty' => 1, 'booking_name' => 'Sara Ali', 'travel_date' => '2026-12-02']],
            ],
        ], $over);
    }

    /** A posted Ticket Sale Invoice of 2 tickets from $this->supplier (each: total 1,125, supplier net 1,025). */
    protected function postedTicketInvoice(?int $customerId = null): \App\Models\TicketSaleInvoice
    {
        $t = fn ($pax, $no) => [
            'pax_name' => $pax, 'pax_type' => 'adult', 'pnr' => 'TKT1', 'ticket_no' => $no,
            'trip_type' => 'one_way', 'leg1_from' => 'KHI', 'leg1_to' => 'DXB', 'supplier_id' => $this->supplier->id,
            'fare_amount' => 1000, 'tax_amount' => 100, 'apt_percent' => 2, 'apt_input_mode' => 'percent',
            'commission_percent' => 10, 'commission_input_mode' => 'percent', 'wht_amount' => 5, 'wht_input_mode' => 'amount',
            'psf_percent' => 1.5, 'psf_input_mode' => 'percent', 'psf_basis' => 'fare', 'discount_amount' => 10, 'discount_input_mode' => 'amount',
        ];
        $this->postJson(route('ticket_invoices.store'), [
            'invoice_date' => '2026-11-20', 'customer_id' => $customerId ?? $this->customer->id,
            'lines' => [$t('Kamran', '2209999999991'), $t('Nadia', '2209999999992')],
        ])->assertOk();
        $inv = \App\Models\TicketSaleInvoice::with('lines')->latest('id')->first();
        $this->patch(route('ticket_invoices.post', $inv->id))->assertSessionHas('success');

        return $inv->fresh('lines');
    }

    protected function createFull(): TravelInvoice
    {
        $this->postJson(route('tour_invoices.store'), $this->fullPayload())->assertOk()->assertJsonStructure(['redirect']);

        return TravelInvoice::with('serviceLines.ticketDetail', 'serviceLines.hotelDetail.rooms', 'passengers')->latest('id')->firstOrFail();
    }

    protected function pay(TravelInvoice $inv, string $direction, float $amount, array $over = [])
    {
        return $this->post(route('invoice_payments.store'), array_merge([
            'direction' => $direction,
            'customer_id' => $direction === 'receipt' ? $this->customer->id : null,
            'supplier_id' => $direction === 'payment' ? $this->supplier->id : null,
            'travel_invoice_id' => $inv->id,
            'cash_bank_account_id' => $this->cashAccountId,
            'payment_date' => '2026-11-21', 'amount' => $amount, 'payment_mode' => 'cash',
        ], $over));
    }

    // ═══════════════════════ CRUD ═══════════════════════

    public function test_crud_01_create_with_every_tab_calculates_lines_and_totals(): void
    {
        $this->get(route('tour_invoices.create'))->assertOk();
        $inv = $this->createFull();
        $lines = $inv->serviceLines->groupBy('service_type');

        $this->assertSame('TIN-26-0001', $inv->invoice_no);
        $this->assertSame('draft', $inv->status);
        $this->assertCount(2, $inv->passengers);

        // Ticket tab: 1000 + 100 + 20 + 50 = 1170 to customer; supplier net 1000+100+20−100+5 = 1025
        $this->assertCount(2, $lines['ticket']);
        $this->assertEquals(1170, (float) $lines['ticket'][0]->receivable_l_amount);
        $this->assertEquals(1025, (float) $lines['ticket'][0]->payable_l_amount);
        $this->assertSame('220-1111-111-111', $lines['ticket'][0]->ticketDetail->ticket_no);
        $this->assertSame('KHI-JED/JED-KHI', $lines['ticket'][0]->ticketDetail->sector);

        // Hotel: 4 nights × 2 rooms × 500 = 4000; cost 3200
        $this->assertEquals(4000, (float) $lines['hotel'][0]->receivable_l_amount);
        $this->assertEquals(3200, (float) $lines['hotel'][0]->payable_l_amount);
        $this->assertSame(4, (int) $lines['hotel'][0]->hotelDetail->nights);

        // Visa in SAR at 75 → 3000 / 2250
        $this->assertEquals(3000, (float) $lines['visa'][0]->receivable_l_amount);
        $this->assertEquals(2250, (float) $lines['visa'][0]->payable_l_amount);

        $this->assertEquals(2 * 1170 + 4000 + 800 + 3000 + 300, (float) $inv->total_receivable); // 10440
        $this->assertEquals(2 * 1025 + 3200 + 600 + 2250 + 200, (float) $inv->total_payable);    // 8300
        $this->assertEquals((float) $inv->total_receivable - (float) $inv->total_payable, (float) $inv->total_income);
    }

    public function test_crud_02_index_search_filter_show_and_edit_pages(): void
    {
        $inv = $this->createFull();
        $this->get(route('tour_invoices.index'))->assertOk()->assertSee($inv->invoice_no);
        $this->get(route('tour_invoices.index', ['search' => 'Vacations']))->assertOk()->assertSee($inv->invoice_no);
        $this->get(route('tour_invoices.index', ['search' => 'TIN-26-0001']))->assertOk()->assertSee($inv->invoice_no);
        $this->get(route('tour_invoices.index', ['status' => 'confirmed']))->assertOk()->assertDontSee($inv->invoice_no);
        $this->get(route('tour_invoices.show', $inv->id))->assertOk()
            ->assertSee('Kiswa Tower')->assertSee('220-1111-111-111')->assertSee('Airport pickup')->assertSee('Ziyarat');
        $this->get(route('tour_invoices.edit', $inv->id))->assertOk()->assertSee('tkm-root', false);
    }

    public function test_crud_03_update_replaces_lines_and_passengers(): void
    {
        $inv = $this->createFull();
        $payload = $this->fullPayload();
        $payload['passengers'] = [['name' => 'Only One', 'pax_type' => 'adult']];
        $payload['lines'] = [$payload['lines'][3]]; // keep transport only
        $payload['lines'][0]['receivable_f_amount'] = 900;

        $this->putJson(route('tour_invoices.update', $inv->id), $payload)->assertOk();
        $inv->refresh();
        $this->assertSame(1, $inv->serviceLines()->count());
        $this->assertSame(['Only One'], $inv->passengers()->pluck('name')->all());
        $this->assertEquals(900, (float) $inv->total_receivable);
        $this->assertSame(1, ServiceLine::count(), 'Old lines are removed, not orphaned.');
    }

    public function test_crud_04_validation_errors_come_back_as_json(): void
    {
        $this->postJson(route('tour_invoices.store'), ['lines' => []])
            ->assertStatus(422)->assertJsonValidationErrors(['customer_id', 'invoice_date']);
        $bad = $this->fullPayload();
        $bad['lines'][0]['detail']['ticket_no'] = '22011';
        $this->postJson(route('tour_invoices.store'), $bad)->assertStatus(422)->assertJsonValidationErrors(['lines.0.detail.ticket_no']);
        $bad = $this->fullPayload();
        $bad['lines'][2]['detail']['rooms'][0]['cost'] = -1;
        $this->postJson(route('tour_invoices.store'), $bad)->assertStatus(422)->assertJsonValidationErrors(['lines.2.detail.rooms.0.cost']);
        $this->assertSame(0, TravelInvoice::count());
    }

    public function test_crud_05_delete_draft_and_confirmed_rules(): void
    {
        $draft = $this->createFull();
        $this->delete(route('tour_invoices.destroy', $draft->id))->assertSessionHas('success');
        $this->assertSoftDeleted($draft);
        $this->assertSame(0, ServiceLine::count());

        $conf = $this->createFull();
        $this->patch(route('tour_invoices.status', $conf->id), ['status' => 'confirmed']);
        $this->delete(route('tour_invoices.destroy', $conf->id))->assertSessionHas('error');
        $this->assertNotSoftDeleted($conf);
        $this->assertSame('TIN-26-0002', $conf->invoice_no, 'Deleted numbers are not reused.');
    }

    // ═══════════════════════ STATUS ═══════════════════════

    public function test_status_01_confirm_locks_editing_cancel_keeps_it_out_of_the_statement(): void
    {
        $inv = $this->createFull();
        $this->patch(route('tour_invoices.status', $inv->id), ['status' => 'confirmed'])->assertSessionHas('success');
        $this->assertSame('confirmed', $inv->fresh()->status);
        $this->putJson(route('tour_invoices.update', $inv->id), $this->fullPayload())->assertStatus(422);
        $this->get(route('tour_invoices.edit', $inv->id))->assertRedirect();

        $statement = fn () => app(CustomerStatementService::class)->build($this->customer->fresh(), '2026-11-01', '2026-11-30')['summary']['sales'];
        $this->assertEquals(10440, $statement());

        $this->patch(route('tour_invoices.status', $inv->id), ['status' => 'cancelled'])->assertSessionHas('success');
        $this->assertEquals(0, $statement());
        $this->patch(route('tour_invoices.status', $inv->id), ['status' => 'bogus'])->assertSessionHasErrors('status');
    }

    /** Account balances the reference invoice must leave once confirmed. */
    protected function assertReferencePosting(): void
    {
        $this->assertEquals(10440, $this->balance($this->customer->chart_of_account_id), 'Customer owes the full receivable.');
        $this->assertEquals(-8300, $this->balance($this->supplier->chart_of_account_id), 'Supplier: tickets 2,240 − commission 190 + hotel 3,200 + transport 600 + visa 2,250 + other 200.');
        $this->assertEquals(0, $this->balance($this->airline->chart_of_account_id), 'Ticket cost goes to the line supplier, not the airline.');
        $this->assertEquals(-2240, $this->balance($this->systemAccountId('ticket_sales_income_account_name')));
        $this->assertEquals(-100, $this->balance($this->systemAccountId('psf_income_account_name')));
        $this->assertEquals(-190, $this->balance($this->systemAccountId('airline_commission_income_account_name')));
        $this->assertEquals(2240, $this->balance($this->systemAccountId('ticket_cost_expense_account_name')));
        foreach (['Hotel' => [4000, 3200], 'Transport' => [800, 600], 'Visa' => [3000, 2250], 'Other Services' => [300, 200]] as $name => [$sale, $cost]) {
            $this->assertEquals(-$sale, $this->balance(ChartOfAccounts::where('name', "$name Sales")->value('id')), "$name Sales");
            $this->assertEquals($cost, $this->balance(ChartOfAccounts::where('name', "$name Cost")->value('id')), "$name Cost");
        }

        // Ledger profit (income accounts - cost accounts) = the invoice's Income column.
        $all = collect($this->allBalances());
        $income = -$all->filter(fn ($b, $n) => str_ends_with($n, 'Sales') || str_ends_with($n, 'Income'))->sum();
        $cost = $all->filter(fn ($b, $n) => str_ends_with($n, 'Cost'))->sum();
        $this->assertEquals(2140, round($income - $cost, 2));
        $this->assertEquals(2140, (float) TravelInvoice::latest('id')->value('total_income'));
        $this->assertBooksBalance();
    }

    public function test_status_02_confirm_posts_vouchers_for_every_service(): void
    {
        $inv = $this->createFull();
        $this->assertSame(0, Voucher::count(), 'A draft writes nothing to the ledgers.');

        $this->patch(route('tour_invoices.status', $inv->id), ['status' => 'confirmed'])->assertSessionHas('success');

        // 6 sales (ticket, PSF, hotel, transport, visa, other) + 5 costs: ticket->airline, 4 services->supplier; airline commission.
        $this->assertSame(12, Voucher::count());
        $this->assertSame(12, Voucher::where('reference', $inv->invoice_no)->whereDate('date', '2026-11-20')->count());
        $this->assertReferencePosting();

        $this->get(route('tour_invoices.show', $inv->id))->assertOk()
            ->assertSee('Ledger Vouchers')->assertSee('Hotel Sales')->assertSee('Back to Draft');
    }

    public function test_status_03_back_to_draft_unposts_and_reconfirm_gives_identical_books(): void
    {
        $inv = $this->createFull();
        $this->patch(route('tour_invoices.status', $inv->id), ['status' => 'confirmed']);
        $posted = $this->allBalances();

        $this->patch(route('tour_invoices.status', $inv->id), ['status' => 'draft'])->assertSessionHas('success');
        $this->assertSame('draft', $inv->fresh()->status);
        $this->assertSame(0, Voucher::count(), 'Every voucher the invoice posted is removed.');
        $this->assertSame([], $this->allBalances());
        $this->get(route('tour_invoices.edit', $inv->id))->assertOk();

        $this->patch(route('tour_invoices.status', $inv->id), ['status' => 'confirmed']);
        $this->assertSame($posted, $this->allBalances());
    }

    public function test_status_04_cancel_a_confirmed_invoice_removes_its_vouchers_and_keeps_payments(): void
    {
        $inv = $this->createFull();
        $this->patch(route('tour_invoices.status', $inv->id), ['status' => 'confirmed']);
        $this->pay($inv, 'receipt', 1000)->assertSessionHasNoErrors();

        $this->patch(route('tour_invoices.status', $inv->id), ['status' => 'cancelled'])->assertSessionHas('success');
        $this->assertSame(1, Voucher::count(), 'Only the receipt is left.');
        $this->assertEquals(-1000, $this->balance($this->customer->chart_of_account_id), 'Customer is in credit by what they paid (refund due).');
        $this->assertBooksBalance();
    }

    public function test_status_05_only_allowed_transitions(): void
    {
        $inv = $this->createFull();
        $this->patch(route('tour_invoices.status', $inv->id), ['status' => 'cancelled'])->assertSessionHas('success');
        $this->patch(route('tour_invoices.status', $inv->id), ['status' => 'confirmed'])->assertSessionHas('error');
        $this->assertSame('cancelled', $inv->fresh()->status);
        $this->assertSame(0, Voucher::count());

        $this->patch(route('tour_invoices.status', $inv->id), ['status' => 'draft'])->assertSessionHas('success');
        $this->patch(route('tour_invoices.status', $inv->id), ['status' => 'draft'])->assertSessionHas('error');
        $this->patch(route('tour_invoices.status', $inv->id), ['status' => 'confirmed'])->assertSessionHas('success');
        $this->patch(route('tour_invoices.status', $inv->id), ['status' => 'confirmed'])->assertSessionHas('error');
        $this->assertSame(12, Voucher::count(), 'Confirming twice never double-posts.');
    }

    public function test_status_06_confirm_is_refused_when_a_line_has_no_supplier(): void
    {
        $payload = $this->fullPayload();
        $payload['lines'][3]['supplier_id'] = null; // transport with a payable of 600
        $this->postJson(route('tour_invoices.store'), $payload)->assertOk();
        $inv = TravelInvoice::latest('id')->first();

        $this->patch(route('tour_invoices.status', $inv->id), ['status' => 'confirmed'])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'Transport line') && str_contains($m, 'no supplier'));
        $this->assertSame('draft', $inv->fresh()->status);
        $this->assertSame(0, Voucher::count());
    }

    // ═══════════════════════ QUOTATION → INVOICE ═══════════════════════

    public function test_quotation_01_converting_an_approved_quotation_marks_it_converted(): void
    {
        $q = Quotation::create([
            'quotation_no' => 'QTN-26-0001', 'quotation_date' => '2026-11-15', 'customer_id' => $this->customer->id,
            'status' => 'approved', 'created_by' => $this->admin->id,
        ]);
        $this->get(route('tour_invoices.create', ['quotation_id' => $q->id]))->assertOk();
        $this->postJson(route('tour_invoices.store'), $this->fullPayload(['quotation_id' => $q->id]))->assertOk();

        $q->refresh();
        $this->assertSame('converted', $q->status);
        $this->assertSame(TravelInvoice::latest('id')->value('id'), (int) $q->converted_invoice_id);
    }

    // ═══════════════════════ PAYMENTS & LEDGER ═══════════════════════

    public function test_payment_01_receipt_and_supplier_payment_hit_cash_customer_and_supplier_ledgers(): void
    {
        $inv = $this->createFull();
        $this->patch(route('tour_invoices.status', $inv->id), ['status' => 'confirmed']);

        $this->pay($inv, 'receipt', 6000)->assertSessionHasNoErrors();
        $this->pay($inv, 'payment', 5000)->assertSessionHasNoErrors();

        $this->assertEquals(6000 - 5000, $this->balance($this->cashAccountId));
        $this->assertEquals(10440 - 6000, $this->balance($this->customer->chart_of_account_id)); // invoice Dr, receipt Cr
        $this->assertEquals(-8300 + 5000, $this->balance($this->supplier->chart_of_account_id)); // cost Cr, payment Dr
        $this->assertBooksBalance();

        $inv->refresh();
        $this->assertEquals(6000, $inv->receivedAmount());
        $this->assertEquals(10440 - 6000, $inv->outstandingAmount());
        $this->get(route('tour_invoices.show', $inv->id))->assertOk()->assertSee('6,000.00');

        $receipt = InvoicePayment::where('direction', 'receipt')->first();
        $this->assertSame('receipt', $receipt->voucher->voucher_type);
        $this->get(route('invoice_payments.index'))->assertOk();
        $this->get(route('invoice_payments.show', $receipt->id))->assertOk();
        $this->get(route('invoice_payments.print', $receipt->id))->assertOk()
            ->assertSee('PAYMENT RECEIPT')->assertSee('RV' . $receipt->voucher_id)->assertSee('6,000.00')
            ->assertSee('Rupees Six Thousand Only')->assertSee($inv->invoice_no)->assertSee('4,440.00')
            ->assertSee(config('travel.company_name'))->assertSee('Customer Signature');
        $this->assertStringContainsString($inv->invoice_no, $receipt->voucher->description);
        $supplierPayment = InvoicePayment::where('direction', 'payment')->first();
        $this->get(route('invoice_payments.print', $supplierPayment->id))->assertOk()
            ->assertSee('PAYMENT VOUCHER')->assertSee('Rupees Five Thousand Only')->assertSee($this->supplier->name);
    }

    public function test_payment_02_payments_cannot_be_edited_and_delete_reverses_the_voucher(): void
    {
        $inv = $this->createFull();
        $this->pay($inv, 'receipt', 1000);
        $receipt = InvoicePayment::first();

        $this->get(route('invoice_payments.edit', $receipt->id))->assertRedirect(route('invoice_payments.show', $receipt->id));
        $this->put(route('invoice_payments.update', $receipt->id), ['amount' => 1])->assertRedirect();
        $this->assertEquals(1000, (float) $receipt->fresh()->amount);

        $this->delete(route('invoice_payments.destroy', $receipt->id))->assertSessionHas('success');
        $this->assertSame(0, Voucher::count());
        $this->assertEquals(0, $this->balance($this->customer->chart_of_account_id));
        $this->assertEquals(0, $inv->fresh()->receivedAmount());
    }

    public function test_payment_03_receipt_must_belong_to_the_invoices_customer(): void
    {
        $inv = $this->createFull();
        $other = Customer::factory()->create();
        $other->update(['chart_of_account_id' => app(PartyLedgerService::class)->syncCustomer($other)->id]);

        $this->pay($inv, 'receipt', 500, ['customer_id' => $other->id])->assertSessionHasErrors('travel_invoice_id');
        $this->assertSame(0, InvoicePayment::count());
    }

    public function test_payment_04_cash_bank_account_must_be_a_cash_or_bank_account(): void
    {
        $inv = $this->createFull();
        $this->pay($inv, 'receipt', 500, ['cash_bank_account_id' => $this->customer->chart_of_account_id])
            ->assertSessionHasErrors('cash_bank_account_id');
        $this->assertSame(0, Voucher::count());
    }

    public function test_payment_06_no_payments_on_a_cancelled_invoice_and_no_delete_while_payments_exist(): void
    {
        $inv = $this->createFull();
        $this->pay($inv, 'receipt', 500)->assertSessionHasNoErrors();
        $this->delete(route('tour_invoices.destroy', $inv->id))->assertSessionHas('error');
        $this->assertNotSoftDeleted($inv);

        $this->patch(route('tour_invoices.status', $inv->id), ['status' => 'cancelled']);
        $this->pay($inv, 'receipt', 500)->assertSessionHasErrors('travel_invoice_id');
        $this->pay($inv, 'payment', 500)->assertSessionHasErrors('travel_invoice_id');
        $this->assertSame(1, InvoicePayment::count());
        $this->get(route('tour_invoices.show', $inv->id))->assertOk()->assertDontSee('+ Record Receipt');
    }

    public function test_payment_07_receipt_against_a_ticket_sale_invoice(): void
    {
        $inv = $this->postedTicketInvoice();
        $tour = $this->createFull();

        // The invoice dropdown data carries both invoice types for the customer.
        $this->get(route('invoice_payments.create', ['direction' => 'receipt', 'customer_id' => $this->customer->id, 'ticket_sale_invoice_id' => $inv->id]))
            ->assertOk()->assertSee($inv->invoice_no)->assertSee($tour->invoice_no)->assertSee('"ref":"ticket:' . $inv->id . '"', false)
            ->assertSee('"ticket:' . $inv->id . '"', false);

        $this->post(route('invoice_payments.store'), [
            'direction' => 'receipt', 'customer_id' => $this->customer->id, 'invoice_ref' => 'ticket:' . $inv->id,
            'cash_bank_account_id' => $this->cashAccountId, 'payment_date' => '2026-11-21', 'amount' => 1000, 'payment_mode' => 'cash',
        ])->assertSessionHasNoErrors();
        $p = InvoicePayment::latest('id')->first();
        $this->assertSame($inv->id, $p->ticket_sale_invoice_id);
        $this->assertNull($p->travel_invoice_id);
        $this->assertEquals(1250, $inv->fresh()->outstandingAmount());
        $this->assertStringContainsString($inv->invoice_no, $p->voucher->description);
        $this->get(route('invoice_payments.show', $p->id))->assertOk()->assertSee($inv->invoice_no)->assertSee('1,250.00');
        $this->get(route('invoice_payments.print', $p->id))->assertOk()->assertSee($inv->invoice_no)->assertSee('1,250.00');
        $this->get(route('ticket_invoices.show', $inv->id))->assertOk()->assertSee('Record Receipt');

        // Another customer's ticket invoice is refused.
        $other = \App\Models\Customer::factory()->create();
        $this->post(route('invoice_payments.store'), [
            'direction' => 'receipt', 'customer_id' => $other->id, 'invoice_ref' => 'ticket:' . $inv->id,
            'cash_bank_account_id' => $this->cashAccountId, 'payment_date' => '2026-11-21', 'amount' => 10, 'payment_mode' => 'cash',
        ])->assertSessionHasErrors('invoice_ref');
        $this->post(route('invoice_payments.store'), [
            'direction' => 'receipt', 'customer_id' => $this->customer->id, 'invoice_ref' => 'bogus',
            'cash_bank_account_id' => $this->cashAccountId, 'payment_date' => '2026-11-21', 'amount' => 10, 'payment_mode' => 'cash',
        ])->assertSessionHasErrors('invoice_ref');
    }

    public function test_payment_05_validation(): void
    {
        $inv = $this->createFull();
        $this->pay($inv, 'receipt', 0)->assertSessionHasErrors('amount');
        $this->pay($inv, 'receipt', 100, ['customer_id' => null])->assertSessionHasErrors('customer_id');
        $this->pay($inv, 'payment', 100, ['supplier_id' => null])->assertSessionHasErrors('supplier_id');
        $this->pay($inv, 'receipt', 100, ['payment_mode' => 'barter'])->assertSessionHasErrors('payment_mode');
        $this->assertSame(0, InvoicePayment::count());
    }

    // ═══════════════════════ PRINTS & REPORTS ═══════════════════════

    public function test_print_01_customer_copy_hides_cost_and_margin_internal_copy_shows_them(): void
    {
        $inv = $this->createFull();
        foreach (['draft' => 'DRAFT', 'confirmed' => null, 'cancelled' => 'CANCELLED'] as $status => $stamp) {
            if ($status !== 'draft') {
                $this->patch(route('tour_invoices.status', $inv->id), ['status' => $status])->assertSessionHas('success');
            }
            $customer = $this->get(route('tour_invoices.print', $inv->id))->assertOk()
                ->assertSee($inv->invoice_no)->assertSee('Ahmed Ali')->assertSee('Kiswa Tower')->assertSee('2 × Double', false)
                ->assertSee('Airport pickup')->assertSee('Ziyarat')->assertSee('220-1111-111-111')
                ->assertSee('10,440.00')->assertSee('Rupees Ten Thousand Four Hundred Forty Only')
                ->assertSee(config('travel.company_name'))
                ->assertDontSee($this->supplier->name)->assertDontSee('Payable')->assertDontSee('Income')
                ->assertDontSee('8,300.00')->assertDontSee('2,140.00');
            if ($stamp) {
                $customer->assertSee($stamp);
            }

            $this->get(route('tour_invoices.print', ['id' => $inv->id, 'copy' => 'internal']))->assertOk()
                ->assertSee('Internal Copy')->assertSee($this->supplier->name)->assertSee('Payable')
                ->assertSee('8,300.00')->assertSee('2,140.00');
        }
    }

    public function test_report_01_customer_statement_lists_every_service_and_the_payments(): void
    {
        $inv = $this->createFull();
        $this->pay($inv, 'receipt', 6000);

        $s = app(CustomerStatementService::class)->build($this->customer->fresh(), '2026-11-01', '2026-11-30');
        $this->assertEquals(2340, $s['sections']['ticket']->sum('net'));
        $this->assertEquals(4000, $s['sections']['hotel']->sum('net'));
        $this->assertEquals(800, $s['sections']['transport']->sum('net'));
        $this->assertEquals(3000, $s['sections']['visa']->sum('net'));
        $this->assertEquals(300, $s['sections']['other']->sum('net'));
        $this->assertEquals(6000, $s['summary']['receipts']);
        $this->assertEquals(10440 - 6000, $s['summary']['net']);
        $this->assertEquals($inv->fresh()->outstandingAmount(), $s['summary']['net']);

        // Once confirmed, the statement still shows the invoice once (its posted vouchers are not repeated)
        // and its net equals the customer's ledger balance.
        $this->patch(route('tour_invoices.status', $inv->id), ['status' => 'confirmed']);
        $s = app(CustomerStatementService::class)->build($this->customer->fresh(), '2026-11-01', '2026-11-30');
        $this->assertCount(1, $s['vouchers']);
        $this->assertEquals(10440 - 6000, $s['summary']['net']);
        $this->assertEquals($this->balance($this->customer->chart_of_account_id), $s['summary']['net']);
    }

    public function test_report_03_vendor_statement_matches_the_supplier_ledger(): void
    {
        $tour = $this->createFull();
        $this->patch(route('tour_invoices.status', $tour->id), ['status' => 'confirmed']);
        $tickets = $this->postedTicketInvoice();
        $refunded = $tickets->lines->first();
        $this->postJson(route('ticket_invoices.lines.refund', [$tickets->id, $refunded->id]), [
            'refund_date' => '2026-11-22', 'refund_fare_amount' => 1000, 'refund_tax_amount' => 100,
            'refund_deduction_supplier' => 30, 'refund_deduction_company' => 20,
        ])->assertOk();
        $this->pay($tour, 'payment', 5000);

        $s = app(\App\Services\VendorStatementService::class)->build($this->supplier->fresh(), '2026-11-01', '2026-11-30');
        $this->assertCount(4, $s['tickets'], '2 tour tickets + 2 Ticket Sale tickets.');
        $this->assertEquals(4100, $s['tickets']->sum('net'));
        $this->assertEquals(3200, $s['services']['hotel']->sum('net'));
        $this->assertEquals(600, $s['services']['transport']->sum('net'));
        $this->assertEquals(2250, $s['services']['visa']->sum('net'));
        $this->assertEquals(200, $s['services']['other']->sum('net'));
        $this->assertCount(1, $s['credits']);
        $this->assertEquals(['original' => 1025.0, 'kept' => 75.0, 'net' => 950.0], array_intersect_key($s['credits'][0], array_flip(['original', 'kept', 'net'])));
        $this->assertEquals(5000, $s['summary']['payments']);
        $this->assertEquals(10350, $s['summary']['purchases']);
        $this->assertEquals(4400, $s['summary']['net']);
        $this->assertEquals(-$this->balance($this->supplier->chart_of_account_id), $s['summary']['net'], 'Net payable = supplier ledger balance.');

        // Next month opens with what is still owed.
        $next = app(\App\Services\VendorStatementService::class)->build($this->supplier->fresh(), '2026-12-01', '2026-12-31');
        $this->assertEquals(4400, $next['summary']['opening']);

        $this->get(route('reports.accounts', ['tab' => 'vendor_statement', 'supplier_id' => $this->supplier->id, 'from_date' => '2026-11-01', 'to_date' => '2026-11-30']))
            ->assertOk()->assertSee('Vendor Account Statement')->assertSee('Refund / Void Credits')->assertSee('Net Payable')
            ->assertSee('Kiswa Tower')->assertSee('Kamran')
            ->assertSee('color:#c0392b;font-weight:bold;">-4,400.00', false); // we owe the supplier → red, negative
        $pdf = $this->get(route('reports.vendor_statement_pdf', ['supplier_id' => $this->supplier->id, 'from_date' => '2026-11-01', 'to_date' => '2026-11-30']));
        $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        // Paying more than owed → the supplier owes us → green, positive.
        $this->pay($tour, 'payment', 10000);
        $this->get(route('reports.accounts', ['tab' => 'vendor_statement', 'supplier_id' => $this->supplier->id, 'from_date' => '2026-11-01', 'to_date' => '2026-11-30']))
            ->assertSee('color:#1e8449;font-weight:bold;">5,600.00', false);
    }

    public function test_report_04_customer_statement_hides_psf_and_shows_negative_balance_in_red(): void
    {
        $tickets = $this->postedTicketInvoice();
        $url = route('reports.accounts', ['tab' => 'customer_statement', 'customer_id' => $this->customer->id, 'from_date' => '2026-11-01', 'to_date' => '2026-11-30']);
        $s = app(\App\Services\CustomerStatementService::class)->build($this->customer->fresh(), '2026-11-01', '2026-11-30');
        $row = $s['sections']['ticket']->first();
        $this->assertArrayNotHasKey('sp', $row);
        $this->assertEquals(1015, $row['fare'], 'PSF 15 is folded into the fare; fare + taxes − KB still = net.');
        $this->assertEquals($row['net'], $row['fare'] + $row['taxes'] - $row['kb']);
        $this->get($url)->assertOk()->assertDontSee('<th class="text-end">SP</th>', false)
            ->assertSee('color:#1e8449;font-weight:bold;">2,250.00', false); // customer owes us → green

        // Customer pays more than owed → balance below zero, printed with a minus in red.
        $this->post(route('invoice_payments.store'), [
            'direction' => 'receipt', 'customer_id' => $this->customer->id, 'invoice_ref' => 'ticket:' . $tickets->id,
            'cash_bank_account_id' => $this->cashAccountId, 'payment_date' => '2026-11-21', 'amount' => 3000, 'payment_mode' => 'cash',
        ])->assertSessionHasNoErrors();
        $this->get($url)->assertSee('color:#c0392b;font-weight:bold;">-750.00', false); // we owe the customer → red, negative
        $pdf = $this->get(route('reports.customer_statement_pdf', ['customer_id' => $this->customer->id, 'from_date' => '2026-11-01', 'to_date' => '2026-11-30']));
        $pdf->assertOk();
    }

    public function test_report_05_colour_rule_receive_green_pay_red_everywhere(): void
    {
        $tour = $this->createFull();
        $this->patch(route('tour_invoices.status', $tour->id), ['status' => 'confirmed']);
        $tickets = $this->postedTicketInvoice();
        $G = 'color:#1e8449;font-weight:bold;">'; $R = 'color:#c0392b;font-weight:bold;">';
        $range = ['from_date' => '2026-11-01', 'to_date' => '2026-11-30'];

        $this->get(route('tour_invoices.show', $tour->id))->assertSee($G . '10,440.00', false)->assertSee($R . '-8,300.00', false);
        $this->get(route('tour_invoices.print', ['id' => $tour->id, 'copy' => 'internal']))->assertSee($G . '10,440.00', false)->assertSee($R . '-8,300.00', false);
        $this->get(route('tour_invoices.print', $tour->id))->assertSee($G . '10,440.00', false)->assertDontSee('8,300.00');
        $this->get(route('ticket_invoices.show', $tickets->id))->assertSee($G . '2,250.00', false)->assertSee($R . '-2,050.00', false);
        $this->get(route('reports.travel_accounts', $range))->assertSee($G . '10,440.00', false);
        $this->get(route('reports.travel_accounts', $range + ['tab' => 'payables']))->assertSee($R . '-10,350.00', false);
        $this->get(route('reports.travel_sales', $range))->assertSee($G . '10,440.00', false)->assertSee($R . '-8,300.00', false);
        $this->get(route('reports.travel_vendor', $range))->assertSee($R . '-10,350.00', false);
        $this->get(route('reports.accounts', ['tab' => 'receivables'] + $range))->assertSee($G . '12,690.00', false);
        $this->get(route('reports.accounts', ['tab' => 'payables'] + $range))->assertSee($R . '-10,350.00', false);
        $this->get(route('reports.accounts', ['tab' => 'party_ledger', 'account_id' => $this->supplier->chart_of_account_id] + $range))->assertSee($R . '-10,350.00', false);
        $this->get(route('dashboard'))->assertSee($G . '12,690', false)->assertSee($R . '-10,350', false);
    }

    public function test_report_02_travel_vendor_sales_and_accounting_pages(): void
    {
        $inv = $this->createFull();
        $this->patch(route('tour_invoices.status', $inv->id), ['status' => 'confirmed']);
        $this->pay($inv, 'receipt', 6000);
        $this->pay($inv, 'payment', 5000);
        $range = ['from_date' => '2026-11-01', 'to_date' => '2026-11-30'];

        $this->get(route('reports.travel_vendor', $range))->assertOk()->assertSee($this->supplier->name)
            ->assertSee('8,300.00')->assertSee('5,000.00')->assertSee('3,300.00');
        $this->get(route('reports.travel_vendor', $range + ['tab' => 'complaints']))->assertOk();
        $this->get(route('reports.travel_vendor', $range + ['tab' => 'spend']))->assertOk()->assertSee('3,200.00');

        $this->get(route('reports.travel_sales', $range))->assertOk()->assertSee($inv->invoice_no)
            ->assertSee('10,440.00')->assertSee('8,300.00')->assertSee('2,140.00')->assertSee('4,440.00');
        $this->get(route('reports.travel_sales', $range + ['tab' => 'by_service']))->assertOk()
            ->assertSee('4,000.00')->assertSee('2,340.00');
        $this->get(route('reports.travel_sales', $range + ['tab' => 'by_customer']))->assertOk()->assertSee('Vacations Gala');

        $this->get(route('reports.travel_accounts', $range))->assertOk()->assertSee('4,440.00');
        $this->get(route('reports.travel_accounts', $range + ['tab' => 'payables']))->assertOk()->assertSee('3,300.00');
        $this->get(route('reports.travel_accounts', $range + ['tab' => 'payments_register']))->assertOk()
            ->assertSee('6,000.00')->assertSee('5,000.00');
    }

    public function test_security_01_actions_require_permission(): void
    {
        $inv = $this->createFull();
        $this->actingAs(User::factory()->create());

        $this->get(route('tour_invoices.index'))->assertForbidden();
        $this->postJson(route('tour_invoices.store'), $this->fullPayload())->assertForbidden();
        $this->patch(route('tour_invoices.status', $inv->id), ['status' => 'confirmed'])->assertForbidden();
        $this->delete(route('tour_invoices.destroy', $inv->id))->assertForbidden();
        $this->get(route('tour_invoices.print', $inv->id))->assertForbidden();
        $this->post(route('invoice_payments.store'), [])->assertForbidden();
        $this->assertSame('draft', $inv->fresh()->status);
    }
}
