<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Services\PartyLedgerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class SupplierController extends Controller
{
    private const TYPES = ['airline', 'hotel', 'visa_agency', 'transport', 'other'];

    public function __construct(protected PartyLedgerService $ledger)
    {
    }

    public function index(Request $request)
    {
        $query = Supplier::with('account')->latest();

        if ($request->filled('type') && $request->type !== 'all') {
            $query->where('type', $request->type);
        }
        if ($request->filled('search')) {
            $term = $request->search;
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                  ->orWhere('code', 'like', "%{$term}%")
                  ->orWhere('contact_person', 'like', "%{$term}%");
            });
        }

        $suppliers = $query->paginate(25)->withQueryString();

        return view('suppliers.index', compact('suppliers'));
    }

    public function create()
    {
        return view('suppliers.create', ['types' => self::TYPES]);
    }

    public function store(Request $request)
    {
        $validated = $this->validated($request);

        DB::beginTransaction();
        try {
            $validated['code'] = $this->nextCode();
            $validated['created_by'] = auth()->id();
            $validated['updated_by'] = auth()->id();

            $supplier = Supplier::create($validated);

            $account = $this->ledger->syncSupplier($supplier);
            $supplier->update(['chart_of_account_id' => $account->id]);

            DB::commit();

            return redirect()->route('suppliers.index')->with('success', 'Supplier created successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('[Supplier] store failed: ' . $e->getMessage());

            return back()->withInput()->with('error', 'Could not create supplier: ' . $e->getMessage());
        }
    }

    public function show(string $id)
    {
        $supplier = Supplier::with(['account', 'complaints' => fn ($q) => $q->latest('complaint_date')])->findOrFail($id);

        return view('suppliers.show', compact('supplier'));
    }

    public function edit(string $id)
    {
        $supplier = Supplier::findOrFail($id);

        return view('suppliers.edit', ['supplier' => $supplier, 'types' => self::TYPES]);
    }

    public function update(Request $request, string $id)
    {
        $supplier = Supplier::findOrFail($id);
        $validated = $this->validated($request, $supplier->id);

        DB::beginTransaction();
        try {
            $validated['updated_by'] = auth()->id();
            $supplier->update($validated);
            $this->ledger->syncSupplier($supplier);

            DB::commit();

            return redirect()->route('suppliers.index')->with('success', 'Supplier updated successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('[Supplier] update failed: ' . $e->getMessage());

            return back()->withInput()->with('error', 'Could not update supplier: ' . $e->getMessage());
        }
    }

    public function destroy(string $id)
    {
        $supplier = Supplier::findOrFail($id);
        $supplier->delete(); // soft delete only — the linked COA/ledger history is kept intact

        return redirect()->route('suppliers.index')->with('success', 'Supplier deleted successfully.');
    }

    public function print(string $id)
    {
        $supplier = Supplier::with('account')->findOrFail($id);

        return view('suppliers.print', compact('supplier'));
    }

    protected function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name'           => 'required|string|max:255',
            'type'           => ['required', Rule::in(self::TYPES)],
            'contact_person' => 'nullable|string|max:255',
            'phone'          => 'nullable|string|max:50',
            'email'          => 'nullable|email|max:255',
            'address'        => 'nullable|string|max:255',
            'license_no'     => 'nullable|string|max:100',
            'ntn'            => 'nullable|string|max:100',
            'credit_limit'   => 'nullable|numeric|min:0',
            'credit_days'    => 'nullable|integer|min:0',
            'opening_balance'=> 'nullable|numeric|min:0',
            'remarks'        => 'nullable|string|max:1000',
            'is_active'      => 'nullable|boolean',
        ]);
    }

    protected function nextCode(): string
    {
        $last = Supplier::withTrashed()->orderByDesc('id')->value('id') ?? 0;

        return 'SUP-' . str_pad((string) ($last + 1), 4, '0', STR_PAD_LEFT);
    }
}
