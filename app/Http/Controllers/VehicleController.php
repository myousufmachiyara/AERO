<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Models\Vehicle;
use Illuminate\Http\Request;

class VehicleController extends Controller
{
    public function index()
    {
        $vehicles = Vehicle::with('supplier')->orderBy('name')->get();
        $transporters = Supplier::where('type', 'transport')->where('is_active', true)->orderBy('name')->get();

        return view('vehicles.index', compact('vehicles', 'transporters'));
    }

    public function create()
    {
        return redirect()->route('vehicles.index');
    }

    public function store(Request $request)
    {
        Vehicle::create($this->validated($request));

        return redirect()->route('vehicles.index')->with('success', 'Vehicle added successfully.');
    }

    public function show(string $id)
    {
        return redirect()->route('vehicles.index');
    }

    public function edit(string $id)
    {
        return response()->json(Vehicle::findOrFail($id));
    }

    public function update(Request $request, string $id)
    {
        Vehicle::findOrFail($id)->update($this->validated($request));

        return redirect()->route('vehicles.index')->with('success', 'Vehicle updated successfully.');
    }

    public function destroy(string $id)
    {
        Vehicle::findOrFail($id)->delete();

        return redirect()->route('vehicles.index')->with('success', 'Vehicle deleted successfully.');
    }

    public function print()
    {
        return redirect()->route('vehicles.index');
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'name'        => 'required|string|max:255',
            'type'        => 'nullable|string|max:255',
            'capacity'    => 'nullable|integer|min:1',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'is_active'   => 'nullable|boolean',
        ]);
    }
}
