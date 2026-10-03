<?php

namespace App\Http\Controllers;

use App\Models\Currency;
use Illuminate\Http\Request;

class CurrencyController extends Controller
{
    public function index()
    {
        $currencies = Currency::orderBy('code')->get();

        return view('currencies.index', compact('currencies'));
    }

    public function create()
    {
        // Managed entirely through the index modal — no separate create page.
        return redirect()->route('currencies.index');
    }

    public function store(Request $request)
    {
        $request->validate([
            'code' => 'required|string|max:3|unique:currencies,code',
            'name' => 'required|string|max:255',
        ]);
        Currency::create([
            'code' => strtoupper($request->code),
            'name' => $request->name,
        ]);

        return redirect()->route('currencies.index')->with('success', 'Currency added successfully.');
    }

    public function show(string $id)
    {
        return redirect()->route('currencies.index');
    }

    public function edit(string $id)
    {
        return response()->json(Currency::findOrFail($id));
    }

    public function update(Request $request, string $id)
    {
        $currency = Currency::findOrFail($id);
        $request->validate([
            'code' => 'required|string|max:3|unique:currencies,code,' . $currency->id,
            'name' => 'required|string|max:255',
        ]);
        $currency->update([
            'code' => strtoupper($request->code),
            'name' => $request->name,
        ]);

        return redirect()->route('currencies.index')->with('success', 'Currency updated successfully.');
    }

    public function destroy(string $id)
    {
        Currency::findOrFail($id)->delete();

        return redirect()->route('currencies.index')->with('success', 'Currency deleted successfully.');
    }

    public function print()
    {
        return redirect()->route('currencies.index');
    }
}