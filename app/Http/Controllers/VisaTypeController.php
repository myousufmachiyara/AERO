<?php

namespace App\Http\Controllers;

use App\Models\VisaType;
use Illuminate\Http\Request;

class VisaTypeController extends Controller
{
    public function index()
    {
        $visaTypes = VisaType::orderBy('name')->get();

        return view('visa-types.index', compact('visaTypes'));
    }

    public function create()
    {
        return redirect()->route('visa_types.index');
    }

    public function store(Request $request)
    {
        $request->validate(['name' => 'required|string|max:255|unique:visa_types,name']);
        VisaType::create($request->only('name'));

        return redirect()->route('visa_types.index')->with('success', 'Visa type added successfully.');
    }

    public function show(string $id)
    {
        return redirect()->route('visa_types.index');
    }

    public function edit(string $id)
    {
        return response()->json(VisaType::findOrFail($id));
    }

    public function update(Request $request, string $id)
    {
        $visaType = VisaType::findOrFail($id);
        $request->validate(['name' => 'required|string|max:255|unique:visa_types,name,' . $visaType->id]);
        $visaType->update($request->only('name'));

        return redirect()->route('visa_types.index')->with('success', 'Visa type updated successfully.');
    }

    public function destroy(string $id)
    {
        VisaType::findOrFail($id)->delete();

        return redirect()->route('visa_types.index')->with('success', 'Visa type deleted successfully.');
    }

    public function print()
    {
        return redirect()->route('visa_types.index');
    }
}
