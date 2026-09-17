<?php

namespace App\Http\Controllers;

use App\Models\ChargeTemplate;
use App\Models\Customer;
use App\Models\Package;
use App\Models\Quotation;
use App\Models\Supplier;
use App\Models\TravelPassenger;
use App\Services\ServiceLineWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class QuotationController extends Controller
{
    private const SERVICE_TYPES = ['ticket', 'hotel', 'transport', 'visa', 'other'];

    public function __construct(protected ServiceLineWriter $lineWriter)
    {
    }

    public function index(Request $request)
    {
        $query = Quotation::with('customer', 'package')->latest('quotation_date');

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }
        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }
        if ($request->filled('search')) {
            $term = $request->search;
            $query->where(function ($q) use ($term) {
                $q->where('quotation_no', 'like', "%{$term}%")
                  ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$term}%"));
            });
        }

        $quotations = $query->paginate(25)->withQueryString();
        $customers = Customer::orderBy('name')->get();

        return view('quotations.index', [
            'quotations' => $quotations,
            'customers' => $customers,
            'statuses' => Quotation::STATUSES,
        ]);
    }

    public function create()
    {
        return view('quotations.create', $this->referenceData());
    }

    public function store(Request $request)
    {
        $validated = $this->validated($request);

        DB::beginTransaction();
        try {
            $quotation = Quotation::create([
                'quotation_no' => $this->nextNumber(),
                'quotation_date' => $validated['quotation_date'],
                'valid_until' => $validated['valid_until'] ?? null,
                'customer_id' => $validated['customer_id'],
                'package_id' => $validated['package_id'] ?? null,
                'visit_type' => $validated['visit_type'] ?? null,
                'remarks' => $validated['remarks'] ?? null,
                'status' => 'draft',
                'created_by' => auth()->id(),
            ]);

            $this->syncPassengers($quotation, $validated['passengers'] ?? []);
            $this->lineWriter->sync($quotation, $validated['lines'] ?? []);

            DB::commit();

            return redirect()->route('quotations.show', $quotation->id)->with('success', 'Quotation created successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('[Quotation] store failed: ' . $e->getMessage());

            return back()->withInput()->with('error', 'Could not create quotation: ' . $e->getMessage());
        }
    }

    public function show(string $id)
    {
        $quotation = Quotation::with(['customer', 'package', 'passengers', 'serviceLines.supplier', 'creator'])->findOrFail($id);

        return view('quotations.show', compact('quotation'));
    }

    public function edit(string $id)
    {
        $quotation = Quotation::with('passengers', 'serviceLines')->findOrFail($id);

        if (!$quotation->isEditable()) {
            return redirect()->route('quotations.show', $id)->with('error', 'Only draft or sent quotations can be edited.');
        }

        return view('quotations.edit', array_merge($this->referenceData(), ['quotation' => $quotation]));
    }

    public function update(Request $request, string $id)
    {
        $quotation = Quotation::findOrFail($id);

        if (!$quotation->isEditable()) {
            return redirect()->route('quotations.show', $id)->with('error', 'Only draft or sent quotations can be edited.');
        }

        $validated = $this->validated($request);

        DB::beginTransaction();
        try {
            $quotation->update([
                'quotation_date' => $validated['quotation_date'],
                'valid_until' => $validated['valid_until'] ?? null,
                'customer_id' => $validated['customer_id'],
                'package_id' => $validated['package_id'] ?? null,
                'visit_type' => $validated['visit_type'] ?? null,
                'remarks' => $validated['remarks'] ?? null,
            ]);

            $this->syncPassengers($quotation, $validated['passengers'] ?? []);
            $this->lineWriter->sync($quotation, $validated['lines'] ?? []);

            DB::commit();

            return redirect()->route('quotations.show', $quotation->id)->with('success', 'Quotation updated successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('[Quotation] update failed: ' . $e->getMessage());

            return back()->withInput()->with('error', 'Could not update quotation: ' . $e->getMessage());
        }
    }

    public function destroy(string $id)
    {
        $quotation = Quotation::findOrFail($id);

        if ($quotation->status === 'converted') {
            return redirect()->route('quotations.index')->with('error', 'A converted quotation cannot be deleted.');
        }

        $quotation->serviceLines()->delete();
        $quotation->passengers()->delete();
        $quotation->delete();

        return redirect()->route('quotations.index')->with('success', 'Quotation deleted successfully.');
    }

    public function print(string $id)
    {
        $quotation = Quotation::with(['customer', 'package', 'passengers', 'serviceLines.supplier'])->findOrFail($id);

        return view('quotations.print', compact('quotation'));
    }

    /**
     * Status workflow (draft -> sent -> approved/rejected). Conversion to a
     * Sale/Tour Invoice is a Phase 3 action once those modules exist, so it
     * is intentionally not offered here yet.
     */
    public function updateStatus(Request $request, string $id)
    {
        $quotation = Quotation::findOrFail($id);

        $validated = $request->validate([
            'status' => ['required', Rule::in(['draft', 'sent', 'approved', 'rejected', 'expired'])],
        ]);

        if ($quotation->status === 'converted') {
            return back()->with('error', 'A converted quotation\'s status can no longer be changed.');
        }

        $quotation->update(['status' => $validated['status']]);

        return back()->with('success', "Quotation marked as {$validated['status']}.");
    }

    protected function syncPassengers(Quotation $quotation, array $passengers): void
    {
        $quotation->passengers()->delete();

        foreach ($passengers as $p) {
            if (empty($p['name'])) {
                continue;
            }
            $quotation->passengers()->create([
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
        $packages = Package::where('is_active', true)->with('services')->orderBy('name')->get();

        // Pre-built server-side (rather than inline in Blade with @json()
        // wrapping nested closures, which trips Blade's directive parser)
        // so "Apply Package" can pre-fill quotation lines client-side
        // without a round trip.
        $packageServiceMap = $packages->mapWithKeys(fn ($package) => [
            $package->id => $package->services->map(fn ($service) => [
                'service_type' => $service->service_type,
                'description' => $service->label(),
                'qty' => $service->qty,
            ])->values(),
        ]);

        return [
            'customers' => Customer::where('is_active', true)->orderBy('name')->get(),
            'packages' => $packages,
            'packageServiceMap' => $packageServiceMap,
            'suppliers' => Supplier::where('is_active', true)->orderBy('name')->get(),
            'chargeTemplates' => ChargeTemplate::where('is_active', true)->get(),
            'serviceTypes' => self::SERVICE_TYPES,
        ];
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'quotation_date' => 'required|date',
            'valid_until' => 'nullable|date|after_or_equal:quotation_date',
            'customer_id' => 'required|exists:customers,id',
            'package_id' => 'nullable|exists:packages,id',
            'visit_type' => 'nullable|string|max:100',
            'remarks' => 'nullable|string|max:2000',

            'passengers' => 'nullable|array',
            'passengers.*.name' => 'nullable|string|max:255',
            'passengers.*.passport_no_nic' => 'nullable|string|max:100',
            'passengers.*.pax_type' => 'nullable|in:adult,child,infant',
            'passengers.*.nationality' => 'nullable|string|max:100',
            'passengers.*.dob' => 'nullable|date',

            'lines' => 'nullable|array',
            'lines.*.service_type' => 'nullable|in:' . implode(',', self::SERVICE_TYPES),
            'lines.*.supplier_id' => 'nullable|exists:suppliers,id',
            'lines.*.charge_template_id' => 'nullable|exists:charge_templates,id',
            'lines.*.description' => 'nullable|string|max:255',
            'lines.*.currency' => 'nullable|string|max:3',
            'lines.*.exchange_rate' => 'nullable|numeric|min:0',
            'lines.*.receivable_f_amount' => 'nullable|numeric|min:0',
            'lines.*.payable_f_amount' => 'nullable|numeric|min:0',
        ]);
    }

    protected function nextNumber(): string
    {
        $year = date('y');
        $last = Quotation::withTrashed()->where('quotation_no', 'like', "QTN-{$year}-%")->count();

        return sprintf('QTN-%s-%04d', $year, $last + 1);
    }
}
