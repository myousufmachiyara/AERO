<?php

namespace App\Http\Controllers;

use App\Models\Package;
use App\Models\HotelRoom;
use App\Models\Vehicle;
use App\Models\VisaType;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PackageController extends Controller
{
    private const CATEGORIES = ['umrah', 'hajj', 'tour', 'custom'];
    private const SERVICE_TYPES = ['ticket', 'hotel', 'transport', 'visa', 'other'];

    public function index(Request $request)
    {
        $query = Package::withCount('services')->latest();

        if ($request->filled('category') && $request->category !== 'all') {
            $query->where('category', $request->category);
        }

        $packages = $query->get();

        return view('packages.index', ['packages' => $packages, 'categories' => self::CATEGORIES]);
    }

    public function create()
    {
        return view('packages.create', $this->referenceData());
    }

    public function store(Request $request)
    {
        $validated = $this->validated($request);

        DB::transaction(function () use ($validated) {
            $package = Package::create([
                ...collect($validated)->except('services')->all(),
                'created_by' => auth()->id(),
            ]);

            foreach ($validated['services'] ?? [] as $service) {
                if (empty($service['service_type'])) {
                    continue;
                }
                $package->services()->create($service);
            }
        });

        return redirect()->route('packages.index')->with('success', 'Package created successfully.');
    }

    public function show(string $id)
    {
        $package = Package::with('services.hotelRoom.hotel', 'services.vehicle', 'services.visaType', 'services.service')->findOrFail($id);

        return view('packages.show', compact('package'));
    }

    public function edit(string $id)
    {
        $package = Package::with('services')->findOrFail($id);

        return view('packages.edit', array_merge($this->referenceData(), ['package' => $package]));
    }

    public function update(Request $request, string $id)
    {
        $package = Package::findOrFail($id);
        $validated = $this->validated($request);

        DB::transaction(function () use ($package, $validated) {
            $package->update(collect($validated)->except('services')->all());

            $package->services()->delete();
            foreach ($validated['services'] ?? [] as $service) {
                if (empty($service['service_type'])) {
                    continue;
                }
                $package->services()->create($service);
            }
        });

        return redirect()->route('packages.index')->with('success', 'Package updated successfully.');
    }

    public function destroy(string $id)
    {
        Package::findOrFail($id)->delete();

        return redirect()->route('packages.index')->with('success', 'Package deleted successfully.');
    }

    public function print(string $id)
    {
        $package = Package::with('services.hotelRoom.hotel', 'services.vehicle', 'services.visaType', 'services.service')->findOrFail($id);

        return view('packages.print', compact('package'));
    }

    protected function referenceData(): array
    {
        return [
            'categories' => self::CATEGORIES,
            'serviceTypes' => self::SERVICE_TYPES,
            'hotelRooms' => HotelRoom::with('hotel')->where('is_active', true)->get(),
            'vehicles' => Vehicle::where('is_active', true)->orderBy('name')->get(),
            'visaTypes' => VisaType::where('is_active', true)->orderBy('name')->get(),
            'services' => Service::where('is_active', true)->orderBy('name')->get(),
        ];
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'required|in:' . implode(',', self::CATEGORIES),
            'duration_days' => 'nullable|integer|min:1',
            'base_price' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|max:3',
            'inclusions' => 'nullable|string|max:2000',
            'is_active' => 'nullable|boolean',
            'services' => 'nullable|array',
            'services.*.service_type' => 'nullable|in:' . implode(',', self::SERVICE_TYPES),
            'services.*.hotel_room_id' => 'nullable|exists:hotel_rooms,id',
            'services.*.vehicle_id' => 'nullable|exists:vehicles,id',
            'services.*.visa_type_id' => 'nullable|exists:visa_types,id',
            'services.*.service_id' => 'nullable|exists:services,id',
            'services.*.qty' => 'nullable|integer|min:1',
            'services.*.notes' => 'nullable|string|max:255',
        ]);
    }
}
