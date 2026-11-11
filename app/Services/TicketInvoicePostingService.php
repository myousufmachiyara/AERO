<?php

namespace App\Services;

use App\Models\Airline;
use App\Models\ChartOfAccounts;
use App\Models\SubHeadOfAccounts;
use App\Models\TicketInvoiceLedgerEntry;
use App\Models\TicketSaleInvoice;
use App\Models\TicketSaleInvoiceLine;
use App\Models\Voucher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * "Post" / "Unpost" for a Ticket Sale Invoice — client feedback: "sale
 * invoice ticket action will be pending status means just document,
 * posted means hit ledgers... total invoice amount hit customer ledger...
 * commission amount hits airline ledger, we have to receive that from
 * airline."
 *
 * Posting creates real double-entry Vouchers (so ledgers/trial balance/
 * party statements pick this up exactly like any other transaction):
 *   1. Customer side — up to two vouchers, both Dr Customer, for what the
 *      customer is actually being charged (already net of any refund/void
 *      adjustments made on individual tickets), split by income type so
 *      PSF is distinguishable from ordinary ticket-sale revenue:
 *        a. Cr Ticket Sales Income — everything that isn't PSF. See
 *           TicketSaleInvoiceLine::effectiveTicketSalesIncome().
 *        b. Cr PSF Income — the PSF % fee on active tickets, plus the
 *           deduction the company itself keeps on a voided or refunded
 *           one (client's own term for both — "PSF (company earning)").
 *           See TicketSaleInvoiceLine::effectivePsfIncome().
 *   2. Per distinct airline touched by this invoice, up to two vouchers,
 *      both landing on that same Airline ledger account so its balance
 *      nets automatically:
 *        a. Payable — Dr Ticket Cost, Cr Airline — what's actually owed
 *           to the airline: base cost (fare+tax+APT — deliberately
 *           excluding PSF, which is the company's own fee, not the
 *           airline's) on every active ticket, plus the airline's own
 *           cut of the deduction on a voided or refunded one. See
 *           TicketSaleInvoiceLine::effectiveAirlinePayable().
 *        b. Receivable — Dr Airline, Cr Airline Commission Income — the
 *           commission earned on active tickets, net of WHT (WHT is
 *           folded into the net receivable rather than tracked as its
 *           own recoverable-tax account — a deliberate scope
 *           simplification, easy to split out later if a separate WHT
 *           ledger is wanted).
 *      Posted as two separate vouchers (not netted into one) so both
 *      sides stay individually auditable; either is skipped if its
 *      amount rounds to zero.
 *
 * Every voucher this creates is recorded in ticket_invoice_ledger_entries
 * so Unpost can find and reverse exactly those entries, never more.
 */
class TicketInvoicePostingService
{
    public function post(TicketSaleInvoice $invoice): void
    {
        if ($invoice->isPosted()) {
            throw new \RuntimeException('This invoice is already posted.');
        }

        $customer = $invoice->customer;
        if (!$customer || !$customer->chart_of_account_id) {
            throw new \RuntimeException('This customer has no ledger account yet — re-save the customer record first.');
        }

        $lines = $invoice->lines()->get();

        DB::transaction(function () use ($invoice, $lines, $customer) {
            $ticketSalesTotal = round($lines->sum(fn ($l) => $l->effectiveTicketSalesIncome()), 2);
            $psfIncomeTotal = round($lines->sum(fn ($l) => $l->effectivePsfIncome()), 2);

            if ($ticketSalesTotal > 0.001) {
                $incomeAccount = $this->systemAccount(
                    config('travel.ticket_sales_income_subhead_name'),
                    config('travel.ticket_sales_income_account_name')
                );

                $voucher = Voucher::create([
                    'voucher_type' => 'journal',
                    'date' => $invoice->effectiveAdjustmentDate()->format('Y-m-d'),
                    'ac_dr_sid' => $customer->chart_of_account_id,
                    'ac_cr_sid' => $incomeAccount->id,
                    'amount' => $ticketSalesTotal,
                    'reference' => $invoice->invoice_no,
                    'description' => "Ticket Sale Invoice {$invoice->invoice_no} — customer receivable",
                ]);

                TicketInvoiceLedgerEntry::create([
                    'ticket_sale_invoice_id' => $invoice->id,
                    'voucher_id' => $voucher->id,
                    'entry_type' => TicketInvoiceLedgerEntry::TYPE_CUSTOMER,
                    'amount' => $ticketSalesTotal,
                ]);
            }

            if ($psfIncomeTotal > 0.001) {
                $psfAccount = $this->systemAccount(
                    config('travel.psf_income_subhead_name'),
                    config('travel.psf_income_account_name')
                );

                $voucher = Voucher::create([
                    'voucher_type' => 'journal',
                    'date' => $invoice->effectiveAdjustmentDate()->format('Y-m-d'),
                    'ac_dr_sid' => $customer->chart_of_account_id,
                    'ac_cr_sid' => $psfAccount->id,
                    'amount' => $psfIncomeTotal,
                    'reference' => $invoice->invoice_no,
                    'description' => "Ticket Sale Invoice {$invoice->invoice_no} — PSF (company earning)",
                ]);

                TicketInvoiceLedgerEntry::create([
                    'ticket_sale_invoice_id' => $invoice->id,
                    'voucher_id' => $voucher->id,
                    'entry_type' => TicketInvoiceLedgerEntry::TYPE_CUSTOMER_PSF,
                    'amount' => $psfIncomeTotal,
                ]);
            }

            $byAirline = $lines->filter(fn ($l) => $l->airline_id)->groupBy('airline_id');

            foreach ($byAirline as $airlineId => $group) {
                $payable = round($group->sum(fn ($l) => $l->effectiveAirlinePayable()), 2);
                $receivable = round($group->sum(fn ($l) => $l->effectiveNetCommission()), 2);

                if ($payable <= 0.001 && $receivable <= 0.001) {
                    continue;
                }

                $airline = Airline::find($airlineId);
                if (!$airline || !$airline->chart_of_account_id) {
                    throw new \RuntimeException("Airline '{$airline?->name}' has no ledger account yet — re-save the airline record first.");
                }

                if ($payable > 0.001) {
                    $costAccount = $this->systemAccount(
                        config('travel.ticket_cost_expense_subhead_name'),
                        config('travel.ticket_cost_expense_account_name')
                    );

                    $voucher = Voucher::create([
                        'voucher_type' => 'journal',
                        'date' => $invoice->effectiveAdjustmentDate()->format('Y-m-d'),
                        'ac_dr_sid' => $costAccount->id,
                        'ac_cr_sid' => $airline->chart_of_account_id,
                        'amount' => $payable,
                        'reference' => $invoice->invoice_no,
                        'description' => "Ticket Sale Invoice {$invoice->invoice_no} — ticket cost payable to {$airline->name}",
                    ]);

                    TicketInvoiceLedgerEntry::create([
                        'ticket_sale_invoice_id' => $invoice->id,
                        'voucher_id' => $voucher->id,
                        'entry_type' => TicketInvoiceLedgerEntry::TYPE_AIRLINE_PAYABLE,
                        'airline_id' => $airlineId,
                        'amount' => $payable,
                    ]);
                }

                if ($receivable > 0.001) {
                    $commissionAccount = $this->systemAccount(
                        config('travel.airline_commission_income_subhead_name'),
                        config('travel.airline_commission_income_account_name')
                    );

                    $voucher = Voucher::create([
                        'voucher_type' => 'journal',
                        'date' => $invoice->effectiveAdjustmentDate()->format('Y-m-d'),
                        'ac_dr_sid' => $airline->chart_of_account_id,
                        'ac_cr_sid' => $commissionAccount->id,
                        'amount' => $receivable,
                        'reference' => $invoice->invoice_no,
                        'description' => "Ticket Sale Invoice {$invoice->invoice_no} — commission receivable from {$airline->name}",
                    ]);

                    TicketInvoiceLedgerEntry::create([
                        'ticket_sale_invoice_id' => $invoice->id,
                        'voucher_id' => $voucher->id,
                        'entry_type' => TicketInvoiceLedgerEntry::TYPE_AIRLINE_COMMISSION,
                        'airline_id' => $airlineId,
                        'amount' => $receivable,
                    ]);
                }
            }

            $invoice->update([
                'status' => TicketSaleInvoice::STATUS_POSTED,
                'posted_at' => now(),
                'posted_by' => auth()->id(),
            ]);
        });
    }

    public function unpost(TicketSaleInvoice $invoice): void
    {
        if (!$invoice->isPosted()) {
            throw new \RuntimeException('This invoice is not posted.');
        }

        DB::transaction(function () use ($invoice) {
            foreach ($invoice->ledgerEntries()->get() as $entry) {
                $entry->voucher?->delete();
                $entry->delete();
            }

            $invoice->update([
                'status' => TicketSaleInvoice::STATUS_PENDING,
                'posted_at' => null,
                'posted_by' => null,
            ]);
        });
    }

    /**
     * Refund / Void of a ticket on an ALREADY POSTED invoice.
     *
     * Client fix: "refund and void also hit vouchers and ledgers — first it
     * will credit then debit so we can see refund as a voucher entry". Must
     * be called AFTER the line has been marked refunded/voided. Two steps,
     * each its own voucher so the refund is visible in every ledger:
     *
     *   1. Reversal (credit) — undo exactly what Post booked for this ticket
     *      while it was active:
     *        Dr Ticket Sales Income  / Cr Customer   (non-PSF sale)
     *        Dr PSF Income           / Cr Customer   (PSF)
     *        Dr Airline              / Cr Ticket Cost (fare+tax+APT payable)
     *        Dr Airline Comm. Income / Cr Airline    (net commission)
     *   2. Charges (debit) — book what the ticket is still worth after the
     *      refund/void (the same effective* figures Post itself would use):
     *        Dr Customer   / Cr Ticket Sales Income  (retained sale / supplier deduction)
     *        Dr Customer   / Cr PSF Income           (company deduction)
     *        Dr Ticket Cost / Cr Airline             (supplier/airline deduction)
     *
     * Net effect on every ledger equals unposting and re-posting the
     * invoice, but with a readable audit trail. Every voucher is recorded
     * in ticket_invoice_ledger_entries (with the line id), so Unpost still
     * removes everything cleanly.
     */
    public function postLineAdjustment(TicketSaleInvoice $invoice, TicketSaleInvoiceLine $line, string $kind): void
    {
        if (!$invoice->isPosted()) {
            return; // pending invoice — the eventual Post picks up the effective values
        }

        $customer = $invoice->customer;
        if (!$customer || !$customer->chart_of_account_id) {
            throw new \RuntimeException('This customer has no ledger account yet — re-save the customer record first.');
        }

        $date = $kind === 'refund'
            ? ($line->refund_adjustment_date ?? $line->refund_date ?? now())
            : ($line->void_date ?? now());
        $date = \Illuminate\Support\Carbon::parse($date)->format('Y-m-d');

        // What Post booked for this ticket while it was still active.
        $asActive = clone $line;
        $asActive->status = TicketSaleInvoiceLine::STATUS_ACTIVE;

        $label = ucfirst($kind) . " of ticket {$line->ticket_no} ({$line->pax_name}"
            . ($line->pnr ? ", PNR {$line->pnr}" : '')
            . ($line->citiesLabel() ? ', ' . $line->citiesLabel() : '')
            . ") — invoice {$invoice->invoice_no}";

        $salesAccount = fn () => $this->systemAccount(config('travel.ticket_sales_income_subhead_name'), config('travel.ticket_sales_income_account_name'));
        $psfAccount = fn () => $this->systemAccount(config('travel.psf_income_subhead_name'), config('travel.psf_income_account_name'));
        $costAccount = fn () => $this->systemAccount(config('travel.ticket_cost_expense_subhead_name'), config('travel.ticket_cost_expense_account_name'));
        $commAccount = fn () => $this->systemAccount(config('travel.airline_commission_income_subhead_name'), config('travel.airline_commission_income_account_name'));

        $airline = $line->airline_id ? Airline::find($line->airline_id) : null;
        if ($airline && !$airline->chart_of_account_id) {
            throw new \RuntimeException("Airline '{$airline->name}' has no ledger account yet — re-save the airline record first.");
        }

        DB::transaction(function () use ($invoice, $line, $asActive, $kind, $date, $label, $customer, $airline, $salesAccount, $psfAccount, $costAccount, $commAccount) {
            $cust = $customer->chart_of_account_id;

            // ── 1. Reversal (credit customer / airline-side unwinds) ──
            $this->entry($invoice, $line, $kind, $date, $salesAccount()->id, $cust,
                $asActive->effectiveTicketSalesIncome(), "{$label} — reversal of ticket sale", 'rev_customer_sale');
            $this->entry($invoice, $line, $kind, $date, $psfAccount()->id, $cust,
                $asActive->effectivePsfIncome(), "{$label} — reversal of PSF", 'rev_customer_psf');

            if ($airline) {
                $this->entry($invoice, $line, $kind, $date, $airline->chart_of_account_id, $costAccount()->id,
                    $asActive->effectiveAirlinePayable(), "{$label} — reversal of ticket cost payable to {$airline->name}", 'rev_airline_payable', $airline->id);
                $this->entry($invoice, $line, $kind, $date, $commAccount()->id, $airline->chart_of_account_id,
                    $asActive->effectiveNetCommission(), "{$label} — reversal of commission from {$airline->name}", 'rev_airline_commission', $airline->id);
            }

            // ── 2. Charges retained after refund/void (debit) ──
            $this->entry($invoice, $line, $kind, $date, $cust, $salesAccount()->id,
                $line->effectiveTicketSalesIncome(), "{$label} — {$kind} charges receivable", TicketInvoiceLedgerEntry::TYPE_CUSTOMER);
            $this->entry($invoice, $line, $kind, $date, $cust, $psfAccount()->id,
                $line->effectivePsfIncome(), "{$label} — company deduction (PSF)", TicketInvoiceLedgerEntry::TYPE_CUSTOMER_PSF);

            if ($airline) {
                $this->entry($invoice, $line, $kind, $date, $costAccount()->id, $airline->chart_of_account_id,
                    $line->effectiveAirlinePayable(), "{$label} — supplier deduction payable to {$airline->name}", TicketInvoiceLedgerEntry::TYPE_AIRLINE_PAYABLE, $airline->id);
            }
        });
    }

    /** One voucher + its ledger-entry record; silently skipped for a zero amount. */
    protected function entry(
        TicketSaleInvoice $invoice,
        TicketSaleInvoiceLine $line,
        string $kind,
        string $date,
        int $drAccountId,
        int $crAccountId,
        float $amount,
        string $description,
        string $entryType,
        ?int $airlineId = null
    ): void {
        $amount = round($amount, 2);
        if ($amount <= 0.001) {
            return;
        }

        $voucher = Voucher::create([
            'voucher_type' => 'journal',
            'date' => $date,
            'ac_dr_sid' => $drAccountId,
            'ac_cr_sid' => $crAccountId,
            'amount' => $amount,
            'reference' => $invoice->invoice_no . '/' . strtoupper(substr($kind, 0, 1)) . '-' . $line->id,
            'description' => $description,
        ]);

        TicketInvoiceLedgerEntry::create([
            'ticket_sale_invoice_id' => $invoice->id,
            'ticket_sale_invoice_line_id' => $line->id,
            'voucher_id' => $voucher->id,
            'entry_type' => $entryType,
            'adjustment' => $kind,
            'airline_id' => $airlineId,
            'amount' => $amount,
        ]);
    }

    /**
     * Find-or-create a named system ledger account under the given
     * sub-head — same resilient by-name pattern PartyLedgerService uses,
     * so a customised chart of accounts never breaks posting.
     */
    protected function systemAccount(string $subHeadName, string $accountName): ChartOfAccounts
    {
        $existing = ChartOfAccounts::where('name', $accountName)->first();
        if ($existing) {
            return $existing;
        }

        $subHead = SubHeadOfAccounts::where('name', $subHeadName)->first();
        if (!$subHead) {
            Log::warning("[TicketInvoicePostingService] Sub-head '{$subHeadName}' not found; falling back to first sub-head.");
            $subHead = SubHeadOfAccounts::firstOrFail();
        }

        return ChartOfAccounts::create([
            'shoa_id' => $subHead->id,
            'account_code' => ChartOfAccounts::nextAccountCode($subHead->id),
            'name' => $accountName,
            'account_type' => 'income',
            'receivables' => 0,
            'payables' => 0,
            'credit_limit' => 0,
            'opening_date' => now(),
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);
    }
}
