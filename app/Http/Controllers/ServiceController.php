<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Models\Supplier;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    private const CATEGORIES = ['ticket', 'hotel', 'transport', 'visa', 'other'];

    public function index()
    {
        $services = Service::with('defaultSupplier')->orderBy('name')->get();
        $suppliers = Supplier::where('is_active', true)->orderBy('name')->get();

        return view('services.index', ['services' => $services, 'suppliers' => $suppliers, 'categories' => self::CATEGORIES]);
    }

    public function create()
    {
        return redirect()->route('services.index');
    }

    public function store(Request $request)
    {
        Service::create($this->validated($request));

        return redirect()->route('services.index')->with('success', 'Service added successfully.');
    }

    public function show(string $id)
    {
        return redirect()->route('services.index');
    }

    public function edit(string $id)
    {
        return response()->json(Service::findOrFail($id));
    }

    public function update(Request $request, string $id)
    {
        Service::findOrFail($id)->update($this->validated($request));

        return redirect()->route('services.index')->with('success', 'Service updated successfully.');
    }

    public function destroy(string $id)
    {
        Service::findOrFail($id)->delete();

        return redirect()->route('services.index')->with('success', 'Service deleted successfully.');
    }

    public function print()
    {
        return redirect()->route('services.index');
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'name'                => 'required|string|max:255',
            'category'            => 'required|in:' . implode(',', self::CATEGORIES),
            'default_supplier_id' => 'nullable|exists:suppliers,id',
            'default_unit'        => 'nullable|string|max:50',
            'description'         => 'nullable|string|max:1000',
            'is_active'           => 'nullable|boolean',
        ]);
    }
}
