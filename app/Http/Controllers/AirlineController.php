<?php

namespace App\Http\Controllers;

use App\Models\Airline;
use App\Services\PartyLedgerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * Master of Airlines (client feedback on Ticket Sale Invoice) — same
 * ledger-linking pattern as SupplierController, but a much smaller field
 * set: just a name and the 3-digit numeric code used to auto-detect the
 * airline from a ticket number.
 */
class AirlineController extends Controller
{
    public function __construct(protected PartyLedgerService $ledger)
    {
    }

    public function index(Request $request)
    {
        $query = Airline::with('account')->orderBy('name');

        if ($request->filled('search')) {
            $term = $request->search;
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                  ->orWhere('numeric_code', 'like', "%{$term}%");
            });
        }

        return view('airlines.index', ['airlines' => $query->paginate(25)->withQueryString()]);
    }

    public function create()
    {
        return view('airlines.create');
    }

    public function store(Request $request)
    {
        $validated = $this->validated($request);

        DB::beginTransaction();
        try {
            $validated['created_by'] = auth()->id();
            $validated['updated_by'] = auth()->id();

            $airline = Airline::create($validated);

            $account = $this->ledger->syncAirline($airline);
            $airline->update(['chart_of_account_id' => $account->id]);

            DB::commit();

            return redirect()->route('airlines.index')->with('success', 'Airline created successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('[Airline] store failed: ' . $e->getMessage());

            return back()->withInput()->with('error', 'Could not create airline: ' . $e->getMessage());
        }
    }

    public function show(string $id)
    {
        $airline = Airline::with('account')->findOrFail($id);

        return view('airlines.show', compact('airline'));
    }

    public function edit(string $id)
    {
        $airline = Airline::findOrFail($id);

        return view('airlines.edit', compact('airline'));
    }

    public function update(Request $request, string $id)
    {
        $airline = Airline::findOrFail($id);
        $validated = $this->validated($request, $airline->id);

        DB::beginTransaction();
        try {
            $validated['updated_by'] = auth()->id();
            $airline->update($validated);
            $this->ledger->syncAirline($airline);

            DB::commit();

            return redirect()->route('airlines.index')->with('success', 'Airline updated successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('[Airline] update failed: ' . $e->getMessage());

            return back()->withInput()->with('error', 'Could not update airline: ' . $e->getMessage());
        }
    }

    public function destroy(string $id)
    {
        Airline::findOrFail($id)->delete();

        return redirect()->route('airlines.index')->with('success', 'Airline deleted successfully.');
    }

    public function print(string $id)
    {
        $airline = Airline::with('account')->findOrFail($id);

        return view('airlines.print', compact('airline'));
    }

    protected function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'numeric_code' => [
                'required', 'digits:3',
                Rule::unique('airlines', 'numeric_code')->ignore($ignoreId),
            ],
            'remarks' => 'nullable|string|max:1000',
            'is_active' => 'nullable|boolean',
        ]);
    }
}