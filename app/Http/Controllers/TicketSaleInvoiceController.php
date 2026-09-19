<?php

namespace App\Http\Controllers;

use App\Models\Airline;
use App\Models\Customer;
use App\Models\Quotation;
use App\Models\Supplier;
use App\Models\TicketSaleInvoice;
use App\Models\TicketSaleInvoiceLine;
use App\Models\User;
use App\Services\TicketInvoicePostingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Ticket Sale Invoice — rebuilt from scratch per client feedback. This is
 * NOT a subclass of BaseTravelInvoiceController: the field set, workflow
 * (pending/posted, refund, void) and ledger postings are different enough
 * from Tour Invoice that sharing the old base class would have meant
 * fighting its assumptions (currency/FX, tabs, service_lines) at every
 * step. Tour Invoice keeps using the old shared code unchanged.
 */
class TicketSaleInvoiceController extends Controller
{
    public function __construct(protected TicketInvoicePostingService $poster)
    {
    }

    public function index(Request $request)
    {
        $query = TicketSaleInvoice::with('customer')->withCount('lines')->latest('invoice_date');

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }
        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }
        if ($request->filled('search')) {
            $term = $request->search;
            $digits = preg_replace('/\D/', '', $term);

            $query->where(function ($q) use ($term, $digits) {
                $q->where('invoice_no', 'like', "%{$term}%")
                  ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$term}%"))
                  ->orWhereHas('lines', function ($l) use ($term, $digits) {
                      $l->where('ticket_no', 'like', "%{$term}%")
                        ->orWhere('pnr', 'like', "%{$term}%");
                      if ($digits !== '') {
                          $l->orWhereRaw("REPLACE(ticket_no, '-', '') LIKE ?", ["%{$digits}%"]);
                      }
                  });
            });
        }

        return view('ticket-invoices.index', [
            'invoices' => $query->paginate(25)->withQueryString(),
            'customers' => Customer::orderBy('name')->get(),
            'statuses' => TicketSaleInvoice::STATUSES,
        ]);
    }

    public function create(Request $request)
    {
        $quotation = null;
        if ($request->filled('quotation_id')) {
            $quotation = Quotation::with('customer', 'serviceLines.supplier')
                ->where('status', 'approved')
                ->find($request->quotation_id);
        }

        return view('ticket-invoices.create', array_merge($this->referenceData(), ['quotation' => $quotation]));
    }

    public function store(Request $request)
    {
        $validated = $this->validatedHeader($request);
        $this->validateLinesShape($request);

        try {
            $preparedLines = $this->prepareAndValidateLines($request->input('lines', []));
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        DB::beginTransaction();
        try {
            $invoice = TicketSaleInvoice::create([
                'invoice_no' => $this->nextNumber(),
                'invoice_date' => $validated['invoice_date'],
                'adjustment_date' => $validated['adjustment_date'] ?? $validated['invoice_date'],
                'customer_id' => $validated['customer_id'],
                'status' => TicketSaleInvoice::STATUS_PENDING,
                'remarks' => $validated['remarks'] ?? null,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            $this->syncLines($invoice, $preparedLines);
            $this->markQuotationConverted($validated['quotation_id'] ?? null, $invoice);
            $invoice->recalculateTotals();

            DB::commit();

            return redirect()->route('ticket_invoices.show', $invoice->id)
                ->with('success', 'Ticket sale invoice created successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('[TicketSaleInvoice] store failed: ' . $e->getMessage());

            return back()->withInput()->with('error', 'Could not create invoice: ' . $e->getMessage());
        }
    }

    public function show(string $id)
    {
        $invoice = TicketSaleInvoice::with([
            'customer', 'creator', 'poster',
            'lines' => fn ($q) => $q->orderBy('sort_order'),
            'lines.supplier', 'lines.airline', 'lines.salesAgent',
        ])->findOrFail($id);

        return view('ticket-invoices.show', ['invoice' => $invoice]);
    }

    public function edit(string $id)
    {
        $invoice = TicketSaleInvoice::with(['lines.supplier', 'lines.airline', 'lines.salesAgent'])->findOrFail($id);

        if (!$invoice->isEditable()) {
            return redirect()->route('ticket_invoices.show', $id)->with('error', 'Posted invoices cannot be edited — unpost it first.');
        }

        return view('ticket-invoices.edit', array_merge($this->referenceData(), ['invoice' => $invoice]));
    }

    public function update(Request $request, string $id)
    {
        $invoice = TicketSaleInvoice::findOrFail($id);

        if (!$invoice->isEditable()) {
            return redirect()->route('ticket_invoices.show', $id)->with('error', 'Posted invoices cannot be edited — unpost it first.');
        }

        $validated = $this->validatedHeader($request);
        $this->validateLinesShape($request);

        try {
            $preparedLines = $this->prepareAndValidateLines($request->input('lines', []), $invoice->id);
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        DB::beginTransaction();
        try {
            $invoice->update([
                'invoice_date' => $validated['invoice_date'],
                'adjustment_date' => $validated['adjustment_date'] ?? $validated['invoice_date'],
                'customer_id' => $validated['customer_id'],
                'remarks' => $validated['remarks'] ?? null,
                'updated_by' => auth()->id(),
            ]);

            $this->syncLines($invoice, $preparedLines);
            $invoice->recalculateTotals();

            DB::commit();

            return redirect()->route('ticket_invoices.show', $invoice->id)
                ->with('success', 'Ticket sale invoice updated successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('[TicketSaleInvoice] update failed: ' . $e->getMessage());

            return back()->withInput()->with('error', 'Could not update invoice: ' . $e->getMessage());
        }
    }

    public function destroy(string $id)
    {
        $invoice = TicketSaleInvoice::findOrFail($id);

        if ($invoice->isPosted()) {
            return redirect()->route('ticket_invoices.index')->with('error', 'A posted invoice cannot be deleted — unpost it first.');
        }

        $invoice->lines()->delete();
        $invoice->delete();

        return redirect()->route('ticket_invoices.index')->with('success', 'Invoice deleted successfully.');
    }

    /** Customer-facing print — fare/tax/total only, none of the internal cost/commission detail. */
    public function printCustomer(string $id)
    {
        $invoice = TicketSaleInvoice::with(['customer', 'lines' => fn ($q) => $q->orderBy('sort_order')])->findOrFail($id);

        return view('ticket-invoices.print_customer', ['invoice' => $invoice]);
    }

    /** Internal print — every field, for the agency's own staff/managers. */
    public function printDetailed(string $id)
    {
        $invoice = TicketSaleInvoice::with([
            'customer', 'creator', 'poster',
            'lines' => fn ($q) => $q->orderBy('sort_order'),
            'lines.supplier', 'lines.airline', 'lines.salesAgent',
        ])->findOrFail($id);

        return view('ticket-invoices.print_detailed', ['invoice' => $invoice]);
    }

    public function post(string $id)
    {
        $invoice = TicketSaleInvoice::with('lines')->findOrFail($id);

        try {
            $this->poster->post($invoice);

            return redirect()->route('ticket_invoices.show', $id)->with('success', 'Invoice posted — customer and airline ledgers updated.');
        } catch (\Throwable $e) {
            Log::error('[TicketSaleInvoice] post failed: ' . $e->getMessage());

            return back()->with('error', $e->getMessage());
        }
    }

    public function unpost(string $id)
    {
        $invoice = TicketSaleInvoice::findOrFail($id);

        try {
            $this->poster->unpost($invoice);

            return redirect()->route('ticket_invoices.show', $id)->with('success', 'Invoice unposted and reverted to pending.');
        } catch (\Throwable $e) {
            Log::error('[TicketSaleInvoice] unpost failed: ' . $e->getMessage());

            return back()->with('error', $e->getMessage());
        }
    }

    public function refundLine(Request $request, string $invoiceId, string $lineId)
    {
        $invoice = TicketSaleInvoice::findOrFail($invoiceId);
        $line = $invoice->lines()->findOrFail($lineId);

        if ($invoice->isPosted()) {
            return back()->with('error', 'Unpost this invoice before refunding a ticket.');
        }
        if (!$line->isActive()) {
            return back()->with('error', 'Only an active ticket can be refunded.');
        }

        $validated = $request->validate([
            'refund_date' => 'required|date',
            'refund_adjustment_date' => 'nullable|date',
            'refund_fare_amount' => 'required|numeric|min:0',
            'refund_tax_amount' => 'required|numeric|min:0',
            'refund_charges' => 'required|numeric|min:0',
        ]);

        $refundAmount = round($validated['refund_fare_amount'] + $validated['refund_tax_amount'] - $validated['refund_charges'], 2);
        $refundProfit = round(((float) $line->total_amount) - $refundAmount, 2);

        DB::beginTransaction();
        try {
            $line->update([
                'status' => TicketSaleInvoiceLine::STATUS_REFUNDED,
                'refund_date' => $validated['refund_date'],
                'refund_adjustment_date' => $validated['refund_adjustment_date'] ?? $validated['refund_date'],
                'refund_fare_amount' => $validated['refund_fare_amount'],
                'refund_tax_amount' => $validated['refund_tax_amount'],
                'refund_charges' => $validated['refund_charges'],
                'refund_amount' => $refundAmount,
                'refund_profit' => $refundProfit,
                'updated_by' => auth()->id(),
            ]);

            $invoice->recalculateTotals();

            DB::commit();

            return redirect()->route('ticket_invoices.show', $invoice->id)->with('success', "Ticket {$line->ticket_no} refunded.");
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('[TicketSaleInvoice] refund failed: ' . $e->getMessage());

            return back()->with('error', 'Could not refund ticket: ' . $e->getMessage());
        }
    }

    public function voidLine(Request $request, string $invoiceId, string $lineId)
    {
        $invoice = TicketSaleInvoice::findOrFail($invoiceId);
        $line = $invoice->lines()->findOrFail($lineId);

        if ($invoice->isPosted()) {
            return back()->with('error', 'Unpost this invoice before voiding a ticket.');
        }
        if (!$line->isActive()) {
            return back()->with('error', 'Only an active ticket can be voided.');
        }
        if (!now()->isSameDay($invoice->invoice_date)) {
            return back()->with('error', 'Tickets can only be voided on the same day they were issued (invoice date: ' . $invoice->invoice_date->format('d/m/Y') . '). Use Refund instead.');
        }

        $validated = $request->validate([
            'void_deduction_supplier' => 'required|numeric|min:0',
            'void_deduction_company' => 'required|numeric|min:0',
        ]);

        $totalDeduction = round($validated['void_deduction_supplier'] + $validated['void_deduction_company'], 2);

        DB::beginTransaction();
        try {
            $line->update([
                'status' => TicketSaleInvoiceLine::STATUS_VOIDED,
                'void_date' => now()->toDateString(),
                'void_deduction_supplier' => $validated['void_deduction_supplier'],
                'void_deduction_company' => $validated['void_deduction_company'],
                'void_total_deduction' => $totalDeduction,
                'updated_by' => auth()->id(),
            ]);

            $invoice->recalculateTotals();

            DB::commit();

            return redirect()->route('ticket_invoices.show', $invoice->id)->with('success', "Ticket {$line->ticket_no} voided.");
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('[TicketSaleInvoice] void failed: ' . $e->getMessage());

            return back()->with('error', 'Could not void ticket: ' . $e->getMessage());
        }
    }

    /** "Any agent when login can see his commission monthly" — self-scoped, no permission gate needed. */
    public function myCommission(Request $request)
    {
        $month = (int) ($request->integer('month') ?: now()->month);
        $year = (int) ($request->integer('year') ?: now()->year);

        $lines = TicketSaleInvoiceLine::with('invoice.customer')
            ->where('sales_agent_id', auth()->id())
            ->where('status', TicketSaleInvoiceLine::STATUS_ACTIVE)
            ->whereHas('invoice', function ($q) use ($month, $year) {
                $q->whereYear('invoice_date', $year)->whereMonth('invoice_date', $month);
            })
            ->get();

        return view('ticket-invoices.my_commission', [
            'lines' => $lines,
            'month' => $month,
            'year' => $year,
            'total' => $lines->sum('agent_commission_amount'),
        ]);
    }

    protected function markQuotationConverted(?int $quotationId, TicketSaleInvoice $invoice): void
    {
        if (!$quotationId) {
            return;
        }

        $quotation = Quotation::find($quotationId);
        if ($quotation && $quotation->status === 'approved') {
            $quotation->update([
                'status' => 'converted',
                'converted_invoice_type' => 'sale',
                'converted_invoice_id' => $invoice->id,
            ]);
        }
    }

    /**
     * Create/update/delete lines from the submitted array, matching by an
     * optional 'id' on each row. Only ever touches lines currently
     * 'active' — a refunded or voided ticket is never re-created,
     * re-numbered or silently deleted by an invoice edit.
     */
    protected function syncLines(TicketSaleInvoice $invoice, array $lines): void
    {
        $existingActiveIds = $invoice->lines()->where('status', TicketSaleInvoiceLine::STATUS_ACTIVE)->pluck('id')->all();
        $keepIds = [];

        foreach ($lines as $i => $l) {
            $airlineId = $l['airline_id'] ?? null;
            if (!$airlineId && !empty($l['ticket_no'])) {
                $code = TicketSaleInvoiceLine::airlineCodeFromTicketNo($l['ticket_no']);
                $airlineId = optional(Airline::where('numeric_code', $code)->first())->id;
            }

            $attrs = [
                'ticket_sale_invoice_id' => $invoice->id,
                'supplier_id' => $l['supplier_id'] ?? null,
                'airline_id' => $airlineId,
                'pax_name' => $l['pax_name'],
                'pax_type' => $l['pax_type'] ?? 'adult',
                'pnr' => $l['pnr'] ?? null,
                'ticket_no' => $l['ticket_no'] ?? null,
                'trip_type' => $l['trip_type'] ?? 'one_way',
                'leg1_from' => $l['leg1_from'] ?? null,
                'leg1_stay' => $l['leg1_stay'] ?? null,
                'leg1_to' => $l['leg1_to'] ?? null,
                'leg2_from' => $l['leg2_from'] ?? null,
                'leg2_stay' => $l['leg2_stay'] ?? null,
                'leg2_to' => $l['leg2_to'] ?? null,
                'fare_amount' => $l['fare_amount'] ?? 0,
                'tax_amount' => $l['tax_amount'] ?? 0,
                'apt_charges' => $l['apt_charges'] ?? 0,
                'commission_percent' => $l['commission_percent'] ?? 0,
                'wht_amount' => $l['wht_amount'] ?? 0,
                'psf_amount' => $l['psf_amount'] ?? 0,
                'discount_amount' => $l['discount_amount'] ?? 0,
                'sales_agent_id' => $l['sales_agent_id'] ?? null,
                'agent_commission_amount' => $l['agent_commission_amount'] ?? 0,
                'sort_order' => $i,
                'updated_by' => auth()->id(),
            ];

            $lineId = $l['id'] ?? null;
            if ($lineId && in_array((int) $lineId, $existingActiveIds, true)) {
                $line = TicketSaleInvoiceLine::find($lineId);
                $line->fill($attrs);
                $line->recalculate();
                $line->save();
                $keepIds[] = $line->id;
            } else {
                $attrs['status'] = TicketSaleInvoiceLine::STATUS_ACTIVE;
                $attrs['created_by'] = auth()->id();
                $line = new TicketSaleInvoiceLine($attrs);
                $line->recalculate();
                $line->save();
                $keepIds[] = $line->id;
            }
        }

        TicketSaleInvoiceLine::where('ticket_sale_invoice_id', $invoice->id)
            ->where('status', TicketSaleInvoiceLine::STATUS_ACTIVE)
            ->whereNotIn('id', $keepIds)
            ->delete();
    }

    /**
     * Normalizes every submitted line's ticket # and checks for
     * duplicates (within the form and against every other invoice's
     * lines) before anything touches the database. Thrown as a
     * ValidationException so the form re-renders with per-row errors
     * instead of a generic 500.
     */
    protected function prepareAndValidateLines(array $rawLines, ?int $ignoreInvoiceId = null): array
    {
        $errors = [];
        $seen = [];
        $prepared = [];

        foreach ($rawLines as $i => $l) {
            if (empty($l['pax_name'])) {
                continue;
            }

            $rowLabel = 'Ticket row ' . ($i + 1);

            try {
                $ticketNo = TicketSaleInvoiceLine::normalizeTicketNo($l['ticket_no'] ?? null);
            } catch (\InvalidArgumentException $e) {
                $errors["lines.$i.ticket_no"] = "{$rowLabel}: " . $e->getMessage();
                continue;
            }

            if ($ticketNo) {
                if (isset($seen[$ticketNo])) {
                    $errors["lines.$i.ticket_no"] = "{$rowLabel}: ticket # {$ticketNo} is duplicated in this invoice.";
                    continue;
                }
                $seen[$ticketNo] = true;

                $conflict = TicketSaleInvoiceLine::where('ticket_no', $ticketNo)
                    ->when($ignoreInvoiceId, fn ($q) => $q->where('ticket_sale_invoice_id', '!=', $ignoreInvoiceId))
                    ->exists();

                if ($conflict) {
                    $errors["lines.$i.ticket_no"] = "{$rowLabel}: ticket # {$ticketNo} is already used on another invoice.";
                    continue;
                }
            }

            $l['ticket_no'] = $ticketNo;
            $prepared[$i] = $l;
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        if (empty($prepared)) {
            throw ValidationException::withMessages(['lines' => 'Add at least one ticket.']);
        }

        return $prepared;
    }

    protected function referenceData(): array
    {
        return [
            'customers' => Customer::where('is_active', true)->orderBy('name')->get(),
            'suppliers' => Supplier::where('is_active', true)->orderBy('name')->get(),
            'airlines' => Airline::where('is_active', true)->orderBy('name')->get(),
            'agents' => User::orderBy('name')->get(),
            'paxTypes' => TicketSaleInvoiceLine::PAX_TYPES,
        ];
    }

    protected function validateLinesShape(Request $request): void
    {
        $request->validate([
            'lines' => 'required|array|min:1',
            'lines.*.id' => 'nullable|integer',
            'lines.*.pax_name' => 'nullable|string|max:255',
            'lines.*.pax_type' => 'nullable|in:adult,child,infant',
            'lines.*.supplier_id' => 'nullable|exists:suppliers,id',
            'lines.*.airline_id' => 'nullable|exists:airlines,id',
            'lines.*.pnr' => 'nullable|string|max:50',
            'lines.*.ticket_no' => 'nullable|string|max:20',
            'lines.*.trip_type' => 'nullable|in:one_way,return',
            'lines.*.leg1_from' => 'nullable|string|max:100',
            'lines.*.leg1_stay' => 'nullable|string|max:100',
            'lines.*.leg1_to' => 'nullable|string|max:100',
            'lines.*.leg2_from' => 'nullable|string|max:100',
            'lines.*.leg2_stay' => 'nullable|string|max:100',
            'lines.*.leg2_to' => 'nullable|string|max:100',
            'lines.*.fare_amount' => 'nullable|numeric|min:0',
            'lines.*.tax_amount' => 'nullable|numeric|min:0',
            'lines.*.apt_charges' => 'nullable|numeric|min:0',
            'lines.*.commission_percent' => 'nullable|numeric|min:0|max:100',
            'lines.*.wht_amount' => 'nullable|numeric|min:0',
            'lines.*.psf_amount' => 'nullable|numeric|min:0',
            'lines.*.discount_amount' => 'nullable|numeric|min:0',
            'lines.*.sales_agent_id' => 'nullable|exists:users,id',
            'lines.*.agent_commission_amount' => 'nullable|numeric|min:0',
        ]);
    }

    protected function validatedHeader(Request $request): array
    {
        return $request->validate([
            'invoice_date' => 'required|date',
            'adjustment_date' => 'nullable|date',
            'customer_id' => 'required|exists:customers,id',
            'quotation_id' => 'nullable|exists:quotations,id',
            'remarks' => 'nullable|string|max:2000',
        ]);
    }

    protected function nextNumber(): string
    {
        $year = date('y');
        $last = TicketSaleInvoice::withTrashed()
            ->where('invoice_no', 'like', "SIN-{$year}-%")
            ->count();

        return sprintf('SIN-%s-%04d', $year, $last + 1);
    }
}