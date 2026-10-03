<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceHotelDetail extends Model
{
    protected $fillable = [
        'service_line_id', 'hotel_id', 'hotel_room_id', 'room_view_id', 'check_in', 'check_out',
        'nights', 'room_qty', 'extra_bed_qty', 'booking_name',
        // Client fix round 2: dual receivable/payable currency+rate (so an
        // invoice can show what currency the vendor vs. the customer is
        // actually being charged in) and per-line agent commission —
        // see the 2026_10_03_070000 migration for why these aren't just
        // reusing service_lines.currency/exchange_rate.
        'receivable_currency', 'receivable_exchange_rate', 'payable_currency', 'payable_exchange_rate',
        'agent_commission_percent', 'agent_commission_amount',
    ];

    protected $casts = [
        'check_in' => 'date',
        'check_out' => 'date',
    ];

    public function serviceLine()
    {
        return $this->belongsTo(ServiceLine::class);
    }

    public function hotel()
    {
        return $this->belongsTo(Hotel::class);
    }

    public function hotelRoom()
    {
        return $this->belongsTo(HotelRoom::class);
    }

    public function roomView()
    {
        return $this->belongsTo(RoomView::class);
    }

    /**
     * Client fix round 2: "Room Details" is now a repeatable grid (a
     * booking can cover more than one room type), so Room Type/Room
     * View/No. of Room/Rate live one row per room here instead of once
     * per hotel booking on this record.
     */
    public function rooms()
    {
        return $this->hasMany(InvoiceHotelRoom::class)->orderBy('sort_order');
    }
}
