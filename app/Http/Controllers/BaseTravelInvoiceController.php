<?php

namespace App\Http\Controllers;

use App\Models\Airline;
use App\Models\ChargeTemplate;
use App\Models\ChargeType;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Hotel;
use App\Models\HotelRoom;
use App\Models\InvoiceTicketDetail;
use App\Models\Quotation;
use App\Models\RoomView;
use App\Models\Service;
use App\Models\Supplier;
use App\Models\TicketSaleInvoiceLine;
use App\Models\TravelInvoice;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VisaType;
use App\Services\ServiceLineWriter;
use App\Services\TourInvoicePostingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Shared implementation for Sale Invoice (tickets only) and Tour Invoice
 * (tickets + hotel/transport/visa/other services) — same header shape,
 * same service_lines/travel_passengers tables, same Receivable/Payable/
 * Income line math as Quotation. The two concrete controllers below only
 * differ in invoice_type and which service-type tabs are open.
 *
 * Deliberately NOT named SaleInvoiceController: the base app already has
 * one for inventory sales at the `sale_invoices` route. This module uses
 * `ticket_invoices` / `tour_invoices` instead so nothing collides.
 */
abstract class BaseTravelInvoiceController extends Controller
{
    public function __construct(protected ServiceLineWriter $lineWriter)
    {
    }

    abstract protected function invoiceType(): string;

    abstract protected function allowedServiceTypes(): array;

    abstract protected function routeUri(): string;

    abstract protected function viewPrefix(): string;

    public function index(Request $request)
    {
        $query = TravelInvoice::with('customer', 'quotation')
            ->where('invoice_type', $this->invoiceType())
            ->latest('invoice_date');

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }
        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }
        if ($request->filled('search')) {
            $term = $request->search;
            $query->where(function ($q) use ($term) {
                $q->where('invoice_no', 'like', "%{$term}%")
                  ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$term}%"));
            });
        }

        $invoices = $query->paginate(25)->withQueryString();
        $customers = Customer::orderBy('name')->get();

        return view("{$this->viewPrefix()}.index", [
            'invoices' => $invoices,
            'customers' => $customers,
            'statuses' => TravelInvoice::STATUSES,
            'routeUri' => $this->routeUri(),
        ]);
    }

    public function create(Request $request)
    {
        $quotation = null;
        if ($request->filled('quotation_id')) {
            $quotation = Quotation::with('customer', 'passengers', 'serviceLines.supplier')
                ->where('status', 'approved')
                ->find($request->quotation_id);
        }

        return view("{$this->viewPrefix()}.create", array_merge(
            $this->referenceData(),
            ['quotation' => $quotation]
        ));
    }

    public function store(Request $request)
    {
        $validated = $this->validated($request);
        $validated['lines'] = $this->prepareTicketLines($validated['lines'] ?? []);

        DB::beginTransaction();
        try {
            $invoice = TravelInvoice::create([
                'invoice_no' => $this->nextNumber(),
                'invoice_type' => $this->invoiceType(),
                'invoice_date' => $validated['invoice_date'],
                'visit_type' => $validated['visit_type'] ?? null,
                'payment_mode' => $validated['payment_mode'] ?? 'credit',
                'status' => 'draft',
                'customer_id' => $validated['customer_id'],
                'quotation_id' => $validated['quotation_id'] ?? null,
                'name_on_invoice' => $validated['name_on_invoice'] ?? null,
                'cost_center' => $validated['cost_center'] ?? null,
                'staff_id' => $validated['staff_id'] ?? null,
                'remarks' => $validated['remarks'] ?? null,
                'created_by' => auth()->id(),
            ]);

            $this->syncPassengers($invoice, $validated['passengers'] ?? []);
            $this->lineWriter->sync($invoice, $validated['lines'] ?? []);

            $this->markQuotationConverted($validated['quotation_id'] ?? null, $invoice);

            DB::commit();

            return $this->done($request, route("{$this->routeUri()}.show", $invoice->id), ucfirst($this->invoiceType()) . ' invoice created successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('[TravelInvoice] store failed: ' . $e->getMessage());

            return $this->failed($request, 'Could not create invoice: ' . $e->getMessage(), 500);
        }
    }

    public function show(string $id)
    {
        $invoice = $this->findForType($id, [
            'customer', 'quotation', 'staff', 'creator', 'passengers', 'payments',
            'serviceLines.supplier', 'serviceLines.ticketDetail.flights',
            'serviceLines.ticketDetail.airlineRecord', 'serviceLines.ticketDetail.salesAgent',
            'serviceLines.hotelDetail.hotel', 'serviceLines.hotelDetail.hotelRoom',
            'serviceLines.hotelDetail.rooms.hotelRoom', 'serviceLines.hotelDetail.rooms.roomView',
            'serviceLines.transportDetail.vehicle', 'serviceLines.transportDetail.vehicles.vehicle',
            'serviceLines.visaDetail.visaType',
            'serviceLines.otherDetail.service', 'serviceLines.charges.chargeType',
        ]);

        return view("{$this->viewPrefix()}.show", [
            'invoice' => $invoice,
            'routeUri' => $this->routeUri(),
            'ledgerVouchers' => app(TourInvoicePostingService::class)->vouchers($invoice),
            'transitions' => self::TRANSITIONS[$invoice->status] ?? [],
        ]);
    }

    public function edit(string $id)
    {
        $invoice = $this->findForType($id, [
            'passengers', 'serviceLines.ticketDetail.flights', 'serviceLines.hotelDetail.rooms',
            'serviceLines.transportDetail.vehicles', 'serviceLines.visaDetail', 'serviceLines.otherDetail',
            'serviceLines.charges',
        ]);

        if (!$invoice->isEditable()) {
            return redirect()->route("{$this->routeUri()}.show", $id)->with('error', 'Only draft invoices can be edited.');
        }

        return view("{$this->viewPrefix()}.edit", array_merge($this->referenceData(), ['invoice' => $invoice]));
    }

    public function update(Request $request, string $id)
    {
        $invoice = $this->findForType($id);

        if (!$invoice->isEditable()) {
            return $this->failed($request, 'Only draft invoices can be edited.', 422, route("{$this->routeUri()}.show", $id));
        }

        $validated = $this->validated($request);
        $validated['lines'] = $this->prepareTicketLines($validated['lines'] ?? [], $invoice->id);

        DB::beginTransaction();
        try {
            $invoice->update([
                'invoice_date' => $validated['invoice_date'],
                'visit_type' => $validated['visit_type'] ?? null,
                'payment_mode' => $validated['payment_mode'] ?? 'credit',
                'customer_id' => $validated['customer_id'],
                'name_on_invoice' => $validated['name_on_invoice'] ?? null,
                'cost_center' => $validated['cost_center'] ?? null,
                'staff_id' => $validated['staff_id'] ?? null,
                'remarks' => $validated['remarks'] ?? null,
            ]);

            $this->syncPassengers($invoice, $validated['passengers'] ?? []);
            $this->lineWriter->sync($invoice, $validated['lines'] ?? []);

            DB::commit();

            return $this->done($request, route("{$this->routeUri()}.show", $invoice->id), ucfirst($this->invoiceType()) . ' invoice updated successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('[TravelInvoice] update failed: ' . $e->getMessage());

            return $this->failed($request, 'Could not update invoice: ' . $e->getMessage(), 500);
        }
    }

    public function destroy(string $id)
    {
        $invoice = $this->findForType($id);

        if ($invoice->status === 'confirmed') {
            return redirect()->route("{$this->routeUri()}.index")->with('error', 'A confirmed invoice cannot be deleted.');
        }
        if ($invoice->payments()->exists()) {
            return redirect()->route("{$this->routeUri()}.show", $invoice->id)->with('error', 'This invoice has payments recorded against it — delete those payments first.');
        }

        $invoice->serviceLines()->delete();
        $invoice->passengers()->delete();
        $invoice->delete();

        return redirect()->route("{$this->routeUri()}.index")->with('success', 'Invoice deleted successfully.');
    }

    public function print(string $id)
    {
        $invoice = $this->findForType($id, [
            'customer', 'passengers', 'payments', 'serviceLines.supplier',
            'serviceLines.ticketDetail.flights', 'serviceLines.ticketDetail.airlineRecord',
            'serviceLines.hotelDetail.hotel', 'serviceLines.hotelDetail.rooms.hotelRoom', 'serviceLines.hotelDetail.rooms.roomView',
            'serviceLines.transportDetail.vehicle', 'serviceLines.transportDetail.vehicles.vehicle',
            'serviceLines.visaDetail.visaType', 'serviceLines.otherDetail.service',
            'serviceLines.charges.chargeType',
        ]);

        // Customer copy by default (no supplier / cost / margin); ?copy=internal for the office copy.
        if (request('copy') === 'internal') {
            return view("{$this->viewPrefix()}.print", ['invoice' => $invoice]);
        }

        return view('travel-invoices._customer_print', ['invoice' => $invoice]);
    }

    /**
     * Allowed moves (client sign-off pending):
     *   Draft     → Confirmed (posts the vouchers) | Cancelled
     *   Confirmed → Draft (removes the vouchers, editable again) | Cancelled (removes the vouchers)
     *   Cancelled → Draft (re-open)
     */
    public const TRANSITIONS = [
        'draft' => ['confirmed', 'cancelled'],
        'confirmed' => ['draft', 'cancelled'],
        'cancelled' => ['draft'],
    ];

    public function updateStatus(Request $request, string $id)
    {
        $invoice = $this->findForType($id);

        $validated = $request->validate([
            'status' => ['required', Rule::in(TravelInvoice::STATUSES)],
        ]);
        $to = $validated['status'];

        if (!in_array($to, self::TRANSITIONS[$invoice->status] ?? [], true)) {
            return back()->with('error', 'A ' . $invoice->status . ' invoice cannot be moved to ' . $to . '.');
        }

        $posting = app(TourInvoicePostingService::class);
        $wasPosted = $posting->isPosted($invoice);

        try {
            DB::transaction(function () use ($invoice, $to, $posting) {
                if ($to === 'confirmed') {
                    $posting->post($invoice);
                } else {
                    $posting->unpost($invoice);
                }
                $invoice->update(['status' => $to]);
            });
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $message = match ($to) {
            'confirmed' => 'Invoice confirmed and posted to the ledgers.',
            'cancelled' => 'Invoice cancelled' . ($wasPosted ? '; its ledger vouchers were removed.' : '.'),
            default => 'Invoice moved back to draft' . ($wasPosted ? '; its ledger vouchers were removed.' : '.'),
        };

        return back()->with('success', $message);
    }

    /** Scope lookups to this controller's invoice_type so /ticket_invoices/{id} 404s on a tour invoice id and vice versa. */
    protected function findForType(string $id, array $with = []): TravelInvoice
    {
        return TravelInvoice::with($with)
            ->where('invoice_type', $this->invoiceType())
            ->findOrFail($id);
    }

    protected function markQuotationConverted(?int $quotationId, TravelInvoice $invoice): void
    {
        if (!$quotationId) {
            return;
        }

        $quotation = Quotation::find($quotationId);
        if ($quotation && $quotation->status === 'approved') {
            $quotation->update([
                'status' => 'converted',
                'converted_invoice_type' => $this->invoiceType(),
                'converted_invoice_id' => $invoice->id,
            ]);
        }
    }

    protected function syncPassengers(TravelInvoice $invoice, array $passengers): void
    {
        $invoice->passengers()->delete();

        foreach ($passengers as $p) {
            if (empty($p['name'])) {
                continue;
            }
            $invoice->passengers()->create([
                'name' => $p['name'],
                'passport_no_nic' => $p['passport_no_nic'] ?? null,
                'pax_type' => $p['pax_type'] ?? 'adult',
                'nationality' => $p['nationality'] ?? null,
                'dob' => $p['dob'] ?? null,
            ]);
        }
    }

    protected function referenceData(): array
    {
        return [
            'customers' => Customer::where('is_active', true)->orderBy('name')->get(),
            'suppliers' => Supplier::where('is_active', true)->orderBy('name')->get(),
            'chargeTemplates' => ChargeTemplate::where('is_active', true)->with('items')->get(),
            'chargeTypes' => ChargeType::where('is_active', true)->orderBy('name')->get(),
            'hotels' => Hotel::where('is_active', true)->with('rooms')->orderBy('name')->get(),
            'hotelRooms' => HotelRoom::where('is_active', true)->get(),
            'roomViews' => RoomView::where('is_active', true)->orderBy('name')->get(),
            'currencies' => Currency::where('is_active', true)->orderBy('code')->get(),
            'vehicles' => Vehicle::where('is_active', true)->orderBy('name')->get(),
            'visaTypes' => VisaType::where('is_active', true)->orderBy('name')->get(),
            'services' => Service::where('is_active', true)->orderBy('name')->get(),
            'staffUsers' => User::orderBy('name')->get(),
            'airlines' => Airline::where('is_active', true)->orderBy('name')->get(),
            'paxTypes' => TicketSaleInvoiceLine::PAX_TYPES,
            'serviceTypes' => $this->allowedServiceTypes(),
            'routeUri' => $this->routeUri(),
            'invoiceType' => $this->invoiceType(),
        ];
    }

    protected function validated(Request $request): array
    {
        $allowedTypes = implode(',', $this->allowedServiceTypes());

        return $request->validate([
            'invoice_date' => 'required|date',
            'visit_type' => 'nullable|string|max:100',
            'payment_mode' => 'nullable|in:cash,credit',
            'customer_id' => 'required|exists:customers,id',
            'quotation_id' => 'nullable|exists:quotations,id',
            'name_on_invoice' => 'nullable|string|max:255',
            'cost_center' => 'nullable|string|max:100',
            'staff_id' => 'nullable|exists:users,id',
            'remarks' => 'nullable|string|max:2000',

            'passengers' => 'nullable|array',
            'passengers.*.name' => 'nullable|string|max:255',
            'passengers.*.passport_no_nic' => 'nullable|string|max:100',
            'passengers.*.pax_type' => 'nullable|in:adult,child,infant',
            'passengers.*.nationality' => 'nullable|string|max:100',
            'passengers.*.dob' => 'nullable|date',

            'lines' => 'nullable|array',
            'lines.*.service_type' => "nullable|in:{$allowedTypes}",
            'lines.*.supplier_id' => 'nullable|exists:suppliers,id',
            'lines.*.charge_template_id' => 'nullable|exists:charge_templates,id',
            'lines.*.description' => 'nullable|string|max:255',
            'lines.*.currency' => 'nullable|string|max:3',
            'lines.*.exchange_rate' => 'nullable|numeric|min:0',
            'lines.*.receivable_f_amount' => 'nullable|numeric|min:0',
            'lines.*.payable_f_amount' => 'nullable|numeric|min:0',

            'lines.*.charges' => 'nullable|array',
            'lines.*.charges.*.charge_type_id' => 'nullable|exists:charge_types,id',
            'lines.*.charges.*.value' => 'nullable|numeric',
            'lines.*.charges.*.computed_amount' => 'nullable|numeric',

            'lines.*.detail' => 'nullable|array',
            'lines.*.detail.pnr' => 'nullable|string|max:50',
            'lines.*.detail.gds' => 'nullable|string|max:50',
            'lines.*.detail.airline' => 'nullable|string|max:100',
            'lines.*.detail.ticket_no' => 'nullable|string|max:50',
            'lines.*.detail.ticket_type' => 'nullable|in:domestic,international',
            'lines.*.detail.sector' => 'nullable|string|max:100',
            'lines.*.detail.tour_code' => 'nullable|string|max:50',
            'lines.*.detail.issue_date' => 'nullable|date',
            // Ticket tab — identical fields to the Ticket Sale Invoice.
            'lines.*.detail.pax_type' => ['nullable', Rule::in(TicketSaleInvoiceLine::PAX_TYPES)],
            'lines.*.detail.airline_id' => 'nullable|exists:airlines,id',
            'lines.*.detail.trip_type' => ['nullable', Rule::in(TicketSaleInvoiceLine::TRIP_TYPES)],
            'lines.*.detail.leg1_from' => 'nullable|string|max:100',
            'lines.*.detail.leg1_stay' => 'nullable|string|max:255',
            'lines.*.detail.leg1_to' => 'nullable|string|max:100',
            'lines.*.detail.leg1_departure_date' => 'nullable|date',
            'lines.*.detail.leg1_departure_time' => ['nullable', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'lines.*.detail.leg2_from' => 'nullable|string|max:100',
            'lines.*.detail.leg2_stay' => 'nullable|string|max:255',
            'lines.*.detail.leg2_to' => 'nullable|string|max:100',
            'lines.*.detail.leg2_departure_date' => 'nullable|date',
            'lines.*.detail.leg2_departure_time' => ['nullable', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'lines.*.detail.fare_amount' => 'nullable|numeric|min:0',
            'lines.*.detail.tax_amount' => 'nullable|numeric|min:0',
            'lines.*.detail.apt_percent' => 'nullable|numeric|min:0|max:9999',
            'lines.*.detail.apt_charges' => 'nullable|numeric|min:0',
            'lines.*.detail.apt_input_mode' => ['nullable', Rule::in(TicketSaleInvoiceLine::INPUT_MODES)],
            'lines.*.detail.commission_percent' => 'nullable|numeric|min:0|max:100',
            'lines.*.detail.commission_amount' => 'nullable|numeric|min:0',
            'lines.*.detail.commission_input_mode' => ['nullable', Rule::in(TicketSaleInvoiceLine::INPUT_MODES)],
            'lines.*.detail.wht_percent' => 'nullable|numeric|min:0|max:100',
            'lines.*.detail.wht_amount' => 'nullable|numeric|min:0',
            'lines.*.detail.wht_input_mode' => ['nullable', Rule::in(TicketSaleInvoiceLine::INPUT_MODES)],
            'lines.*.detail.psf_percent' => 'nullable|numeric|min:0|max:9999',
            'lines.*.detail.psf_amount' => 'nullable|numeric|min:0',
            'lines.*.detail.psf_basis' => ['nullable', Rule::in(TicketSaleInvoiceLine::PSF_BASES)],
            'lines.*.detail.psf_input_mode' => ['nullable', Rule::in(TicketSaleInvoiceLine::INPUT_MODES)],
            'lines.*.detail.discount_percent' => 'nullable|numeric|min:0|max:100',
            'lines.*.detail.discount_amount' => 'nullable|numeric|min:0',
            'lines.*.detail.discount_input_mode' => ['nullable', Rule::in(TicketSaleInvoiceLine::INPUT_MODES)],
            'lines.*.detail.sales_agent_id' => 'nullable|exists:users,id',
            'lines.*.detail.agent_commission_input_mode' => ['nullable', Rule::in(TicketSaleInvoiceLine::INPUT_MODES)],

            'lines.*.detail.flights' => 'nullable|array',
            'lines.*.detail.flights.*.city' => 'nullable|string|max:100',
            'lines.*.detail.flights.*.flight_no' => 'nullable|string|max:20',
            'lines.*.detail.flights.*.dep_date' => 'nullable|date',
            'lines.*.detail.flights.*.dep_time' => 'nullable|string|max:10',
            'lines.*.detail.flights.*.arr_time' => 'nullable|string|max:10',
            'lines.*.detail.flights.*.fare_basis' => 'nullable|string|max:50',
            'lines.*.detail.hotel_id' => 'nullable|exists:hotels,id',
            'lines.*.detail.hotel_room_id' => 'nullable|exists:hotel_rooms,id',
            'lines.*.detail.room_view_id' => 'nullable|exists:room_views,id',
            'lines.*.detail.check_in' => 'nullable|date',
            'lines.*.detail.check_out' => 'nullable|date',
            'lines.*.detail.nights' => 'nullable|integer|min:0',
            'lines.*.detail.room_qty' => 'nullable|integer|min:0',
            'lines.*.detail.extra_bed_qty' => 'nullable|integer|min:0',
            'lines.*.detail.booking_name' => 'nullable|string|max:255',

            // Hotel tab fix round 2: dual receivable/payable currency+rate,
            // agent commission, and the Room Details grid (see the
            // 2026_10_03_070000 / 070100 migrations and ServiceLineWriter).
            'lines.*.detail.receivable_currency' => 'nullable|string|max:3',
            'lines.*.detail.receivable_exchange_rate' => 'nullable|numeric|min:0',
            'lines.*.detail.payable_currency' => 'nullable|string|max:3',
            'lines.*.detail.payable_exchange_rate' => 'nullable|numeric|min:0',
            'lines.*.detail.agent_commission_percent' => 'nullable|numeric|min:0|max:100',
            'lines.*.detail.agent_commission_amount' => 'nullable|numeric',
            'lines.*.detail.rooms' => 'nullable|array',
            'lines.*.detail.rooms.*.hotel_room_id' => 'nullable|exists:hotel_rooms,id',
            'lines.*.detail.rooms.*.room_view_id' => 'nullable|exists:room_views,id',
            'lines.*.detail.rooms.*.qty' => 'nullable|integer|min:0',
            'lines.*.detail.rooms.*.rate' => 'nullable|numeric|min:0',
            'lines.*.detail.rooms.*.cost' => 'nullable|numeric|min:0',
            'lines.*.detail.rooms.*.total_amount' => 'nullable|numeric|min:0',

            'lines.*.detail.vehicle_id' => 'nullable|exists:vehicles,id',
            // Transport tab rework: Category is now a dropdown with the
            // same options as Hotel's Category.
            'lines.*.detail.category' => 'nullable|in:umrah,hajj,holiday,tour,visitor',
            // Vehicle Details is now a repeatable grid (a transport line
            // can cover more than one vehicle/sector pair) — same pattern
            // as Hotel's Room Details grid.
            'lines.*.detail.vehicles' => 'nullable|array',
            'lines.*.detail.vehicles.*.vehicle_id' => 'nullable|exists:vehicles,id',
            'lines.*.detail.vehicles.*.sector' => 'nullable|string|max:100',
            'lines.*.detail.visa_type_id' => 'nullable|exists:visa_types,id',
            'lines.*.detail.apply_date' => 'nullable|date',
            'lines.*.detail.expiry_date' => 'nullable|date',
            'lines.*.detail.reference_no' => 'nullable|string|max:100',
            'lines.*.detail.service_id' => 'nullable|exists:services,id',
            'lines.*.detail.qty' => 'nullable|integer|min:1',
            'lines.*.detail.travel_date' => 'nullable|date',
        ], [], $this->ticketAttributeNames($request));
    }

    /** "Ticket row 2 PSF %" instead of "lines.5.detail.psf_percent" in validation messages. */
    protected function ticketAttributeNames(Request $request): array
    {
        $labels = [
            'pax_type' => 'Passenger Type', 'airline_id' => 'Airline', 'ticket_no' => 'Ticket #',
            'fare_amount' => 'Fare Amount', 'tax_amount' => 'Tax Amount',
            'apt_percent' => 'APT %', 'apt_charges' => 'APT Amount',
            'commission_percent' => 'Commission %', 'commission_amount' => 'Commission Amount',
            'wht_percent' => 'WHT %', 'wht_amount' => 'WHT Amount',
            'psf_percent' => 'PSF %', 'psf_amount' => 'PSF Amount',
            'discount_percent' => 'Discount %', 'discount_amount' => 'Discount Amount',
            'sales_agent_id' => 'Sale Agent', 'agent_commission_percent' => 'Sale Commission %',
            'agent_commission_amount' => 'Sale Commission Amount',
            'leg1_departure_date' => 'Departure Date', 'leg1_departure_time' => 'Departure Time',
            'leg2_departure_date' => 'Return Departure Date', 'leg2_departure_time' => 'Return Departure Time',
        ];

        $names = ['customer_id' => 'Customer', 'invoice_date' => 'Date'];
        $ticketRow = 0;
        foreach ((array) $request->input('lines', []) as $i => $line) {
            if (($line['service_type'] ?? null) !== 'ticket') {
                continue;
            }
            $ticketRow++;
            foreach ($labels as $field => $label) {
                $names["lines.$i.detail.$field"] = "Ticket row {$ticketRow} {$label}";
            }
        }

        return $names;
    }

    /**
     * Ticket tab rules copied from the Ticket Sale Invoice: rows with no
     * passenger name are skipped; Ticket # is blank or exactly 13 digits
     * (stored as 220-1234-567-890), unique within the invoice and not
     * already on another Tour Invoice or Ticket Sale Invoice; Airline is
     * auto-detected from the ticket's 3-digit prefix when not picked.
     */
    protected function prepareTicketLines(array $lines, ?int $ignoreInvoiceId = null): array
    {
        $errors = [];
        $seen = [];
        $ticketRow = 0;

        foreach ($lines as $i => $line) {
            if (($line['service_type'] ?? null) !== 'ticket' || !is_array($line['detail'] ?? null) || !array_key_exists('fare_amount', $line['detail'])) {
                continue;
            }
            $ticketRow++;

            if (!filled($line['description'] ?? null)) {
                unset($lines[$i]); // blank grid row
                continue;
            }

            $label = "Ticket row {$ticketRow}";
            try {
                $ticketNo = TicketSaleInvoiceLine::normalizeTicketNo($line['detail']['ticket_no'] ?? null);
            } catch (\InvalidArgumentException $e) {
                $errors["lines.$i.detail.ticket_no"] = "{$label}: " . $e->getMessage();
                continue;
            }

            if ($ticketNo) {
                if (isset($seen[$ticketNo])) {
                    $errors["lines.$i.detail.ticket_no"] = "{$label}: ticket # {$ticketNo} is duplicated in this invoice.";
                    continue;
                }
                $seen[$ticketNo] = true;

                $onTour = InvoiceTicketDetail::where('ticket_no', $ticketNo)
                    ->whereHas('serviceLine', fn ($q) => $q->where('linkable_type', TravelInvoice::class)
                        ->when($ignoreInvoiceId, fn ($q2) => $q2->where('linkable_id', '!=', $ignoreInvoiceId)))
                    ->exists();
                $onTicketSale = TicketSaleInvoiceLine::where('ticket_no', $ticketNo)->exists();
                if ($onTour || $onTicketSale) {
                    $errors["lines.$i.detail.ticket_no"] = "{$label}: ticket # {$ticketNo} is already used on another invoice.";
                    continue;
                }

                if (empty($line['detail']['airline_id'])) {
                    $code = TicketSaleInvoiceLine::airlineCodeFromTicketNo($ticketNo);
                    $lines[$i]['detail']['airline_id'] = optional(Airline::where('numeric_code', $code)->first())->id;
                }
            }

            $lines[$i]['detail']['ticket_no'] = $ticketNo;
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return $lines;
    }

    /** Success: JSON {redirect} for the AJAX form, a normal redirect otherwise. */
    protected function done(Request $request, string $url, string $message)
    {
        if ($request->expectsJson()) {
            session()->flash('success', $message);

            return response()->json(['message' => $message, 'redirect' => $url]);
        }

        return redirect()->to($url)->with('success', $message);
    }

    /** Error that never reloads the page for an AJAX submit. */
    protected function failed(Request $request, string $message, int $status = 422, ?string $redirect = null)
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'errors' => ['_' => [$message]]], $status);
        }

        return ($redirect ? redirect()->to($redirect) : back()->withInput())->with('error', $message);
    }

    protected function nextNumber(): string
    {
        $year = date('y');
        $prefix = $this->invoiceType() === 'sale' ? 'SIN' : 'TIN';
        $last = TravelInvoice::withTrashed()
            ->where('invoice_type', $this->invoiceType())
            ->where('invoice_no', 'like', "{$prefix}-{$year}-%")
            ->count();

        return sprintf('%s-%s-%04d', $prefix, $year, $last + 1);
    }
}
