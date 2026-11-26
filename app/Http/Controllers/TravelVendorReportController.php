<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Models\VendorComplaint;
use App\Support\TravelReportData;
use Illuminate\Http\Request;

/** Reports → Travel: Vendor — Vendor Summary, Complaint Log, Spend by Service Type. */
class TravelVendorReportController extends Controller
{
    public function index(Request $request)
    {
        [$from, $to] = TravelReportData::period($request);
        $tab = in_array($request->input('tab'), ['summary', 'complaints', 'spend'], true) ? $request->input('tab') : 'summary';
        $supplierId = $request->integer('supplier_id') ?: null;

        $complaintsInPeriod = VendorComplaint::whereBetween('complaint_date', [$from, $to])
            ->where('status', '!=', 'dismissed')
            ->selectRaw('supplier_id, COUNT(*) as total')
            ->groupBy('supplier_id')
            ->pluck('total', 'supplier_id');

        $summary = TravelReportData::supplierBalances($from, $to)
            ->map(function ($row) use ($complaintsInPeriod) {
                $row->complaints_count = (int) ($complaintsInPeriod[$row->supplier->id] ?? 0);
                $row->complaint_rate = $row->supplier->complaintRatePercent();
                $row->is_flagged = (bool) $row->supplier->is_flagged;

                return $row;
            })
            ->filter(fn ($r) => $r->lines_count > 0 || $r->total_paid > 0 || $r->complaints_count > 0 || $r->is_flagged)
            ->values();

        $complaints = VendorComplaint::with('supplier', 'customer')
            ->whereBetween('complaint_date', [$from, $to])
            ->when($supplierId, fn ($q) => $q->where('supplier_id', $supplierId))
            ->orderByDesc('complaint_date')
            ->get();

        $spendByType = TravelReportData::supplierLines($from, $to)
            ->groupBy(fn ($l) => $l->supplier_id . '|' . $l->service_type)
            ->map(fn ($group) => (object) [
                'supplier' => $group->first()->supplier->name ?? '—',
                'service_type' => $group->first()->service_type,
                'lines_count' => $group->count(),
                'total_payable' => round((float) $group->sum('payable_l_amount'), 2),
            ])
            ->sortBy(fn ($r) => $r->supplier . '|' . $r->service_type)
            ->values();

        return view('reports.travel_vendor', [
            'tab' => $tab,
            'from' => $from,
            'to' => $to,
            'supplierId' => $supplierId,
            'suppliers' => Supplier::orderBy('name')->get(),
            'summary' => $summary,
            'complaints' => $complaints,
            'spendByType' => $spendByType,
        ]);
    }
}
