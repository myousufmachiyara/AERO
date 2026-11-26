<?php

namespace App\Http\Controllers;

use App\Models\InvoicePayment;
use App\Support\TravelReportData;
use Illuminate\Http\Request;

/** Reports → Travel: Accounting — Outstanding Receivables, Outstanding Payables, Payments Register. */
class TravelAccountsReportController extends Controller
{
    public function index(Request $request)
    {
        [$from, $to] = TravelReportData::period($request);
        $tab = in_array($request->input('tab'), ['receivables', 'payables', 'payments_register'], true) ? $request->input('tab') : 'receivables';

        $receivables = TravelReportData::invoices($from, $to)
            ->map(fn ($inv) => (object) ['invoice' => $inv, 'outstanding' => round($inv->outstandingAmount(), 2)])
            ->filter(fn ($r) => $r->outstanding > 0.001)
            ->values();

        $payables = TravelReportData::supplierBalances($from, $to)
            ->filter(fn ($r) => abs($r->outstanding) > 0.001)
            ->values();

        $paymentsRegister = InvoicePayment::with('customer', 'supplier', 'travelInvoice')
            ->whereBetween('payment_date', [$from, $to])
            ->orderBy('payment_date')->orderBy('id')
            ->get();

        return view('reports.travel_accounts', [
            'tab' => $tab,
            'from' => $from,
            'to' => $to,
            'receivables' => $receivables,
            'payables' => $payables,
            'paymentsRegister' => $paymentsRegister,
        ]);
    }
}
