<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TicketSaleInvoice extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'invoice_no', 'invoice_date', 'adjustment_date', 'customer_id',
        'status', 'posted_at', 'posted_by', 'remarks',
        'total_fare', 'total_tax', 'total_apt', 'total_commission',
        'total_wht', 'total_psf', 'total_discount', 'total_amount',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'adjustment_date' => 'date',
        'posted_at' => 'datetime',
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_POSTED = 'posted';
    public const STATUSES = [self::STATUS_PENDING, self::STATUS_POSTED];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function poster()
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function lines()
    {
        return $this->hasMany(TicketSaleInvoiceLine::class)->orderBy('sort_order');
    }

    public function ledgerEntries()
    {
        return $this->hasMany(TicketInvoiceLedgerEntry::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isPosted(): bool
    {
        return $this->status === self::STATUS_POSTED;
    }

    /** Only a pending invoice can have its header/lines edited. */
    public function isEditable(): bool
    {
        return $this->isPending();
    }

    /** The date the ledger-posting date defaults to when not explicitly set. */
    public function effectiveAdjustmentDate(): \Carbon\Carbon
    {
        return $this->adjustment_date ?: $this->invoice_date;
    }

    // --- Report-compatibility accessors ------------------------------
    // The Phase 6 Travel Sales/Vendor/Accounting reports were built
    // against TravelInvoice's public shape (invoice_type, total_receivable,
    // total_payable, total_income, outstandingAmount()). Rather than fork
    // the report code into two parallel branches, this model exposes the
    // same shape so a TicketSaleInvoice can sit in the same collection and
    // be rendered by the same Blade markup as a TravelInvoice row.

    /** Always "sale" — routes report rows to the ticket_invoices.* URLs, same convention the old shared model used. */
    public function getInvoiceTypeAttribute(): string
    {
        return 'sale';
    }

    /** total_amount already IS the customer receivable (see recalculateTotals()). */
    public function getTotalReceivableAttribute(): float
    {
        return (float) $this->total_amount;
    }

    /** Sum of each line's effectiveSupplierPayable() — see that method for the approximation used. */
    public function getTotalPayableAttribute(): float
    {
        return round($this->lines->sum(fn (TicketSaleInvoiceLine $l) => $l->effectiveSupplierPayable()), 2);
    }

    /**
     * "Income" here is net airline commission (commission − WHT), not
     * receivable-minus-payable: the fare/tax/apt/PSF the customer pays are
     * passed through to the supplier at cost, so the agency's actual
     * revenue on a ticket sale is the commission, not a fare markup.
     */
    public function getTotalIncomeAttribute(): float
    {
        return round($this->lines->sum(fn (TicketSaleInvoiceLine $l) => $l->effectiveNetCommission()), 2);
    }

    /**
     * Ticket Sale Invoice isn't wired into the Phase 5 Payments module yet
     * (no travel_invoice_id-equivalent link), so every invoice is treated
     * as fully unpaid for reporting purposes until that integration is
     * built — a disclosed limitation, not a computed figure.
     */
    public function outstandingAmount(): float
    {
        return (float) $this->total_amount;
    }

    /**
     * Roll the header totals up from the current lines. Amounts on
     * refunded/voided lines are already reduced to their post-adjustment
     * values (see TicketSaleInvoiceLine::effectiveReceivable()), so this
     * always reflects what the customer actually owes right now.
     */
    public function recalculateTotals(): void
    {
        $lines = $this->lines()->get();

        $this->total_fare = $lines->sum('fare_amount');
        $this->total_tax = $lines->sum('tax_amount');
        $this->total_apt = $lines->sum('apt_charges');
        $this->total_commission = $lines->sum('commission_amount');
        $this->total_wht = $lines->sum('wht_amount');
        $this->total_psf = $lines->sum('psf_amount');
        $this->total_discount = $lines->sum('discount_amount');
        $this->total_amount = $lines->sum(fn (TicketSaleInvoiceLine $l) => $l->effectiveReceivable());
        $this->save();
    }
}