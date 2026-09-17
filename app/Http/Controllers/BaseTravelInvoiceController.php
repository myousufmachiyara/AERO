<?php

namespace App\Http\Controllers;

use App\Models\ChargeTemplate;
use App\Models\ChargeType;
use App\Models\Customer;
use App\Models\Hotel;
use App\Models\HotelRoom;
use App\Models\Quotation;
use App\Models\Service;
use App\Models\Supplier;
use App\Models\TravelInvoice;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VisaType;
use App\Services\ServiceLineWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

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

            return redirect()->route("{$this->routeUri()}.show", $invoice->id)
                ->with('success', ucfirst($this->invoiceType()) . ' invoice created successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('[TravelInvoice] store failed: ' . $e->getMessage());

            return back()->withInput()->with('error', 'Could not create invoice: ' . $e->getMessage());
        }
    }

    public function show(string $id)
    {
        $invoice = $this->findForType($id, [
            'customer', 'quotation', 'staff', 'creator', 'passengers', 'payments',
            'serviceLines.supplier', 'serviceLines.ticketDetail.flights',
            'serviceLines.hotelDetail.hotel', 'serviceLines.hotelDetail.hotelRoom',
            'serviceLines.transportDetail.vehicle', 'serviceLines.visaDetail.visaType',
            'serviceLines.otherDetail.service', 'serviceLines.charges.chargeType',
        ]);

        return view("{$this->viewPrefix()}.show", ['invoice' => $invoice, 'routeUri' => $this->routeUri()]);
    }

    public function edit(string $id)
    {
        $invoice = $this->findForType($id, [
            'passengers', 'serviceLines.ticketDetail.flights', 'serviceLines.hotelDetail',
            'serviceLines.transportDetail', 'serviceLines.visaDetail', 'serviceLines.otherDetail',
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
            return redirect()->route("{$this->routeUri()}.show", $id)->with('error', 'Only draft invoices can be edited.');
        }

        $validated = $this->validated($request);

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

            return redirect()->route("{$this->routeUri()}.show", $invoice->id)
                ->with('success', ucfirst($this->invoiceType()) . ' invoice updated successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('[TravelInvoice] update failed: ' . $e->getMessage());

            return back()->withInput()->with('error', 'Could not update invoice: ' . $e->getMessage());
        }
    }

    public function destroy(string $id)
    {
        $invoice = $this->findForType($id);

        if ($invoice->status === 'confirmed') {
            return redirect()->route("{$this->routeUri()}.index")->with('error', 'A confirmed invoice cannot be deleted.');
        }

        $invoice->serviceLines()->delete();
        $invoice->passengers()->delete();
        $invoice->delete();

        return redirect()->route("{$this->routeUri()}.index")->with('success', 'Invoice deleted successfully.');
    }

    public function print(string $id)
    {
        $invoice = $this->findForType($id, [
            'customer', 'passengers', 'serviceLines.supplier',
            'serviceLines.ticketDetail.flights', 'serviceLines.hotelDetail',
            'serviceLines.transportDetail', 'serviceLines.visaDetail', 'serviceLines.otherDetail',
            'serviceLines.charges.chargeType',
        ]);

        return view("{$this->viewPrefix()}.print", ['invoice' => $invoice]);
    }

    public function updateStatus(Request $request, string $id)
    {
        $invoice = $this->findForType($id);

        $validated = $request->validate([
            'status' => ['required', Rule::in(TravelInvoice::STATUSES)],
        ]);

        $invoice->update(['status' => $validated['status']]);

        return back()->with('success', "Invoice marked as {$validated['status']}.");
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
            'chargeTemplates' => ChargeTemplate::where('is_active', true)->get(),
            'chargeTypes' => ChargeType::where('is_active', true)->orderBy('name')->get(),
            'hotels' => Hotel::where('is_active', true)->with('rooms')->orderBy('name')->get(),
            'hotelRooms' => HotelRoom::where('is_active', true)->get(),
            'vehicles' => Vehicle::where('is_active', true)->orderBy('name')->get(),
            'visaTypes' => VisaType::where('is_active', true)->orderBy('name')->get(),
            'services' => Service::where('is_active', true)->orderBy('name')->get(),
            'staffUsers' => User::orderBy('name')->get(),
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
            'lines.*.detail.flights' => 'nullable|array',
            'lines.*.detail.flights.*.city' => 'nullable|string|max:100',
            'lines.*.detail.flights.*.flight_no' => 'nullable|string|max:20',
            'lines.*.detail.flights.*.dep_date' => 'nullable|date',
            'lines.*.detail.flights.*.dep_time' => 'nullable|string|max:10',
            'lines.*.detail.flights.*.arr_time' => 'nullable|string|max:10',
            'lines.*.detail.flights.*.fare_basis' => 'nullable|string|max:50',
            'lines.*.detail.hotel_id' => 'nullable|exists:hotels,id',
            'lines.*.detail.hotel_room_id' => 'nullable|exists:hotel_rooms,id',
            'lines.*.detail.check_in' => 'nullable|date',
            'lines.*.detail.check_out' => 'nullable|date',
            'lines.*.detail.nights' => 'nullable|integer|min:0',
            'lines.*.detail.room_qty' => 'nullable|integer|min:0',
            'lines.*.detail.extra_bed_qty' => 'nullable|integer|min:0',
            'lines.*.detail.booking_name' => 'nullable|string|max:255',
            'lines.*.detail.vehicle_id' => 'nullable|exists:vehicles,id',
            'lines.*.detail.visa_type_id' => 'nullable|exists:visa_types,id',
            'lines.*.detail.apply_date' => 'nullable|date',
            'lines.*.detail.expiry_date' => 'nullable|date',
            'lines.*.detail.reference_no' => 'nullable|string|max:100',
            'lines.*.detail.service_id' => 'nullable|exists:services,id',
            'lines.*.detail.qty' => 'nullable|integer|min:1',
        ]);
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
