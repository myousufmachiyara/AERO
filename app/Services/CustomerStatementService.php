<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\InvoiceTicketDetail;
use App\Models\ServiceLine;
use App\Models\TicketInvoiceLedgerEntry;
use App\Models\TicketSaleInvoice;
use App\Models\TicketSaleInvoiceLine;
use App\Models\TravelInvoice;
use App\Models\Voucher;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Customer Account Statement (Accounts Reports → Customer Statement tab and
 * its PDF). One source of truth for both, laid out like the client's
 * sample statement:
 *
 *   Sale Invoices   — Ticket / Hotel / Transport / Visa / Other Booking,
 *                     from Ticket Sale Invoices and Tour Invoices
 *   Refund Invoices — refunded and voided tickets
 *   Vouchers        — receipts (credit) / payments (debit) on the
 *                     customer's ledger account
 *   Summary         — Opening B/F + Sales − Refunds − Receipts + Payments
 *
 * Rules (applied identically before the From date to build Opening B/F, so
 * the statement always adds up):
 *   - Ticket Sale Invoices: every ticket counts as a sale at its full amount
 *     on the invoice date (pending or posted); a refund / void then appears
 *     under Refund Invoices on its refund (adjustment) / void date at the
 *     amount given back, so Sale − Refund = what the customer still owes.
 *   - Tour Invoices: every service line of a draft or confirmed invoice
 *     (cancelled ones are left out) at its Receivable in PKR.
 *   - Vouchers: every voucher on the customer's account EXCEPT the ones an
 *     invoice posted itself (Ticket Sale Post / refund / void entries, Tour
 *     Invoice Confirm entries) — those are already shown as invoices above.
 *   - Opening balance of the customer's ledger account is the starting point.
 */
class CustomerStatementService
{
    public function build(Customer $customer, string $from, string $to): array
    {
        $from = Carbon::parse($from)->toDateString();
        $to = Carbon::parse($to)->toDateString();
        $dayBefore = Carbon::parse($from)->subDay()->toDateString();

        $tickets = $this->ticketRows($customer, $from, $to);
        $refunds = $this->refundRows($customer, $from, $to);
        $tourLines = $this->tourLines($customer, $from, $to);

        $sections = [
            'ticket' => $tickets,
            'hotel' => $tourLines->get('hotel', collect())->map(fn ($l) => $this->hotelRow($l))->values(),
            'transport' => $tourLines->get('transport', collect())->map(fn ($l) => $this->transportRow($l))->values(),
            'visa' => $tourLines->get('visa', collect())->map(fn ($l) => $this->visaRow($l))->values(),
            'other' => $tourLines->get('other', collect())->map(fn ($l) => $this->otherRow($l))->values(),
        ];
        foreach ($sections as $k => $rows) {
            $sections[$k] = $this->markFirstOfInvoice($rows);
        }

        $vouchers = $this->voucherRows($customer, $from, $to);

        $salesTotal = round(collect($sections)->sum(fn ($rows) => $rows->sum('net')), 2);
        $refundTotal = round($refunds->sum('net'), 2);
        $receipts = round($vouchers->sum('credit'), 2);
        $payments = round($vouchers->sum('debit'), 2);
        $opening = $this->openingBalance($customer, $dayBefore);

        return [
            'customer' => $customer,
            'from' => $from,
            'to' => $to,
            'printed_at' => now(),
            'sections' => $sections,
            'ticket_counts' => [
                'sale' => $tickets->where('voided', false)->count(),
                'void' => $tickets->where('voided', true)->count(),
                'total' => $tickets->count(),
            ],
            'refunds' => $this->markFirstOfInvoice($refunds),
            'refund_counts' => [
                'refund' => $refunds->where('kind', 'refund')->count(),
                'void' => $refunds->where('kind', 'void')->count(),
                'total' => $refunds->count(),
            ],
            'vouchers' => $vouchers,
            'summary' => [
                'opening' => $opening,
                'sales' => $salesTotal,
                'refunds' => $refundTotal,
                'receipts' => $receipts,
                'payments' => $payments,
                'net' => round($opening + $salesTotal - $refundTotal - $receipts + $payments, 2),
            ],
        ];
    }

    // ── Opening balance ────────────────────────────────────────────────

    /** Ledger opening + every sale/refund/receipt/payment dated on or before $asOf (same rules as the statement). */
    public function openingBalance(Customer $customer, string $asOf): float
    {
        $account = $customer->account;
        $ledgerOpening = $account ? ((float) $account->receivables - (float) $account->payables) : (float) ($customer->opening_balance ?? 0);

        $epoch = '1900-01-01';
        $tickets = $this->ticketRows($customer, $epoch, $asOf)->sum('net');
        // Tour-invoice tickets are already inside ticketRows(); count the other services here.
        $tour = collect($this->tourLines($customer, $epoch, $asOf)->all())->except('ticket')->flatten(1)->sum(fn ($l) => (float) $l->receivable_l_amount);
        $refunds = $this->refundRows($customer, $epoch, $asOf)->sum('net');
        $vouchers = $this->voucherRows($customer, $epoch, $asOf);

        return round($ledgerOpening + $tickets + $tour - $refunds - $vouchers->sum('credit') + $vouchers->sum('debit'), 2);
    }

    // ── Sale: tickets ──────────────────────────────────────────────────

    /** Ticket Sale Invoice lines + Tour Invoice ticket lines, as ticket rows. */
    protected function ticketRows(Customer $customer, string $from, string $to): Collection
    {
        $saleRows = TicketSaleInvoiceLine::query()
            ->whereHas('invoice', fn ($q) => $q->where('customer_id', $customer->id)->whereBetween('invoice_date', [$from, $to]))
            ->with('invoice')
            ->get()
            ->map(fn (TicketSaleInvoiceLine $l) => [
                'date' => $l->invoice->invoice_date,
                'invoice' => $l->invoice->invoice_no,
                'ticket_no' => $l->ticket_no,
                'passenger' => $l->pax_name,
                'sector' => $l->citiesLabel(),
                'fare' => (float) $l->fare_amount,
                'taxes' => round((float) $l->tax_amount + (float) $l->apt_charges, 2),
                'sp' => (float) $l->psf_amount,
                'kb' => (float) $l->discount_amount,
                'net' => (float) $l->total_amount,
                'voided' => $l->status === TicketSaleInvoiceLine::STATUS_VOIDED,
                'sort' => [$l->invoice->invoice_date->format('Y-m-d'), 'A' . $l->invoice->invoice_no, $l->id],
            ]);

        $tourRows = $this->tourLines($customer, $from, $to)->get('ticket', collect())->map(function (ServiceLine $l) {
            $d = $l->ticketDetail;
            $priced = $d && $d->hasTicketSalePricing();

            return [
                'date' => $l->linkable->invoice_date,
                'invoice' => $l->linkable->invoice_no,
                'ticket_no' => $d->ticket_no ?? null,
                'passenger' => $l->description,
                'sector' => $d ? ($d->citiesLabel() ?: $d->sector) : null,
                'fare' => $priced ? (float) $d->fare_amount : (float) $l->receivable_l_amount,
                'taxes' => $priced ? round((float) $d->tax_amount + (float) $d->apt_charges, 2) : 0.0,
                'sp' => $priced ? (float) $d->psf_amount : 0.0,
                'kb' => $priced ? (float) $d->discount_amount : 0.0,
                'net' => (float) $l->receivable_l_amount,
                'voided' => false,
                'sort' => [$l->linkable->invoice_date->format('Y-m-d'), 'B' . $l->linkable->invoice_no, $l->id],
            ];
        });

        return $saleRows->concat($tourRows)->sortBy(fn ($r) => implode('|', $r['sort']))->values();
    }

    // ── Refund invoices ────────────────────────────────────────────────

    /** Refunded (by refund adjustment date) and voided (by void date) Ticket Sale Invoice tickets. */
    protected function refundRows(Customer $customer, string $from, string $to): Collection
    {
        $lines = TicketSaleInvoiceLine::query()
            ->whereIn('status', [TicketSaleInvoiceLine::STATUS_REFUNDED, TicketSaleInvoiceLine::STATUS_VOIDED])
            ->whereHas('invoice', fn ($q) => $q->where('customer_id', $customer->id))
            ->with('invoice')
            ->get();

        return $lines->map(function (TicketSaleInvoiceLine $l) {
            if ($l->status === TicketSaleInvoiceLine::STATUS_REFUNDED) {
                $date = $l->refund_adjustment_date ?? $l->refund_date ?? $l->invoice->invoice_date;

                return [
                    'kind' => 'refund',
                    'date' => Carbon::parse($date),
                    'invoice' => 'RF' . $l->id . '(' . $l->invoice->invoice_no . ')',
                    'ticket_no' => $l->ticket_no,
                    'passenger' => $l->pax_name,
                    'sector' => $l->citiesLabel(),
                    'fare' => (float) $l->refund_fare_amount,
                    'taxes' => (float) $l->refund_tax_amount,
                    'sp' => 0.0,
                    'kb' => 0.0,
                    'cc' => round((float) $l->refund_deduction_supplier + (float) $l->refund_deduction_company, 2),
                    'net' => (float) $l->refund_amount, // returned to the customer
                ];
            }

            $date = $l->void_date ?? $l->invoice->invoice_date;

            return [
                'kind' => 'void',
                'date' => Carbon::parse($date),
                'invoice' => 'VD' . $l->id . '(' . $l->invoice->invoice_no . ')',
                'ticket_no' => $l->ticket_no,
                'passenger' => $l->pax_name,
                'sector' => $l->citiesLabel(),
                'fare' => (float) $l->fare_amount,
                'taxes' => round((float) $l->tax_amount + (float) $l->apt_charges, 2),
                'sp' => (float) $l->psf_amount,
                'kb' => (float) $l->discount_amount,
                'cc' => (float) $l->void_total_deduction,
                'net' => round((float) $l->total_amount - (float) $l->void_total_deduction, 2),
            ];
        })
            ->filter(fn ($r) => $r['date']->toDateString() >= $from && $r['date']->toDateString() <= $to)
            ->sortBy(fn ($r) => $r['date']->format('Y-m-d') . '|' . $r['invoice'])
            ->values();
    }

    // ── Sale: tour invoice service lines ───────────────────────────────

    /** Tour (and any other travel) invoice service lines, grouped by service type. */
    protected function tourLines(Customer $customer, string $from, string $to): Collection
    {
        $invoiceIds = TravelInvoice::where('customer_id', $customer->id)
            ->where('status', '!=', 'cancelled')
            ->whereBetween('invoice_date', [$from, $to])
            ->pluck('id');

        if ($invoiceIds->isEmpty()) {
            return collect();
        }

        return ServiceLine::where('linkable_type', TravelInvoice::class)
            ->whereIn('linkable_id', $invoiceIds)
            ->with([
                'linkable.passengers', 'ticketDetail',
                'hotelDetail.hotel', 'hotelDetail.rooms.hotelRoom', 'hotelDetail.hotelRoom',
                'transportDetail.vehicle', 'transportDetail.vehicles.vehicle',
                'visaDetail.visaType', 'otherDetail.service',
            ])
            ->get()
            ->sortBy(fn ($l) => $l->linkable->invoice_date->format('Y-m-d') . '|' . $l->linkable->invoice_no . '|' . str_pad((string) $l->sort_order, 5, '0', STR_PAD_LEFT) . '|' . $l->id)
            ->groupBy('service_type');
    }

    protected function base(ServiceLine $l): array
    {
        return [
            'date' => $l->linkable->invoice_date,
            'invoice' => $l->linkable->invoice_no,
            'net' => (float) $l->receivable_l_amount,
        ];
    }

    protected function hotelRow(ServiceLine $l): array
    {
        $d = $l->hotelDetail;
        $rooms = $d ? $d->rooms : collect();
        $roomTypes = $rooms->map(fn ($r) => optional($r->hotelRoom)->room_type)->filter()->unique()->values();

        return $this->base($l) + [
            'hotel' => $d->hotel->name ?? '—',
            'passenger' => $d->booking_name ?? null,
            'check_in' => $d->check_in ?? null,
            'check_out' => $d->check_out ?? null,
            'room_type' => $roomTypes->isNotEmpty() ? $roomTypes->implode(', ') : ($d->hotelRoom->room_type ?? null),
            'rooms' => $rooms->isNotEmpty() ? (int) $rooms->sum('qty') : (int) ($d->room_qty ?? 0),
            'nights' => (int) ($d->nights ?? 0),
        ];
    }

    protected function transportRow(ServiceLine $l): array
    {
        $d = $l->transportDetail;
        $vehicles = $d ? $d->vehicles : collect();

        return $this->base($l) + [
            'passenger' => $d->booking_name ?? null,
            'vehicle' => $vehicles->isNotEmpty()
                ? $vehicles->map(fn ($v) => optional($v->vehicle)->name)->filter()->implode(', ')
                : ($d->vehicle->name ?? null),
            'sector' => $vehicles->isNotEmpty() ? $vehicles->pluck('sector')->filter()->implode(', ') : ($d->sector ?? null),
            'reference' => $d->reference_no ?? null,
            'remarks' => $l->description,
        ];
    }

    /**
     * The Visa tab's per-line passenger grid isn't stored yet, so the
     * invoice's General Information passengers stand in for "who the visa
     * is for" (names, Adult/Child/Infant counts, passport numbers).
     */
    protected function visaRow(ServiceLine $l): array
    {
        $d = $l->visaDetail;
        $pax = $l->linkable->passengers;

        return $this->base($l) + [
            'passenger' => $pax->isNotEmpty() ? $pax->map(fn ($p) => $p->name . ' X (1)')->implode(', ') : ($l->description ?: null),
            'adults' => $pax->where('pax_type', 'adult')->count(),
            'children' => $pax->where('pax_type', 'child')->count(),
            'infants' => $pax->where('pax_type', 'infant')->count(),
            'passport' => $pax->pluck('passport_no_nic')->filter()->implode(', '),
            'visa_type' => $d->visaType->name ?? null,
            'apply_date' => $d->apply_date ?? null,
            'expiry_date' => $d->expiry_date ?? null,
        ];
    }

    protected function otherRow(ServiceLine $l): array
    {
        $d = $l->otherDetail;

        return $this->base($l) + [
            'service' => $d->service->name ?? null,
            'passenger' => $d->booking_name ?? null,
            'travel_date' => $d->travel_date ?? null,
            'qty' => (int) ($d->qty ?? 1),
            'remarks' => $l->description,
        ];
    }

    // ── Vouchers ───────────────────────────────────────────────────────

    /** Receipts (credit to the customer) and payments (debit), excluding a Ticket Sale Invoice's own postings. */
    protected function voucherRows(Customer $customer, string $from, string $to): Collection
    {
        $accountId = $customer->chart_of_account_id;
        if (!$accountId) {
            return collect();
        }

        // Vouchers an invoice posted itself are already shown as invoice rows above.
        $invoicePosted = TicketInvoiceLedgerEntry::pluck('voucher_id')
            ->merge(\App\Models\TravelInvoiceLedgerEntry::pluck('voucher_id'))
            ->filter()->all();

        $vouchers = Voucher::query()
            ->whereBetween('date', [$from, $to])
            ->where(fn ($q) => $q->where('ac_dr_sid', $accountId)->orWhere('ac_cr_sid', $accountId))
            ->when($invoicePosted, fn ($q) => $q->whereNotIn('id', $invoicePosted))
            ->orderBy('date')->orderBy('id')
            ->get();
        $payments = \App\Models\InvoicePayment::whereIn('voucher_id', $vouchers->pluck('id'))->get()->keyBy('voucher_id');

        return $vouchers
            ->map(function (Voucher $v) use ($accountId, $payments) {
                $isDebit = (int) $v->ac_dr_sid === (int) $accountId;
                $prefix = match ($v->voucher_type) {
                    'receipt' => 'RV',
                    'payment' => 'PV',
                    'journal' => 'JV',
                    default => strtoupper(substr((string) $v->voucher_type, 0, 1)) . 'V',
                };
                $payment = $payments->get($v->id);

                return [
                    'date' => Carbon::parse($v->date),
                    'voucher_no' => $prefix . $v->id,
                    'reference' => $v->reference,
                    'description' => $v->description ?: $v->remarks,
                    'cheque_no' => $payment && $payment->payment_mode === 'cheque' ? $payment->reference : null,
                    'debit' => $isDebit ? (float) $v->amount : 0.0,
                    'credit' => $isDebit ? 0.0 : (float) $v->amount,
                ];
            });
    }

    // ── helpers ────────────────────────────────────────────────────────

    /** Date + invoice no are printed only on the first row of each invoice (as on the sample). */
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
