<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Support\TravelReportData;
use Illuminate\Http\Request;

/** Reports → Travel: Sales — Sales Register, Income by Service Type, Customer Wise. */
class TravelSalesReportController extends Controller
{
    public function index(Request $request)
    {
        [$from, $to] = TravelReportData::period($request);
        $tab = in_array($request->input('tab'), ['register', 'by_service', 'by_customer'], true) ? $request->input('tab') : 'register';
        $invoiceType = in_array($request->input('invoice_type'), ['sale', 'tour'], true) ? $request->input('invoice_type') : null;
        $customerId = $request->integer('customer_id') ?: null;

        $register = TravelReportData::invoices($from, $to, $invoiceType, $customerId);

        // Income by service type: Tour Invoice lines + Ticket Sale Invoice tickets (as "ticket").
        $rows = TravelReportData::tourLines($from, $to)->map(fn ($l) => [
            'type' => $l->service_type,
            'recv' => (float) $l->receivable_l_amount,
            'pay' => (float) $l->payable_l_amount,
            'income' => (float) $l->income_l_amount,
        ])->concat(TravelReportData::ticketSaleLines($from, $to)->map(fn ($l) => [
            'type' => 'ticket',
            'recv' => $l->effectiveReceivable(),
            'pay' => $l->effectiveSupplierPayable() - $l->effectiveNetCommission(),
            'income' => $l->effectiveIncome(),
        ]));
        $order = ['ticket', 'hotel', 'transport', 'visa', 'other'];
        $byService = $rows->groupBy('type')
            ->map(fn ($g, $type) => (object) [
                'service_type' => $type,
                'lines_count' => $g->count(),
                'total_receivable' => round($g->sum('recv'), 2),
                'total_payable' => round($g->sum('pay'), 2),
                'total_income' => round($g->sum('income'), 2),
            ])
            ->sortBy(fn ($r) => array_search($r->service_type, $order, true))
            ->values();

        $byCustomer = TravelReportData::invoices($from, $to)
            ->groupBy('customer_id')
            ->map(fn ($g) => (object) [
                'customer' => $g->first()->customer->name ?? '—',
                'invoices_count' => $g->count(),
                'total_receivable' => round($g->sum('total_receivable'), 2),
                'total_income' => round($g->sum('total_income'), 2),
                'total_outstanding' => round($g->sum(fn ($i) => $i->outstandingAmount()), 2),
            ])
            ->sortByDesc('total_receivable')
            ->values();

        return view('reports.travel_sales', [
            'tab' => $tab,
            'from' => $from,
            'to' => $to,
            'invoiceType' => $invoiceType,
            'customerId' => $customerId,
            'customers' => Customer::orderBy('name')->get(),
            'register' => $register,
            'byService' => $byService,
            'byCustomer' => $byCustomer,
        ]);
    }
}
