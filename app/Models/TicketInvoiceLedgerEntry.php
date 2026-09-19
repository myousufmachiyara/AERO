<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketInvoiceLedgerEntry extends Model
{
    protected $fillable = [
        'ticket_sale_invoice_id', 'voucher_id', 'entry_type', 'airline_id', 'amount',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public const TYPE_CUSTOMER = 'customer_sale';
    public const TYPE_AIRLINE_COMMISSION = 'airline_commission';

    public function invoice()
    {
        return $this->belongsTo(TicketSaleInvoice::class, 'ticket_sale_invoice_id');
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