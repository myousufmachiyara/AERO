<?php

namespace App\Http\Controllers;

use App\Models\ChargeType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ChargeTypeController extends Controller
{
    public function index()
    {
        $chargeTypes = ChargeType::orderBy('name')->get();

        return view('charge-types.index', compact('chargeTypes'));
    }

    public function create()
    {
        return redirect()->route('charge_types.index');
    }

    public function store(Request $request)
    {
        ChargeType::create($this->validated($request));

        return redirect()->route('charge_types.index')->with('success', 'Charge type added successfully.');
    }

    public function show(string $id)
    {
        return redirect()->route('charge_types.index');
    }

    public function edit(string $id)
    {
        return response()->json(ChargeType::findOrFail($id));
    }

    public function update(Request $request, string $id)
    {
        ChargeType::findOrFail($id)->update($this->validated($request, $id));

        return redirect()->route('charge_types.index')->with('success', 'Charge type updated successfully.');
    }

    public function destroy(string $id)
    {
        ChargeType::findOrFail($id)->delete();

        return redirect()->route('charge_types.index')->with('success', 'Charge type deleted successfully.');
    }

    public function print()
    {
        return redirect()->route('charge_types.index');
    }

    protected function validated(Request $request, ?string $ignoreId = null): array
    {
        return $request->validate([
            'name'              => 'required|string|max:255',
            'code'              => ['required', 'string', 'max:20', Rule::unique('charge_types', 'code')->ignore($ignoreId)],
            'calculation_type'  => 'required|in:percentage,fixed',
            'default_value'     => 'required|numeric|min:0',
            'is_active'         => 'nullable|boolean',
        ]);
    }
}
