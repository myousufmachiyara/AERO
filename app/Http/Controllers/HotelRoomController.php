<?php

namespace App\Http\Controllers;

use App\Models\Hotel;
use App\Models\HotelRoom;
use App\Models\RoomView;
use Illuminate\Http\Request;

class HotelRoomController extends Controller
{
    public function index(Request $request)
    {
        $query = HotelRoom::with(['hotel', 'roomView'])->latest();

        if ($request->filled('hotel_id') && $request->hotel_id !== 'all') {
            $query->where('hotel_id', $request->hotel_id);
        }

        $rooms = $query->get();
        $hotels = Hotel::orderBy('name')->get();
        $roomViews = RoomView::where('is_active', true)->orderBy('name')->get();

        return view('hotel-rooms.index', compact('rooms', 'hotels', 'roomViews'));
    }

    public function create()
    {
        return redirect()->route('hotel_rooms.index');
    }

    public function store(Request $request)
    {
        HotelRoom::create($this->validated($request));

        return redirect()->route('hotel_rooms.index')->with('success', 'Room type added successfully.');
    }

    public function show(string $id)
    {
        // Managed entirely through the index modal — no separate detail page.
        return redirect()->route('hotel_rooms.index');
    }

    public function edit(string $id)
    {
        return response()->json(HotelRoom::findOrFail($id));
    }

    public function update(Request $request, string $id)
    {
        $room = HotelRoom::findOrFail($id);
        $room->update($this->validated($request));

        return redirect()->route('hotel_rooms.index')->with('success', 'Room type updated successfully.');
    }

    public function destroy(string $id)
    {
        HotelRoom::findOrFail($id)->delete();

        return redirect()->route('hotel_rooms.index')->with('success', 'Room type deleted successfully.');
    }

    public function print()
    {
        return redirect()->route('hotel_rooms.index');
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'hotel_id'      => 'required|exists:hotels,id',
            'room_type'     => 'required|string|max:255',
            'room_view_id'  => 'nullable|exists:room_views,id',
            'capacity'      => 'required|integer|min:1',
            'default_rate'  => 'required|numeric|min:0',
            'currency'      => 'required|string|max:3',
            'is_active'     => 'nullable|boolean',
        ]);
    }
}
