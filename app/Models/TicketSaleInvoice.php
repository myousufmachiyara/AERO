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

    /**
     * Net owed to the supplier(s), exactly as the posted vouchers carry it:
     * Σ (fare + tax + APT on active tickets, or everything the customer is
     * still charged on a refunded/voided one) − Σ (commission − WHT).
     */
    public function getTotalPayableAttribute(): float
    {
        return round($this->lines->sum(fn (TicketSaleInvoiceLine $l) => $l->effectiveSupplierPayable() - $l->effectiveNetCommission()), 2);
    }

    /**
     * Agency income = Receivable − net supplier payable
     * = PSF − Discount + Commission − WHT on active tickets; refunded and
     * voided tickets add nothing — the same profit the ledger shows once
     * the invoice is posted.
     */
    public function getTotalIncomeAttribute(): float
    {
        return round($this->lines->sum(fn (TicketSaleInvoiceLine $l) => $l->effectiveIncome()), 2);
    }

    /** Receipts / supplier payments recorded against this invoice (Customer Payment Voucher). */
    public function payments()
    {
        return $this->hasMany(InvoicePayment::class);
    }

    public function receivedAmount(): float
    {
        return (float) $this->payments()->where('direction', 'receipt')->sum('amount');
    }

    public function outstandingAmount(): float
    {
        return round((float) $this->total_amount - $this->receivedAmount(), 2);
    }

    /**
     * Roll the header totals up from the current lines. Amounts on
     * refunded/voided lines are already reduced to their post-adjustment
     * values (see TicketSaleInvoiceLine::effectiveReceivable()), so this
     * always reflects what the customer actually owes right now.
     */
    public function recalculateTotals(): void
    {
        $all = $this->lines()->get();
        // Fare … discount are the figures AS ISSUED (every ticket, refunded or
        // voided included) so the invoice summary shows the complete sale; the
        // refund/void adjustments, what the customer still owes (total_amount)
        // and the income are worked out per line (effective*()).
        $lines = $all;

        $this->total_fare = $lines->sum('fare_amount');
        $this->total_tax = $lines->sum('tax_amount');
        $this->total_apt = $lines->sum('apt_charges');
        $this->total_commission = $lines->sum('commission_amount');
        $this->total_wht = $lines->sum('wht_amount');
        $this->total_psf = $lines->sum('psf_amount');
        $this->total_discount = $lines->sum('discount_amount');
        $this->total_amount = $all->sum(fn (TicketSaleInvoiceLine $l) => $l->effectiveReceivable());
        $this->save();
    }
}