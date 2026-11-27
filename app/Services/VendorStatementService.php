<?php

namespace App\Services;

use App\Models\InvoicePayment;
use App\Models\ServiceLine;
use App\Models\Supplier;
use App\Models\TicketInvoiceLedgerEntry;
use App\Models\TicketSaleInvoiceLine;
use App\Models\TravelInvoice;
use App\Models\TravelInvoiceLedgerEntry;
use App\Models\Voucher;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Vendor (supplier) Account Statement — the supplier side of the Customer
 * Statement (Accounts Reports → Vendor Statement tab and its PDF).
 *
 *   Purchases — Ticket / Hotel / Transport / Visa / Other bought from the
 *               supplier on Ticket Sale and Tour Invoices, at what we owe
 *   Credits   — refunded and voided tickets: what the supplier gives back
 *   Vouchers  — payments to the supplier (debit) and any other entries
 *   Summary   — Opening B/F + Purchases − Credits − Payments + Other credits
 *               = Net Payable (what we owe the supplier)
 *
 * Rules (used identically before the From date for Opening B/F):
 *   - Ticket Sale Invoice tickets of this supplier (pending or posted) at
 *     the supplier net: fare + tax + APT − (commission − WHT), on the invoice
 *     date. A refund / void then appears under Credits on its refund /
 *     void date: original net − the charges the supplier keeps.
 *   - Tour Invoice lines of this supplier (draft or confirmed; cancelled
 *     left out) at their Payable in PKR, less any commission charge lines.
 *   - Vouchers: every voucher on the supplier's ledger account EXCEPT those
 *     an invoice posted itself, which are the purchase rows above.
 * Once everything is posted, Net Payable equals the supplier's ledger
 * balance (credit).
 */
class VendorStatementService
{
    public const SERVICES = ['hotel' => 'Hotel', 'transport' => 'Transport', 'visa' => 'Visa', 'other' => 'Other Services'];

    public function build(Supplier $supplier, string $from, string $to): array
    {
        $from = Carbon::parse($from)->toDateString();
        $to = Carbon::parse($to)->toDateString();
        $dayBefore = Carbon::parse($from)->subDay()->toDateString();

        $tickets = $this->markFirstOfInvoice($this->ticketRows($supplier, $from, $to));
        $tour = $this->tourLines($supplier, $from, $to);
        $services = [];
        foreach (self::SERVICES as $key => $label) {
            $services[$key] = $this->markFirstOfInvoice($tour->get($key, collect())->map(fn ($l) => $this->serviceRow($l))->values());
        }
        $credits = $this->creditRows($supplier, $from, $to);
        $vouchers = $this->voucherRows($supplier, $from, $to);

        $purchases = round($tickets->sum('net') + collect($services)->sum(fn ($rows) => $rows->sum('net')), 2);
        $creditTotal = round($credits->sum('net'), 2);
        $paid = round($vouchers->sum('debit'), 2);
        $otherCredits = round($vouchers->sum('credit'), 2);
        $opening = $this->openingBalance($supplier, $dayBefore);

        return [
            'party' => $supplier,
            'supplier' => $supplier,
            'from' => $from,
            'to' => $to,
            'printed_at' => now(),
            'tickets' => $tickets,
            'services' => $services,
            'credits' => $credits,
            'vouchers' => $vouchers,
            'summary' => [
                'opening' => $opening,
                'purchases' => $purchases,
                'credits' => $creditTotal,
                'payments' => $paid,
                'other_credits' => $otherCredits,
                'net' => round($opening + $purchases - $creditTotal - $paid + $otherCredits, 2),
            ],
        ];
    }

    /** What we owed the supplier at the end of $asOf: ledger opening + every row dated on or before it. */
    public function openingBalance(Supplier $supplier, string $asOf): float
    {
        $account = $supplier->account;
        $ledgerOpening = $account ? ((float) $account->payables - (float) $account->receivables) : (float) ($supplier->opening_balance ?? 0);

        $epoch = '1900-01-01';
        $tickets = $this->ticketRows($supplier, $epoch, $asOf)->sum('net');
        $tour = collect($this->tourLines($supplier, $epoch, $asOf)->all())->except('ticket')->flatten(1)
            ->sum(fn ($l) => $this->serviceRow($l)['net']);
        $credits = $this->creditRows($supplier, $epoch, $asOf)->sum('net');
        $vouchers = $this->voucherRows($supplier, $epoch, $asOf);

        return round($ledgerOpening + $tickets + $tour - $credits - $vouchers->sum('debit') + $vouchers->sum('credit'), 2);
    }

    // ── Purchases: tickets ────────────────────────────────────────────

    protected function ticketRows(Supplier $supplier, string $from, string $to): Collection
    {
        $sale = TicketSaleInvoiceLine::query()
            ->where('supplier_id', $supplier->id)
            ->whereHas('invoice', fn ($q) => $q->whereDate('invoice_date', '>=', $from)->whereDate('invoice_date', '<=', $to))
            ->with('invoice.customer')
            ->get()
            ->map(fn (TicketSaleInvoiceLine $l) => [
                'date' => $l->invoice->invoice_date,
                'invoice' => $l->invoice->invoice_no,
                'ticket_no' => $l->ticket_no,
                'passenger' => $l->pax_name,
                'sector' => $l->citiesLabel(),
                'customer' => $l->invoice->customer->name ?? '—',
                'fare' => (float) $l->fare_amount,
                'taxes' => round((float) $l->tax_amount + (float) $l->apt_charges, 2),
                'commission' => round((float) $l->commission_amount - (float) $l->wht_amount, 2),
                'net' => $l->supplierNet(),
                'status' => $l->status,
                'sort' => $l->invoice->invoice_date->format('Y-m-d') . '|A' . $l->invoice->invoice_no . '|' . str_pad((string) $l->id, 10, '0', STR_PAD_LEFT),
            ]);

        $tour = $this->tourLines($supplier, $from, $to)->get('ticket', collect())->map(function (ServiceLine $l) {
            $d = $l->ticketDetail;
            $priced = $d && $d->hasTicketSalePricing();

            return [
                'date' => $l->linkable->invoice_date,
                'invoice' => $l->linkable->invoice_no,
                'ticket_no' => $d->ticket_no ?? null,
                'passenger' => $l->description,
                'sector' => $d ? ($d->citiesLabel() ?: $d->sector) : null,
                'customer' => $l->linkable->customer->name ?? '—',
                'fare' => $priced ? (float) $d->fare_amount : (float) $l->payable_l_amount,
                'taxes' => $priced ? round((float) $d->tax_amount + (float) $d->apt_charges, 2) : 0.0,
                'commission' => $priced ? round((float) $d->commission_amount - (float) $d->wht_amount, 2) : 0.0,
                'net' => (float) $l->payable_l_amount,
                'status' => 'active',
                'sort' => $l->linkable->invoice_date->format('Y-m-d') . '|B' . $l->linkable->invoice_no . '|' . str_pad((string) $l->id, 10, '0', STR_PAD_LEFT),
            ];
        });

        return $sale->concat($tour)->sortBy('sort')->values();
    }

    // ── Purchases: hotel / transport / visa / other ────────────────────

    protected function tourLines(Supplier $supplier, string $from, string $to): Collection
    {
        $invoiceIds = TravelInvoice::where('status', '!=', 'cancelled')
            ->whereDate('invoice_date', '>=', $from)->whereDate('invoice_date', '<=', $to)
            ->pluck('id');
        if ($invoiceIds->isEmpty()) {
            return collect();
        }

        return ServiceLine::where('linkable_type', TravelInvoice::class)
            ->whereIn('linkable_id', $invoiceIds)
            ->where('supplier_id', $supplier->id)
            ->with([
                'linkable.customer', 'ticketDetail', 'charges',
                'hotelDetail.hotel', 'hotelDetail.rooms.hotelRoom',
                'transportDetail.vehicle', 'transportDetail.vehicles.vehicle',
                'visaDetail.visaType', 'otherDetail.service',
            ])
            ->get()
            ->sortBy(fn ($l) => $l->linkable->invoice_date->format('Y-m-d') . '|' . $l->linkable->invoice_no . '|' . str_pad((string) $l->sort_order, 5, '0', STR_PAD_LEFT) . '|' . $l->id)
            ->groupBy(fn ($l) => array_key_exists($l->service_type, self::SERVICES) || $l->service_type === 'ticket' ? $l->service_type : 'other');
    }

    /** One hotel / transport / visa / other line: payable less any commission charge lines. */
    protected function serviceRow(ServiceLine $l): array
    {
        $charges = round((float) $l->income_l_amount - ((float) $l->receivable_l_amount - (float) $l->payable_l_amount), 2);

        $details = match ($l->service_type) {
            'hotel' => trim(($l->hotelDetail->hotel->name ?? 'Hotel')
                . ($l->hotelDetail?->check_in ? ' · ' . $l->hotelDetail->check_in->format('d/m/Y') . ' → ' . optional($l->hotelDetail->check_out)->format('d/m/Y') : '')
                . ' · ' . (int) ($l->hotelDetail->nights ?? 0) . ' night(s)'
                . ($l->hotelDetail && $l->hotelDetail->rooms->isNotEmpty() ? ' · ' . $l->hotelDetail->rooms->map(fn ($r) => $r->qty . ' × ' . ($r->hotelRoom->room_type ?? 'Room'))->implode(', ') : '')),
            'transport' => trim(($l->transportDetail && $l->transportDetail->vehicles->isNotEmpty()
                    ? $l->transportDetail->vehicles->map(fn ($v) => trim(($v->vehicle->name ?? '') . ' ' . ($v->sector ?? '')))->filter()->implode(', ')
                    : trim(($l->transportDetail->vehicle->name ?? '') . ' ' . ($l->transportDetail->sector ?? '')))
                . ($l->description ? ' — ' . $l->description : '')),
            'visa' => trim(($l->visaDetail->visaType->name ?? 'Visa') . ($l->description ? ' — ' . $l->description : '')),
            default => trim(($l->otherDetail->service->name ?? 'Service') . ($l->description ? ' — ' . $l->description : '')),
        };

        return [
            'date' => $l->linkable->invoice_date,
            'invoice' => $l->linkable->invoice_no,
            'customer' => $l->linkable->customer->name ?? '—',
            'booking' => $l->hotelDetail->booking_name ?? $l->transportDetail->booking_name ?? $l->otherDetail->booking_name ?? null,
            'details' => $details,
            'payable' => (float) $l->payable_l_amount,
            'charges' => $charges,
            'net' => round((float) $l->payable_l_amount - $charges, 2),
        ];
    }

    // ── Credits: refunded / voided tickets ─────────────────────────────

    protected function creditRows(Supplier $supplier, string $from, string $to): Collection
    {
        return TicketSaleInvoiceLine::query()
            ->where('supplier_id', $supplier->id)
            ->whereIn('status', [TicketSaleInvoiceLine::STATUS_REFUNDED, TicketSaleInvoiceLine::STATUS_VOIDED])
            ->with('invoice.customer')
            ->get()
            ->map(function (TicketSaleInvoiceLine $l) {
                $refund = $l->status === TicketSaleInvoiceLine::STATUS_REFUNDED;
                $date = $refund ? ($l->refund_adjustment_date ?? $l->refund_date ?? $l->invoice->invoice_date) : ($l->void_date ?? $l->invoice->invoice_date);
                $kept = $l->effectiveSupplierPayable();

                return [
                    'kind' => $refund ? 'refund' : 'void',
                    'date' => Carbon::parse($date),
                    'date_label' => Carbon::parse($date)->format('d/m/Y'),
                    'invoice' => ($refund ? 'RF' : 'VD') . $l->id . '(' . $l->invoice->invoice_no . ')',
                    'ticket_no' => $l->ticket_no,
                    'passenger' => $l->pax_name,
                    'customer' => $l->invoice->customer->name ?? '—',
                    'original' => $l->supplierNet(),
                    'kept' => $kept,                                      // charges the supplier keeps
                    'net' => round($l->supplierNet() - $kept, 2),         // credit from the supplier
                ];
            })
            ->filter(fn ($r) => $r['date']->toDateString() >= $from && $r['date']->toDateString() <= $to)
            ->sortBy(fn ($r) => $r['date']->format('Y-m-d') . '|' . $r['invoice'])
            ->values();
    }

    // ── Vouchers ───────────────────────────────────────────────────────

    protected function voucherRows(Supplier $supplier, string $from, string $to): Collection
    {
        $accountId = $supplier->chart_of_account_id;
        if (!$accountId) {
            return collect();
        }

        $invoicePosted = TicketInvoiceLedgerEntry::pluck('voucher_id')
            ->merge(TravelInvoiceLedgerEntry::pluck('voucher_id'))
            ->filter()->all();

        $vouchers = Voucher::query()
            ->whereDate('date', '>=', $from)->whereDate('date', '<=', $to)
            ->where(fn ($q) => $q->where('ac_dr_sid', $accountId)->orWhere('ac_cr_sid', $accountId))
            ->when($invoicePosted, fn ($q) => $q->whereNotIn('id', $invoicePosted))
            ->orderBy('date')->orderBy('id')
            ->get();
        $payments = InvoicePayment::whereIn('voucher_id', $vouchers->pluck('id'))->get()->keyBy('voucher_id');

        return $vouchers->map(function (Voucher $v) use ($accountId, $payments) {
            $isDebit = (int) $v->ac_dr_sid === (int) $accountId;
            $prefix = match ($v->voucher_type) {
                'receipt' => 'RV', 'payment' => 'PV', 'journal' => 'JV',
                default => strtoupper(substr((string) $v->voucher_type, 0, 1)) . 'V',
            };
            $payment = $payments->get($v->id);

            return [
                'date' => Carbon::parse($v->date),
                'voucher_no' => $prefix . $v->id,
                'reference' => $v->reference,
                'description' => $v->description ?: $v->remarks,
                'cheque_no' => $payment && $payment->payment_mode === 'cheque' ? $payment->reference : null,
                'debit' => $isDebit ? (float) $v->amount : 0.0,   // paid to the supplier
                'credit' => $isDebit ? 0.0 : (float) $v->amount,  // other amounts owed to them
            ];
        });
    }

    /** Date + invoice no printed only on the first row of each invoice. */
    protected function markFirstOfInvoice(Collection $rows): Collection
    {
        $prevInvoice = null;
        $prevDate = null;

        return $rows->values()->map(function ($r) use (&$prevInvoice, &$prevDate) {
            $date = Carbon::parse($r['date'])->format('d/m/Y');
            $r['show_invoice'] = $r['invoice'] !== $prevInvoice;
            $r['show_date'] = $r['show_invoice'] && $date !== $prevDate;
            $r['date_label'] = $date;
            $prevInvoice = $r['invoice'];
            if ($r['show_invoice']) {
                $prevDate = $date;
            }

            return $r;
        });
    }
}
