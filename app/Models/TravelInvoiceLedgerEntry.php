<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One voucher posted by a confirmed Tour Invoice — see TourInvoicePostingService. */
class TravelInvoiceLedgerEntry extends Model
{
    protected $fillable = ['travel_invoice_id', 'voucher_id', 'service_type', 'entry_type', 'amount'];

    protected $casts = ['amount' => 'decimal:2'];

    public const TYPE_SALE = 'customer_sale';
    public const TYPE_PSF = 'customer_psf_income';
    public const TYPE_COST = 'supplier_payable';
    public const TYPE_COMMISSION = 'commission_receivable';

    public function invoice()
    {
        return $this->belongsTo(TravelInvoice::class, 'travel_invoice_id');
    }

    public function voucher()
    {
        return $this->belongsTo(Voucher::class);
    }
}
