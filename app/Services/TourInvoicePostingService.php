<?php

namespace App\Services;

use App\Models\Airline;
use App\Models\ChartOfAccounts;
use App\Models\ServiceLine;
use App\Models\SubHeadOfAccounts;
use App\Models\Supplier;
use App\Models\TravelInvoice;
use App\Models\TravelInvoiceLedgerEntry;
use App\Models\Voucher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Ledger posting for a Tour Invoice — runs when the invoice is Confirmed,
 * and is reversed when it goes back to Draft or is Cancelled.
 *
 * All vouchers are dated on the invoice date and referenced with the
 * invoice no. Per service type:
 *
 *   Ticket (Ticket Sale layout) — exactly as a posted Ticket Sale Invoice:
 *     Dr Customer         / Cr Ticket Sales                (total − PSF)
 *     Dr Customer         / Cr PSF Income                  (PSF)
 *     Dr Ticket Cost      / Cr Airline                     (fare + tax + APT)
 *     Dr Airline          / Cr Airline Commission Income   (commission − WHT)
 *     The party is the ticket's airline; if it has none, the line's supplier.
 *
 *   Hotel / Transport / Visa / Other (and older Receivable/Payable tickets):
 *     Dr Customer         / Cr <Service> Sales             (Receivable, PKR)
 *     Dr <Service> Cost   / Cr Supplier                    (Payable, PKR)
 *     Line charges (supplier commission +, withholding −), net per supplier:
 *     Dr Supplier         / Cr Supplier Commission Income  (net > 0)
 *     Dr Supplier Commission Income / Cr Supplier          (net < 0)
 *
 * So ledger profit always equals the invoice's Income column.
 */
class TourInvoicePostingService
{
    public const SERVICE_LABELS = [
        'ticket' => 'Ticket', 'hotel' => 'Hotel', 'transport' => 'Transport',
        'visa' => 'Visa', 'other' => 'Other Services',
    ];

    public function isPosted(TravelInvoice $invoice): bool
    {
        return TravelInvoiceLedgerEntry::where('travel_invoice_id', $invoice->id)->exists();
    }

    /** Throws a RuntimeException (with a user-readable message) when the invoice cannot be posted. */
    public function post(TravelInvoice $invoice): void
    {
        if ($this->isPosted($invoice)) {
            throw new \RuntimeException('This invoice is already posted to the ledgers.');
        }

        $customer = $invoice->customer;
        if (!$customer || !$customer->chart_of_account_id) {
            throw new \RuntimeException('This customer has no ledger account yet — re-save the customer record first.');
        }

        $lines = $invoice->serviceLines()->with('ticketDetail', 'charges', 'supplier')->get();
        $this->assertPartiesPresent($lines);

        DB::transaction(function () use ($invoice, $lines, $customer) {
            $date = $invoice->invoice_date->format('Y-m-d');
            $sales = [];        // service => amount (Cr <Service> Sales)
            $psf = 0.0;
            $costs = [];        // "service|kind|partyId" => amount (Dr Cost / Cr party)
            $commission = [];   // "kind|partyId" => signed amount (Dr party / Cr Commission Income)

            foreach ($lines as $line) {
                $type = $line->service_type;
                $d = $line->ticketDetail;

                if ($type === 'ticket' && $d && $d->hasTicketSalePricing()) {
                    [$kind, $partyId] = $d->airline_id ? ['airline', (int) $d->airline_id] : ['supplier', (int) $line->supplier_id];
                    $sales['ticket'] = ($sales['ticket'] ?? 0) + max(0, (float) $d->total_amount - (float) $d->psf_amount);
                    $psf += (float) $d->psf_amount;
                    $key = "ticket|{$kind}|{$partyId}";
                    $costs[$key] = ($costs[$key] ?? 0) + (float) $d->fare_amount + (float) $d->tax_amount + (float) $d->apt_charges;
                    $ckey = "{$kind}|{$partyId}";
                    $commission[$ckey] = ($commission[$ckey] ?? 0) + (float) $d->commission_amount - (float) $d->wht_amount;
                    continue;
                }

                $sales[$type] = ($sales[$type] ?? 0) + (float) $line->receivable_l_amount;
                if ($line->supplier_id) {
                    $key = "{$type}|supplier|{$line->supplier_id}";
                    $costs[$key] = ($costs[$key] ?? 0) + (float) $line->payable_l_amount;
                    $chargesNet = round((float) $line->income_l_amount - ((float) $line->receivable_l_amount - (float) $line->payable_l_amount), 2);
                    $ckey = "supplier|{$line->supplier_id}";
                    $commission[$ckey] = ($commission[$ckey] ?? 0) + $chargesNet;
                }
            }

            foreach ($sales as $type => $amount) {
                $label = self::SERVICE_LABELS[$type] ?? ucfirst($type);
                $this->entry($invoice, $type, TravelInvoiceLedgerEntry::TYPE_SALE, $date,
                    $customer->chart_of_account_id, $this->salesAccount($type)->id, $amount,
                    "Tour Invoice {$invoice->invoice_no} — {$label} sale to {$customer->name}");
            }

            $this->entry($invoice, 'ticket', TravelInvoiceLedgerEntry::TYPE_PSF, $date,
                $customer->chart_of_account_id,
                $this->systemAccount(config('travel.psf_income_subhead_name'), config('travel.psf_income_account_name'), 'income')->id,
                $psf, "Tour Invoice {$invoice->invoice_no} — PSF (company earning)");

            foreach ($costs as $key => $amount) {
                [$type, $kind, $partyId] = explode('|', $key);
                [$partyAccount, $partyName] = $this->party($kind, (int) $partyId);
                $label = self::SERVICE_LABELS[$type] ?? ucfirst($type);
                $this->entry($invoice, $type, TravelInvoiceLedgerEntry::TYPE_COST, $date,
                    $this->costAccount($type)->id, $partyAccount, $amount,
                    "Tour Invoice {$invoice->invoice_no} — {$label} cost payable to {$partyName}");
            }

            foreach ($commission as $key => $amount) {
                [$kind, $partyId] = explode('|', $key);
                [$partyAccount, $partyName] = $this->party($kind, (int) $partyId);
                $incomeAccount = $kind === 'airline'
                    ? $this->systemAccount(config('travel.airline_commission_income_subhead_name'), config('travel.airline_commission_income_account_name'), 'income')
                    : $this->systemAccount('Other Income', 'Supplier Commission Income', 'income');
                $service = $kind === 'airline' ? 'ticket' : 'charges';

                if ($amount > 0) {
                    $this->entry($invoice, $service, TravelInvoiceLedgerEntry::TYPE_COMMISSION, $date,
                        $partyAccount, $incomeAccount->id, $amount,
                        "Tour Invoice {$invoice->invoice_no} — commission receivable from {$partyName}");
                } elseif ($amount < 0) {
                    $this->entry($invoice, $service, TravelInvoiceLedgerEntry::TYPE_COMMISSION, $date,
                        $incomeAccount->id, $partyAccount, -$amount,
                        "Tour Invoice {$invoice->invoice_no} — charges payable to {$partyName}");
                }
            }
        });
    }

    /** Removes every voucher this invoice posted (Back to Draft / Cancel). No-op when nothing was posted. */
    public function unpost(TravelInvoice $invoice): void
    {
        DB::transaction(function () use ($invoice) {
            foreach (TravelInvoiceLedgerEntry::where('travel_invoice_id', $invoice->id)->with('voucher')->get() as $entry) {
                $entry->voucher?->delete();
                $entry->delete();
            }
        });
    }

    /** Vouchers this invoice has posted, oldest first (for the Show page). */
    public function vouchers(TravelInvoice $invoice)
    {
        $ids = TravelInvoiceLedgerEntry::where('travel_invoice_id', $invoice->id)->pluck('voucher_id');

        return Voucher::with('debitAccount', 'creditAccount')->whereIn('id', $ids)->orderBy('id')->get();
    }

    /** Every line that owes someone money must name who: a supplier, or (tickets) an airline. */
    protected function assertPartiesPresent($lines): void
    {
        $problems = [];
        foreach ($lines->values() as $i => $line) {
            $label = (self::SERVICE_LABELS[$line->service_type] ?? ucfirst($line->service_type)) . ' line ' . ($i + 1)
                . ($line->description ? " ({$line->description})" : '');
            $d = $line->ticketDetail;

            if ($line->service_type === 'ticket' && $d && $d->hasTicketSalePricing()) {
                if ($d->airline_id) {
                    if (!Airline::whereKey($d->airline_id)->value('chart_of_account_id')) {
                        $problems[] = "{$label}: the airline has no ledger account — re-save the airline record.";
                    }
                } elseif (!$line->supplier_id) {
                    $problems[] = "{$label}: pick the airline (or a supplier) so the cost can be posted.";
                }
                continue;
            }

            $owes = abs((float) $line->payable_l_amount) > 0.001
                || abs((float) $line->income_l_amount - ((float) $line->receivable_l_amount - (float) $line->payable_l_amount)) > 0.001;
            if ($owes && !$line->supplier_id) {
                $problems[] = "{$label}: has a payable amount but no supplier.";
            }
        }

        foreach ($lines->pluck('supplier_id')->filter()->unique() as $supplierId) {
            $s = Supplier::find($supplierId);
            if ($s && !$s->chart_of_account_id) {
                $problems[] = "Supplier '{$s->name}' has no ledger account — re-save the supplier record.";
            }
        }

        if ($problems) {
            throw new \RuntimeException('Cannot confirm: ' . implode(' ', array_unique($problems)));
        }
    }

    /** [ledger account id, display name] of an airline or supplier. */
    protected function party(string $kind, int $id): array
    {
        $model = $kind === 'airline' ? Airline::findOrFail($id) : Supplier::findOrFail($id);

        return [(int) $model->chart_of_account_id, $model->name];
    }

    protected function salesAccount(string $type): ChartOfAccounts
    {
        if ($type === 'ticket') {
            return $this->systemAccount(config('travel.ticket_sales_income_subhead_name'), config('travel.ticket_sales_income_account_name'), 'income');
        }

        return $this->systemAccount('Sales', (self::SERVICE_LABELS[$type] ?? ucfirst($type)) . ' Sales', 'income');
    }

    protected function costAccount(string $type): ChartOfAccounts
    {
        if ($type === 'ticket') {
            return $this->systemAccount(config('travel.ticket_cost_expense_subhead_name'), config('travel.ticket_cost_expense_account_name'), 'expense');
        }

        return $this->systemAccount('Cost of Goods Sold', (self::SERVICE_LABELS[$type] ?? ucfirst($type)) . ' Cost', 'expense');
    }

    protected function entry(TravelInvoice $invoice, string $service, string $entryType, string $date, int $dr, int $cr, float $amount, string $description): void
    {
        $amount = round($amount, 2);
        if ($amount <= 0.001) {
            return;
        }

        $voucher = Voucher::create([
            'voucher_type' => 'journal',
            'date' => $date,
            'ac_dr_sid' => $dr,
            'ac_cr_sid' => $cr,
            'amount' => $amount,
            'reference' => $invoice->invoice_no,
            'description' => $description,
        ]);

        TravelInvoiceLedgerEntry::create([
            'travel_invoice_id' => $invoice->id,
            'voucher_id' => $voucher->id,
            'service_type' => $service,
            'entry_type' => $entryType,
            'amount' => $amount,
        ]);
    }

    /** Find-or-create a named system ledger account (same pattern as TicketInvoicePostingService). */
    protected function systemAccount(string $subHeadName, string $accountName, string $type): ChartOfAccounts
    {
        $existing = ChartOfAccounts::where('name', $accountName)->first();
        if ($existing) {
            return $existing;
        }

        $subHead = SubHeadOfAccounts::where('name', $subHeadName)->first();
        if (!$subHead) {
            Log::warning("[TourInvoicePostingService] Sub-head '{$subHeadName}' not found; falling back to first sub-head.");
            $subHead = SubHeadOfAccounts::firstOrFail();
        }

        return ChartOfAccounts::create([
            'shoa_id' => $subHead->id,
            'account_code' => ChartOfAccounts::nextAccountCode($subHead->id),
            'name' => $accountName,
            'account_type' => $type,
            'receivables' => 0,
            'payables' => 0,
            'credit_limit' => 0,
            'opening_date' => now(),
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);
    }
}
