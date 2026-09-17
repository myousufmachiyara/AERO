<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Supplier;
use App\Models\VendorComplaint;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Vendor complaint log — tracks which supplier provided which service on
 * which booking, feeding Supplier::refreshComplaintFlag() so a vendor that
 * crosses the configured threshold gets auto-flagged (see the "High
 * Complaints" badge on the supplier show page and, at the point of
 * choice, in every Supplier dropdown across Quotation/Sale/Tour Invoice).
 */
class VendorComplaintController extends Controller
{
    public function index(Request $request)
    {
        $query = VendorComplaint::with('supplier', 'customer')->latest('complaint_date');

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }
        if ($request->filled('service_type')) {
            $query->where('service_type', $request->service_type);
        }
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $complaints = $query->paginate(25)->withQueryString();
        $suppliers = Supplier::orderBy('name')->get();
        $flaggedSuppliers = Supplier::where('is_flagged', true)->orderBy('name')->get();

        return view('vendor-complaints.index', [
            'complaints' => $complaints,
            'suppliers' => $suppliers,
            'flaggedSuppliers' => $flaggedSuppliers,
            'serviceTypes' => VendorComplaint::SERVICE_TYPES,
            'statuses' => VendorComplaint::STATUSES,
        ]);
    }

    public function create(Request $request)
    {
        return view('vendor-complaints.create', array_merge(
            $this->referenceData(),
            ['prefillSupplierId' => $request->integer('supplier_id') ?: null]
        ));
    }

    public function store(Request $request)
    {
        $validated = $this->validated($request);
        $validated['created_by'] = auth()->id();

        $complaint = VendorComplaint::create($validated);
        $complaint->supplier->refreshComplaintFlag();

        return redirect()->route('vendor_complaints.show', $complaint->id)->with('success', 'Complaint logged successfully.');
    }

    public function show(string $id)
    {
        $complaint = VendorComplaint::with('supplier', 'customer', 'creator')->findOrFail($id);

        return view('vendor-complaints.show', compact('complaint'));
    }

    public function edit(string $id)
    {
        $complaint = VendorComplaint::findOrFail($id);

        return view('vendor-complaints.edit', array_merge($this->referenceData(), compact('complaint')));
    }

    public function update(Request $request, string $id)
    {
        $complaint = VendorComplaint::findOrFail($id);
        $oldSupplierId = $complaint->supplier_id;

        $validated = $this->validated($request);
        $complaint->update($validated);

        $complaint->supplier->refreshComplaintFlag();
        if ($oldSupplierId !== $complaint->supplier_id) {
            Supplier::find($oldSupplierId)?->refreshComplaintFlag();
        }

        return redirect()->route('vendor_complaints.show', $complaint->id)->with('success', 'Complaint updated successfully.');
    }

    public function destroy(string $id)
    {
        $complaint = VendorComplaint::findOrFail($id);
        $supplier = $complaint->supplier;
        $complaint->delete();
        $supplier->refreshComplaintFlag();

        return redirect()->route('vendor_complaints.index')->with('success', 'Complaint deleted successfully.');
    }

    public function print(string $id)
    {
        $complaint = VendorComplaint::with('supplier', 'customer')->findOrFail($id);

        return view('vendor-complaints.print', compact('complaint'));
    }

    protected function referenceData(): array
    {
        return [
            'suppliers' => Supplier::orderBy('name')->get(),
            'customers' => Customer::orderBy('name')->get(),
            'serviceTypes' => VendorComplaint::SERVICE_TYPES,
            'severities' => VendorComplaint::SEVERITIES,
            'statuses' => VendorComplaint::STATUSES,
        ];
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'service_type' => ['required', Rule::in(VendorComplaint::SERVICE_TYPES)],
            'customer_id' => 'nullable|exists:customers,id',
            'reference' => 'nullable|string|max:100',
            'complaint_date' => 'required|date',
            'severity' => ['required', Rule::in(VendorComplaint::SEVERITIES)],
            'status' => ['required', Rule::in(VendorComplaint::STATUSES)],
            'description' => 'required|string|max:2000',
            'resolution_notes' => 'nullable|string|max:2000',
        ]);
    }
}
