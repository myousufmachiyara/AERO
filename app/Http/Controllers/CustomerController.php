<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Services\PartyLedgerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    private const TYPES = ['individual', 'corporate', 'agent'];

    public function __construct(protected PartyLedgerService $ledger)
    {
    }

    public function index(Request $request)
    {
        $query = Customer::with('account')->latest();

        if ($request->filled('search')) {
            $term = $request->search;
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                  ->orWhere('code', 'like', "%{$term}%")
                  ->orWhere('cnic_passport', 'like', "%{$term}%");
            });
        }

        $customers = $query->paginate(25)->withQueryString();

        return view('customers.index', compact('customers'));
    }

    public function create()
    {
        return view('customers.create', ['types' => self::TYPES]);
    }

    public function store(Request $request)
    {
        $validated = $this->validated($request);

        DB::beginTransaction();
        try {
            $validated['code'] = $this->nextCode();
            $validated['created_by'] = auth()->id();
            $validated['updated_by'] = auth()->id();

            $customer = Customer::create($validated);

            $account = $this->ledger->syncCustomer($customer);
            $customer->update(['chart_of_account_id' => $account->id]);

            DB::commit();

            return redirect()->route('customers.index')->with('success', 'Customer created successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('[Customer] store failed: ' . $e->getMessage());

            return back()->withInput()->with('error', 'Could not create customer: ' . $e->getMessage());
        }
    }

    public function show(string $id)
    {
        $customer = Customer::with('account')->findOrFail($id);

        return view('customers.show', compact('customer'));
    }

    public function edit(string $id)
    {
        $customer = Customer::findOrFail($id);

        return view('customers.edit', ['customer' => $customer, 'types' => self::TYPES]);
    }

    public function update(Request $request, string $id)
    {
        $customer = Customer::findOrFail($id);
        $validated = $this->validated($request, $customer->id);

        DB::beginTransaction();
        try {
            $validated['updated_by'] = auth()->id();
            $customer->update($validated);
            $this->ledger->syncCustomer($customer);

            DB::commit();

            return redirect()->route('customers.index')->with('success', 'Customer updated successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('[Customer] update failed: ' . $e->getMessage());

            return back()->withInput()->with('error', 'Could not update customer: ' . $e->getMessage());
        }
    }

    public function destroy(string $id)
    {
        $customer = Customer::findOrFail($id);
        $customer->delete();

        return redirect()->route('customers.index')->with('success', 'Customer deleted successfully.');
    }

    public function print(string $id)
    {
        $customer = Customer::with('account')->findOrFail($id);

        return view('customers.print', compact('customer'));
    }

    protected function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name'            => 'required|string|max:255',
            'customer_type'   => ['required', Rule::in(self::TYPES)],
            'contact_person'  => 'nullable|string|max:255',
            'phone'           => 'nullable|string|max:50',
            'email'           => 'nullable|email|max:255',
            'address'         => 'nullable|string|max:255',
            'cnic_passport'   => 'nullable|string|max:100',
            'credit_limit'    => 'nullable|numeric|min:0',
            'credit_days'     => 'nullable|integer|min:0',
            'opening_balance' => 'nullable|numeric|min:0',
            'remarks'         => 'nullable|string|max:1000',
            'is_active'       => 'nullable|boolean',
        ]);
    }

    protected function nextCode(): string
    {
        $last = Customer::withTrashed()->orderByDesc('id')->value('id') ?? 0;

        return 'CUS-' . str_pad((string) ($last + 1), 4, '0', STR_PAD_LEFT);
    }
}
