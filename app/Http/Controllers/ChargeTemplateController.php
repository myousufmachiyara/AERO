<?php

namespace App\Http\Controllers;

use App\Models\ChargeTemplate;
use App\Models\ChargeType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChargeTemplateController extends Controller
{
    private const CATEGORIES = ['ticket', 'hotel', 'transport', 'visa', 'other'];

    public function index(Request $request)
    {
        $query = ChargeTemplate::withCount('items')->latest('effective_date');

        if ($request->filled('service_category') && $request->service_category !== 'all') {
            $query->where('service_category', $request->service_category);
        }

        $templates = $query->get();

        return view('charge-templates.index', ['templates' => $templates, 'categories' => self::CATEGORIES]);
    }

    public function create()
    {
        $chargeTypes = ChargeType::where('is_active', true)->orderBy('name')->get();

        return view('charge-templates.create', ['chargeTypes' => $chargeTypes, 'categories' => self::CATEGORIES]);
    }

    public function store(Request $request)
    {
        $validated = $this->validated($request);

        DB::transaction(function () use ($validated) {
            $template = ChargeTemplate::create([
                ...collect($validated)->except('items')->all(),
                'created_by' => auth()->id(),
            ]);

            foreach ($validated['items'] ?? [] as $item) {
                $template->items()->create($item);
            }
        });

        return redirect()->route('charge_templates.index')->with('success', 'Charge template created successfully.');
    }

    public function show(string $id)
    {
        $template = ChargeTemplate::with('items.chargeType')->findOrFail($id);

        return view('charge-templates.show', compact('template'));
    }

    public function edit(string $id)
    {
        $template = ChargeTemplate::with('items')->findOrFail($id);
        $chargeTypes = ChargeType::where('is_active', true)->orderBy('name')->get();

        return view('charge-templates.edit', ['template' => $template, 'chargeTypes' => $chargeTypes, 'categories' => self::CATEGORIES]);
    }

    public function update(Request $request, string $id)
    {
        $template = ChargeTemplate::findOrFail($id);
        $validated = $this->validated($request);

        DB::transaction(function () use ($template, $validated) {
            $template->update(collect($validated)->except('items')->all());

            $template->items()->delete();
            foreach ($validated['items'] ?? [] as $item) {
                $template->items()->create($item);
            }
        });

        return redirect()->route('charge_templates.index')->with('success', 'Charge template updated successfully.');
    }

    public function destroy(string $id)
    {
        ChargeTemplate::findOrFail($id)->delete();

        return redirect()->route('charge_templates.index')->with('success', 'Charge template deleted successfully.');
    }

    public function print(string $id)
    {
        $template = ChargeTemplate::with('items.chargeType')->findOrFail($id);

        return view('charge-templates.print', compact('template'));
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'name'                    => 'required|string|max:255',
            'service_category'        => 'required|in:' . implode(',', self::CATEGORIES),
            'effective_date'          => 'required|date',
            'default_currency'        => 'required|string|max:3',
            'default_exchange_rate'   => 'required|numeric|min:0',
            'is_active'               => 'nullable|boolean',
            'items'                   => 'nullable|array',
            'items.*.charge_type_id'  => 'required_with:items|exists:charge_types,id',
            'items.*.value'           => 'required_with:items|numeric|min:0',
        ]);
    }
}
