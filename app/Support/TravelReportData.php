<?php

namespace App\Support;

use App\Models\InvoicePayment;
use App\Models\ServiceLine;
use App\Models\Supplier;
use App\Models\TicketSaleInvoice;
use App\Models\TicketSaleInvoiceLine;
use App\Models\TravelInvoice;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Shared data for the three Travel report pages (Vendor, Sales, Accounting).
 * Invoices = Tour Invoices (not cancelled) + Ticket Sale Invoices; both expose
 * invoice_type / total_receivable / total_payable / total_income /
 * outstandingAmount() so the report views render them the same way.
 */
class TravelReportData
{
    /** [from, to] from the request; default = first of this month → today. */
    public static function period(Request $request): array
    {
        $from = $request->input('from_date') ?: now()->startOfMonth()->toDateString();
        $to = $request->input('to_date') ?: now()->toDateString();

        return $from > $to ? [$to, $from] : [$from, $to];
    }

    public static function invoices(string $from, string $to, ?string $type = null, ?int $customerId = null): Collection
    {
        $tour = collect();
        if ($type !== 'sale') {
            $tour = TravelInvoice::with('customer', 'payments')
                ->where('status', '!=', 'cancelled')
                ->whereBetween('invoice_date', [$from, $to])
                ->when($type === 'tour', fn ($q) => $q->where('invoice_type', TravelInvoice::TYPE_TOUR))
                ->when($customerId, fn ($q) => $q->where('customer_id', $customerId))
                ->get();
        }

        $tickets = collect();
        if ($type !== 'tour') {
            $tickets = TicketSaleInvoice::with('customer', 'lines')
                ->whereBetween('invoice_date', [$from, $to])
                ->when($customerId, fn ($q) => $q->where('customer_id', $customerId))
                ->get();
        }

        return $tour->concat($tickets)
            ->sortBy(fn ($i) => $i->invoice_date->format('Y-m-d') . '|' . $i->invoice_no)
            ->values();
    }

    /** Tour Invoice service lines (not cancelled) in the period. */
    public static function tourLines(string $from, string $to): Collection
    {
        $ids = TravelInvoice::where('status', '!=', 'cancelled')
            ->whereBetween('invoice_date', [$from, $to])
            ->pluck('id');

        return ServiceLine::with('supplier')
            ->where('linkable_type', TravelInvoice::class)
            ->whereIn('linkable_id', $ids)
            ->get();
    }

    /**
     * Tour Invoice lines payable to a supplier — as posted: a ticket with an
     * airline is owed to the airline, not to the line's supplier.
     */
    public static function supplierLines(string $from, string $to): Collection
    {
        return self::tourLines($from, $to)->load('ticketDetail')
            ->filter(fn ($l) => $l->supplier_id && !($l->service_type === 'ticket' && $l->ticketDetail?->airline_id))
            ->values();
    }

    /** Ticket Sale Invoice lines in the period. */
    public static function ticketSaleLines(string $from, string $to): Collection
    {
        return TicketSaleInvoiceLine::whereHas('invoice', fn ($q) => $q->whereBetween('invoice_date', [$from, $to]))->get();
    }

    /**
     * Per supplier: service lines and payable in the period (Tour Invoices),
     * paid in the period (supplier payments), outstanding = payable − paid.
     */
    public static function supplierBalances(string $from, string $to): Collection
    {
        $lines = self::supplierLines($from, $to)->groupBy('supplier_id');
        $paid = InvoicePayment::where('direction', 'payment')
            ->whereBetween('payment_date', [$from, $to])
            ->selectRaw('supplier_id, SUM(amount) as total')
            ->groupBy('supplier_id')
            ->pluck('total', 'supplier_id');

        return Supplier::orderBy('name')->get()->map(function (Supplier $s) use ($lines, $paid) {
            $own = $lines->get($s->id, collect());
            $payable = round((float) $own->sum('payable_l_amount'), 2);
            $totalPaid = round((float) ($paid[$s->id] ?? 0), 2);

            return (object) [
                'supplier' => $s,
                'lines_count' => $own->count(),
                'total_payable' => $payable,
                'total_paid' => $totalPaid,
                'outstanding' => round($payable - $totalPaid, 2),
            ];
        });
    }
}
