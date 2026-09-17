<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InvoicePayment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'voucher_id', 'direction', 'customer_id', 'supplier_id', 'travel_invoice_id',
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

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
