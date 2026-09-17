<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TravelInvoice extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'invoice_no', 'invoice_type', 'invoice_date', 'visit_type', 'payment_mode',
        'status', 'customer_id', 'quotation_id', 'name_on_invoice', 'cost_center',
        'staff_id', 'remarks', 'total_receivable', 'total_payable', 'total_income',
        'created_by',
    ];

    protected $casts = [
        'invoice_date' => 'date',
    ];

    public const STATUSES = ['draft', 'confirmed', 'cancelled'];

    /** "sale" = Sale Invoice (tickets only). "tour" = Tour Invoice (all tabs). */
    public const TYPE_SALE = 'sale';
    public const TYPE_TOUR = 'tour';

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function quotation()
    {
        return $this->belongsTo(Quotation::class);
    }

    public function staff()
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function serviceLines()
    {
        return $this->morphMany(ServiceLine::class, 'linkable')->orderBy('sort_order');
    }

    public function passengers()
    {
        return $this->morphMany(TravelPassenger::class, 'passengerable');
    }

    public function payments()
    {
        return $this->hasMany(InvoicePayment::class);
    }

    public function isEditable(): bool
    {
        return $this->status === 'draft';
    }

    /** Customer receipts recorded against this invoice — see InvoicePayment. */
    public function receivedAmount(): float
    {
        return (float) $this->payments()->where('direction', 'receipt')->sum('amount');
    }

    public function outstandingAmount(): float
    {
        return round((float) $this->total_receivable - $this->receivedAmount(), 2);
    }

    /**
     * Roll the header totals up from the current service lines — identical
     * pattern to Quotation::recalculateTotals(), kept separate because the
     * two models don't share a base class.
     */
    public function recalculateTotals(): void
    {
        $lines = $this->serviceLines()->get();

        $this->total_receivable = $lines->sum('receivable_l_amount');
        $this->total_payable = $lines->sum('payable_l_amount');
        $this->total_income = $lines->sum('income_l_amount');
        $this->save();
    }
}
