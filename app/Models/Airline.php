<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Master of Airlines (client feedback on Ticket Sale Invoice): a real
 * ledger-linked party, distinct from Supplier. numeric_code is the first
 * 3 digits of every 13-digit ticket number issued on that airline, which
 * is how the Ticket Sale Invoice form auto-detects the airline as soon as
 * a ticket number is typed in.
 */
class Airline extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'chart_of_account_id',
        'name',
        'numeric_code',
        'is_active',
        'remarks',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function account()
    {
        return $this->belongsTo(ChartOfAccounts::class, 'chart_of_account_id');
    }

    public function ticketLines()
    {
        return $this->hasMany(TicketSaleInvoiceLine::class);
    }
}