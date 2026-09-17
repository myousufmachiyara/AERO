<?php

namespace App\Models;

class ServiceLine extends \Illuminate\Database\Eloquent\Model
{
    protected $fillable = [
        'linkable_type', 'linkable_id', 'service_type', 'supplier_id',
        'charge_template_id', 'description', 'currency', 'exchange_rate',
        'receivable_f_amount', 'receivable_l_amount',
        'payable_f_amount', 'payable_l_amount', 'income_l_amount', 'sort_order',
    ];

    protected $casts = [
        'exchange_rate' => 'decimal:4',
        'receivable_f_amount' => 'decimal:2',
        'receivable_l_amount' => 'decimal:2',
        'payable_f_amount' => 'decimal:2',
        'payable_l_amount' => 'decimal:2',
        'income_l_amount' => 'decimal:2',
    ];

    public function linkable()
    {
        return $this->morphTo();
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function chargeTemplate()
    {
        return $this->belongsTo(ChargeTemplate::class);
    }

    // --- Phase 3: one-to-one structured detail per service_type, plus the
    // open charges breakdown (SPO1-6/WHT/COM/PSF/taxes) shared by all types.
    public function ticketDetail()
    {
        return $this->hasOne(InvoiceTicketDetail::class);
    }

    public function hotelDetail()
    {
        return $this->hasOne(InvoiceHotelDetail::class);
    }

    public function transportDetail()
    {
        return $this->hasOne(InvoiceTransportDetail::class);
    }

    public function visaDetail()
    {
        return $this->hasOne(InvoiceVisaDetail::class);
    }

    public function otherDetail()
    {
        return $this->hasOne(InvoiceOtherDetail::class);
    }

    public function charges()
    {
        return $this->hasMany(ServiceLineCharge::class);
    }

    /**
     * Recompute local-currency amounts and income from the foreign amounts
     * and exchange rate. Called before save from the owning controller so
     * the DB never stores a stale income figure.
     *
     * $chargesTotal is the signed sum of this line's service_line_charges
     * (positive rows such as commission increase income, negative rows
     * such as WHT/tax withheld reduce it) — passed in rather than queried
     * here so a not-yet-saved line (no id, no persisted charges) still
     * recalculates correctly during the same request that creates it.
     */
    public function recalculate(float $chargesTotal = 0.0): void
    {
        $rate = (float) ($this->exchange_rate ?: 1);
        $this->receivable_l_amount = round(((float) $this->receivable_f_amount) * $rate, 2);
        $this->payable_l_amount = round(((float) $this->payable_f_amount) * $rate, 2);
        $this->income_l_amount = round($this->receivable_l_amount - $this->payable_l_amount + $chargesTotal, 2);
    }
}
