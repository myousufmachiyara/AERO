<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceTicketFlight extends Model
{
    protected $fillable = [
        'invoice_ticket_detail_id', 'city', 'flight_no', 'dep_date',
        'dep_time', 'arr_time', 'fare_basis', 'sort_order',
    ];

    protected $casts = ['dep_date' => 'date'];

    public function ticketDetail()
    {
        return $this->belongsTo(InvoiceTicketDetail::class, 'invoice_ticket_detail_id');
    }
}
