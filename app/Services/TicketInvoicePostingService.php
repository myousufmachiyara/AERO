<?php

namespace App\Services;

use App\Models\Airline;
use App\Models\ChartOfAccounts;
use App\Models\SubHeadOfAccounts;
use App\Models\TicketInvoiceLedgerEntry;
use App\Models\TicketSaleInvoice;
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
