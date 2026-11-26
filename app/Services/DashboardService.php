<?php

namespace App\Services;

use App\Models\Airline;
use App\Models\ChartOfAccounts;
use App\Models\Customer;
use App\Models\InvoiceHotelDetail;
use App\Models\InvoicePayment;
use App\Models\InvoiceTicketDetail;
use App\Models\Supplier;
use App\Models\TicketSaleInvoice;
use App\Models\TicketSaleInvoiceLine;
use App\Models\TravelInvoice;
use App\Models\VendorComplaint;
use App\Models\Voucher;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Figures for the home dashboard. Invoices = Ticket Sale Invoices + Tour
 * Invoices that are not cancelled (same rules as the Travel Sales report);
 * balances come from the ledgers (opening balance + vouchers).
 */
class DashboardService
{
    public const SERVICES = ['ticket' => 'Tickets', 'hotel' => 'Hotel', 'transport' => 'Transport', 'visa' => 'Visa', 'other' => 'Other'];

    public function build(?Carbon $today = null): array
    {
        $today = ($today ?? now())->copy()->startOfDay();
        $monthStart = $today->copy()->startOfMonth();
        $lastMonthStart = $monthStart->copy()->subMonth();
        $lastMonthSameDay = $lastMonthStart->copy()->addDays(min($today->day, $lastMonthStart->daysInMonth) - 1);

        $month = $this->salesBetween($monthStart, $today);
        $lastMonth = $this->salesBetween($lastMonthStart, $lastMonthSameDay);
        $todaySales = $this->salesBetween($today, $today);

        $customerBalances = $this->partyBalances(Customer::query()->whereNotNull('chart_of_account_id')->get(['id', 'name', 'chart_of_account_id']));
        $supplierBalances = $this->partyBalances(Supplier::query()->whereNotNull('chart_of_account_id')->get(['id', 'name', 'chart_of_account_id']));
        $airlineBalances = $this->partyBalances(Airline::query()->whereNotNull('chart_of_account_id')->get(['id', 'name', 'chart_of_account_id']));

        $owedToUs = $customerBalances->filter(fn ($r) => $r['balance'] > 0.005);
        $weOwe = $supplierBalances->concat($airlineBalances)->filter(fn ($r) => $r['balance'] < -0.005);

        $cashIds = ChartOfAccounts::whereHas('subHeadOfAccount', fn ($q) => $q->whereIn('name', config('travel.cash_bank_subhead_names', ['Cash', 'Bank'])))->pluck('id');

        return [
            'today' => $today,
            'monthStart' => $monthStart,
            'kpi' => [
                'sales_month' => $month['receivable'],
                'sales_today' => $todaySales['receivable'],
                'sales_change' => $this->change($month['receivable'], $lastMonth['receivable']),
                'income_month' => $month['income'],
                'income_margin' => $month['receivable'] > 0 ? round($month['income'] / $month['receivable'] * 100, 1) : 0,
                'income_change' => $this->change($month['income'], $lastMonth['income']),
                'invoices_month' => $month['count'],
                'receivable' => round($owedToUs->sum('balance'), 2),
                'receivable_count' => $owedToUs->count(),
                'payable' => round(-$weOwe->sum('balance'), 2),
                'payable_count' => $weOwe->count(),
                'cash' => round($this->accountsBalance($cashIds), 2),
                'received_month' => round((float) InvoicePayment::where('direction', 'receipt')->whereDate('payment_date', '>=', $monthStart->toDateString())->whereDate('payment_date', '<=', $today->toDateString())->sum('amount'), 2),
                'paid_month' => round((float) InvoicePayment::where('direction', 'payment')->whereDate('payment_date', '>=', $monthStart->toDateString())->whereDate('payment_date', '<=', $today->toDateString())->sum('amount'), 2),
            ],
            'trend' => $this->trend($today, 6),
            'byService' => $month['byService'],
            'attention' => $this->attention(),
            'departures' => $this->departures($today, 7),
            'topCustomers' => $owedToUs->sortByDesc('balance')->take(5)->values(),
            'recentInvoices' => $this->recentInvoices(8),
            'recentPayments' => InvoicePayment::with('customer', 'supplier', 'travelInvoice')->latest('payment_date')->latest('id')->take(6)->get(),
        ];
    }

    /** Receivable / income / invoice count and income per service for invoices dated in [from, to]. */
    public function salesBetween(Carbon $from, Carbon $to): array
    {
        [$f, $t] = [$from->toDateString(), $to->toDateString()];
        $byService = array_fill_keys(array_keys(self::SERVICES), ['receivable' => 0.0, 'income' => 0.0]);

        $tickets = TicketSaleInvoice::with('lines')->whereDate('invoice_date', '>=', $f)->whereDate('invoice_date', '<=', $t)->get();
        foreach ($tickets as $inv) {
            $byService['ticket']['receivable'] += $inv->total_receivable;
            $byService['ticket']['income'] += $inv->total_income;
        }

        $tourIds = TravelInvoice::where('status', '!=', 'cancelled')->whereDate('invoice_date', '>=', $f)->whereDate('invoice_date', '<=', $t)->pluck('id');
        $lines = DB::table('service_lines')
            ->where('linkable_type', TravelInvoice::class)
            ->whereIn('linkable_id', $tourIds)
            ->selectRaw('service_type, SUM(receivable_l_amount) as recv, SUM(income_l_amount) as inc')
            ->groupBy('service_type')->get();
        foreach ($lines as $row) {
            $key = array_key_exists($row->service_type, $byService) ? $row->service_type : 'other';
            $byService[$key]['receivable'] += (float) $row->recv;
            $byService[$key]['income'] += (float) $row->inc;
        }

        return [
            'count' => $tickets->count() + $tourIds->count(),
            'receivable' => round(array_sum(array_column($byService, 'receivable')), 2),
            'income' => round(array_sum(array_column($byService, 'income')), 2),
            'byService' => array_map(fn ($r) => ['receivable' => round($r['receivable'], 2), 'income' => round($r['income'], 2)], $byService),
        ];
    }

    /** Sales and income per month for the last $months months (oldest first), current month to date. */
    public function trend(Carbon $today, int $months): array
    {
        $out = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $start = $today->copy()->startOfMonth()->subMonths($i);
            $end = $i === 0 ? $today->copy() : $start->copy()->endOfMonth();
            $s = $this->salesBetween($start, $end);
            $out[] = ['label' => $start->format('M Y'), 'sales' => $s['receivable'], 'income' => $s['income']];
        }

        return $out;
    }

    /** Work that is waiting on someone. */
    public function attention(): array
    {
        $tourWithBalance = TravelInvoice::with('payments')->where('status', 'confirmed')->get()
            ->filter(fn ($i) => $i->outstandingAmount() > 0.005);

        return [
            ['label' => 'Ticket invoices not posted', 'count' => TicketSaleInvoice::where('status', TicketSaleInvoice::STATUS_PENDING)->count(),
                'route' => route('ticket_invoices.index', ['status' => 'pending']), 'icon' => 'fa-ticket-alt', 'tone' => 'warning', 'perm' => 'ticket_invoices.index'],
            ['label' => 'Tour invoices in draft', 'count' => TravelInvoice::where('invoice_type', TravelInvoice::TYPE_TOUR)->where('status', 'draft')->count(),
                'route' => route('tour_invoices.index', ['status' => 'draft']), 'icon' => 'fa-suitcase-rolling', 'tone' => 'warning', 'perm' => 'tour_invoices.index'],
            ['label' => 'Confirmed tours with balance due', 'count' => $tourWithBalance->count(),
                'route' => route('tour_invoices.index', ['status' => 'confirmed']), 'icon' => 'fa-hand-holding-usd', 'tone' => 'danger', 'perm' => 'tour_invoices.index'],
            ['label' => 'Open vendor complaints', 'count' => VendorComplaint::where('status', 'open')->count(),
                'route' => route('vendor_complaints.index'), 'icon' => 'fa-exclamation-circle', 'tone' => 'danger', 'perm' => 'vendor_complaints.index'],
            ['label' => 'Flagged suppliers', 'count' => Supplier::where('is_flagged', true)->count(),
                'route' => route('suppliers.index'), 'icon' => 'fa-flag', 'tone' => 'dark', 'perm' => 'suppliers.index'],
        ];
    }

    /** Flights departing and hotel check-ins within the next $days days (today included). */
    public function departures(Carbon $today, int $days): Collection
    {
        [$f, $t] = [$today->toDateString(), $today->copy()->addDays($days)->toDateString()];

        $tickets = TicketSaleInvoiceLine::with('invoice.customer')
            ->where('status', TicketSaleInvoiceLine::STATUS_ACTIVE)
            ->whereDate('leg1_departure_date', '>=', $f)->whereDate('leg1_departure_date', '<=', $t)->get()
            ->map(fn ($l) => [
                'date' => $l->leg1_departure_date, 'time' => $l->leg1_departure_time, 'kind' => 'Flight', 'icon' => 'fa-plane-departure',
                'who' => $l->pax_name, 'what' => $l->citiesLabel(), 'customer' => $l->invoice->customer->name ?? '—',
                'invoice' => $l->invoice->invoice_no ?? '', 'url' => $l->invoice ? route('ticket_invoices.show', $l->invoice->id) : '#',
            ]);

        $activeTour = TravelInvoice::where('status', '!=', 'cancelled')->pluck('id');

        $tourTickets = InvoiceTicketDetail::with('serviceLine.linkable.customer')
            ->whereDate('leg1_departure_date', '>=', $f)->whereDate('leg1_departure_date', '<=', $t)->get()
            ->filter(fn ($d) => $d->serviceLine && $activeTour->contains($d->serviceLine->linkable_id) && $d->serviceLine->linkable_type === TravelInvoice::class)
            ->map(fn ($d) => [
                'date' => $d->leg1_departure_date, 'time' => $d->leg1_departure_time, 'kind' => 'Flight', 'icon' => 'fa-plane-departure',
                'who' => $d->serviceLine->description, 'what' => $d->citiesLabel() ?: $d->sector, 'customer' => $d->serviceLine->linkable->customer->name ?? '—',
                'invoice' => $d->serviceLine->linkable->invoice_no, 'url' => route('tour_invoices.show', $d->serviceLine->linkable_id),
            ]);

        $hotels = InvoiceHotelDetail::with('hotel', 'serviceLine.linkable.customer')
            ->whereDate('check_in', '>=', $f)->whereDate('check_in', '<=', $t)->get()
            ->filter(fn ($d) => $d->serviceLine && $activeTour->contains($d->serviceLine->linkable_id) && $d->serviceLine->linkable_type === TravelInvoice::class)
            ->map(fn ($d) => [
                'date' => $d->check_in, 'time' => null, 'kind' => 'Check-in', 'icon' => 'fa-hotel',
                'who' => $d->booking_name ?: ($d->serviceLine->linkable->customer->name ?? '—'),
                'what' => ($d->hotel->name ?? 'Hotel') . ' · ' . $d->nights . ' night(s)', 'customer' => $d->serviceLine->linkable->customer->name ?? '—',
                'invoice' => $d->serviceLine->linkable->invoice_no, 'url' => route('tour_invoices.show', $d->serviceLine->linkable_id),
            ]);

        return $tickets->concat($tourTickets)->concat($hotels)
            ->sortBy(fn ($r) => $r['date']->format('Y-m-d') . ' ' . ($r['time'] ?? '99:99'))
            ->values()->take(8);
    }

    /** Latest Ticket Sale + Tour invoices, newest first. */
    public function recentInvoices(int $n): Collection
    {
        $tickets = TicketSaleInvoice::with('customer', 'lines')->latest('invoice_date')->latest('id')->take($n)->get()
            ->map(fn ($i) => [
                'no' => $i->invoice_no, 'date' => $i->invoice_date, 'type' => 'Ticket', 'customer' => $i->customer->name ?? '—',
                'amount' => $i->total_receivable, 'status' => $i->status, 'url' => route('ticket_invoices.show', $i->id), 'sort' => $i->created_at,
            ]);
        $tours = TravelInvoice::with('customer')->where('invoice_type', TravelInvoice::TYPE_TOUR)->latest('invoice_date')->latest('id')->take($n)->get()
            ->map(fn ($i) => [
                'no' => $i->invoice_no, 'date' => $i->invoice_date, 'type' => 'Tour', 'customer' => $i->customer->name ?? '—',
                'amount' => (float) $i->total_receivable, 'status' => $i->status, 'url' => route('tour_invoices.show', $i->id), 'sort' => $i->created_at,
            ]);

        return $tickets->concat($tours)
            ->sortByDesc(fn ($r) => $r['date']->format('Y-m-d') . ' ' . optional($r['sort'])->format('His'))
            ->values()->take($n);
    }

    /** [ ['id','name','balance'] ] — balance = opening Dr − opening Cr + Σ Dr − Σ Cr (positive = they owe us). */
    protected function partyBalances(Collection $parties): Collection
    {
        if ($parties->isEmpty()) {
            return collect();
        }
        $ids = $parties->pluck('chart_of_account_id')->unique()->values();
        $balances = $this->balancesFor($ids);

        return $parties->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'balance' => round($balances[$p->chart_of_account_id] ?? 0, 2)]);
    }

    protected function accountsBalance(Collection $ids): float
    {
        return array_sum($this->balancesFor($ids));
    }

    /** account id => balance (Dr positive), opening balance included. */
    protected function balancesFor(Collection $ids): array
    {
        if ($ids->isEmpty()) {
            return [];
        }
        $out = ChartOfAccounts::whereIn('id', $ids)->get(['id', 'receivables', 'payables'])
            ->mapWithKeys(fn ($a) => [$a->id => (float) $a->receivables - (float) $a->payables])->all();
        foreach (Voucher::whereIn('ac_dr_sid', $ids)->selectRaw('ac_dr_sid as id, SUM(amount) as s')->groupBy('ac_dr_sid')->get() as $r) {
            $out[$r->id] = ($out[$r->id] ?? 0) + (float) $r->s;
        }
        foreach (Voucher::whereIn('ac_cr_sid', $ids)->selectRaw('ac_cr_sid as id, SUM(amount) as s')->groupBy('ac_cr_sid')->get() as $r) {
            $out[$r->id] = ($out[$r->id] ?? 0) - (float) $r->s;
        }

        return $out;
    }

    /** % change vs. the same days of last month; null when there is nothing to compare with. */
    protected function change(float $now, float $before): ?float
    {
        return $before > 0.005 ? round(($now - $before) / $before * 100, 1) : null;
    }
}
