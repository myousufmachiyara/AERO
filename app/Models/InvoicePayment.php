<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InvoicePayment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'voucher_id', 'direction', 'customer_id', 'supplier_id', 'travel_invoice_id', 'ticket_sale_invoice_id',
        'payment_date', 'amount', 'payment_mode', 'reference', 'remarks', 'created_by',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public const DIRECTIONS = ['receipt', 'payment'];
    public const MODES = ['cash', 'bank', 'cheque', 'online'];

    public function voucher()
    {
        return $this->belongsTo(Voucher::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function travelInvoice()
    {
        return $this->belongsTo(TravelInvoice::class);
    }

    public function ticketSaleInvoice()
    {
        return $this->belongsTo(TicketSaleInvoice::class);
    }

    /** The Tour or Ticket Sale invoice this payment was recorded against, if any. */
    public function invoice(): TravelInvoice|TicketSaleInvoice|null
    {
        return $this->travelInvoice ?? $this->ticketSaleInvoice;
    }

    public function invoiceNo(): ?string
    {
        return $this->invoice()?->invoice_no;
    }

    /** Show page of the linked invoice, or null. */
    public function invoiceUrl(): ?string
    {
        if ($this->travel_invoice_id) {
            return route('tour_invoices.show', $this->travel_invoice_id);
        }

        return $this->ticket_sale_invoice_id ? route('ticket_invoices.show', $this->ticket_sale_invoice_id) : null;
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
