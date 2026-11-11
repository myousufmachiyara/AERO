<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketInvoiceLedgerEntry extends Model
{
    protected $fillable = [
        'ticket_sale_invoice_id', 'ticket_sale_invoice_line_id', 'voucher_id', 'entry_type', 'adjustment', 'airline_id', 'amount',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public const TYPE_CUSTOMER = 'customer_sale';
    public const TYPE_CUSTOMER_PSF = 'customer_psf_income';
    public const TYPE_AIRLINE_COMMISSION = 'airline_commission';
    public const TYPE_AIRLINE_PAYABLE = 'airline_payable';

    public function invoice()
    {
        return $this->belongsTo(TicketSaleInvoice::class, 'ticket_sale_invoice_id');
    }

    public function line()
    {
        return $this->belongsTo(TicketSaleInvoiceLine::class, 'ticket_sale_invoice_line_id');
    }

    public function voucher()
    {
        return $this->belongsTo(Voucher::class);
    }

    public function airline()
    {
        return $this->belongsTo(Airline::class);
    }
}
