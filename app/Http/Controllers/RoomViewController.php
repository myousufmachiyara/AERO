<?php

namespace App\Http\Controllers;

use App\Models\RoomView;
use Illuminate\Http\Request;

class RoomViewController extends Controller
{
    public function index()
    {
        $roomViews = RoomView::orderBy('name')->get();

        return view('room-views.index', compact('roomViews'));
    }

    public function create()
    {
        // Managed entirely through the index modal — no separate create page.
        return redirect()->route('room_views.index');
    }

    public function store(Request $request)
    {
        $request->validate(['name' => 'required|string|max:255|unique:room_views,name']);
        RoomView::create($request->only('name'));

        return redirect()->route('room_views.index')->with('success', 'Room view added successfully.');
    }

    public function show(string $id)
    {
        return redirect()->route('room_views.index');
    }

    public function edit(string $id)
    {
        return response()->json(RoomView::findOrFail($id));
    }

    public function update(Request $request, string $id)
    {
        $roomView = RoomView::findOrFail($id);
        $request->validate(['name' => 'required|string|max:255|unique:room_views,name,' . $roomView->id]);
        $roomView->update($request->only('name'));

        return redirect()->route('room_views.index')->with('success', 'Room view updated successfully.');
    }

    public function destroy(string $id)
    {
        RoomView::findOrFail($id)->delete();

        return redirect()->route('room_views.index')->with('success', 'Room view deleted successfully.');
    }

    public function print()
    {
        return redirect()->route('room_views.index');
    }
}
