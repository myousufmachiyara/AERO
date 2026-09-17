<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceTicketDetail extends Model
{
    protected $fillable = [
        'service_line_id', 'pnr', 'gds', 'airline', 'ticket_no',
        'ticket_type', 'sector', 'tour_code', 'issue_date',
    ];

    protected $casts = ['issue_date' => 'date'];

    public function serviceLine()
    {
        return $this->belongsTo(ServiceLine::class);
    }

    public function flights()
    {
        return $this->hasMany(InvoiceTicketFlight::class)->orderBy('sort_order');
    }
}
