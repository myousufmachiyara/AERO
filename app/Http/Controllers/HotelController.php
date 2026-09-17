<?php

namespace App\Http\Controllers;

use App\Models\Hotel;
use App\Models\Supplier;
use Illuminate\Http\Request;

class HotelController extends Controller
{
    public function index(Request $request)
    {
        $query = Hotel::with('supplier')->withCount('rooms')->latest();

        if ($request->filled('search')) {
            $term = $request->search;
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")->orWhere('city', 'like', "%{$term}%");
            });
        }

        $hotels = $query->paginate(25)->withQueryString();

        return view('hotels.index', compact('hotels'));
    }

    public function create()
    {
        $suppliers = Supplier::where('is_active', true)->orderBy('name')->get();

        return view('hotels.create', compact('suppliers'));
    }

    public function store(Request $request)
    {
        $validated = $this->validated($request);
        Hotel::create($validated);

        return redirect()->route('hotels.index')->with('success', 'Hotel created successfully.');
    }

    public function show(string $id)
    {
        $hotel = Hotel::with(['supplier', 'rooms.roomView'])->findOrFail($id);

        return view('hotels.show', compact('hotel'));
    }

    public function edit(string $id)
    {
        $hotel = Hotel::findOrFail($id);
        $suppliers = Supplier::where('is_active', true)->orderBy('name')->get();

        return view('hotels.edit', compact('hotel', 'suppliers'));
    }

    public function update(Request $request, string $id)
    {
        $hotel = Hotel::findOrFail($id);
        $hotel->update($this->validated($request));

        return redirect()->route('hotels.index')->with('success', 'Hotel updated successfully.');
    }

    public function destroy(string $id)
    {
        Hotel::findOrFail($id)->delete();

        return redirect()->route('hotels.index')->with('success', 'Hotel deleted successfully.');
    }

    public function print(string $id)
    {
        $hotel = Hotel::with(['supplier', 'rooms.roomView'])->findOrFail($id);

        return view('hotels.print', compact('hotel'));
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'name'        => 'required|string|max:255',
            'city'        => 'nullable|string|max:255',
            'star_rating' => 'nullable|integer|min:1|max:7',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'address'     => 'nullable|string|max:255',
            'contact_no'  => 'nullable|string|max:50',
            'remarks'     => 'nullable|string|max:1000',
            'is_active'   => 'nullable|boolean',
        ]);
    }
}
