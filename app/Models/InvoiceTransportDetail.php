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

    // vehicle_id/sector kept for back-compat with lines saved before
    // Vehicle Details became a grid (see InvoiceTransportVehicle) — the
    // Transport tab UI itself no longer sends these at this level, it
    // sends a `vehicles` array instead (one row per vehicle).
    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * Vehicle Details is now a repeatable grid — a transport line can
     * cover more than one vehicle/sector pair.
     */
    public function vehicles()
    {
        return $this->hasMany(InvoiceTransportVehicle::class)->orderBy('sort_order');
    }
}
