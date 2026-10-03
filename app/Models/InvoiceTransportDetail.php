<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceTransportDetail extends Model
{
    // reference_no/category and the dual receivable/payable currency+rate +
    // agent commission fields were added for the Transport tab rework
    // (see the 2026_10_03_070200 migration) — same pattern as
    // InvoiceHotelDetail's Hotel round-2 fields.
    protected $fillable = [
        'service_line_id', 'vehicle_id', 'sector', 'booking_name', 'reference_no', 'category',
        'receivable_currency', 'receivable_exchange_rate', 'payable_currency', 'payable_exchange_rate',
        'agent_commission_percent', 'agent_commission_amount',
    ];

    public function serviceLine()
    {
        return $this->belongsTo(ServiceLine::class);
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }
}
