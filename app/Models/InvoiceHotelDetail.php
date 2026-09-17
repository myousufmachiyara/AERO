<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceHotelDetail extends Model
{
    protected $fillable = [
        'service_line_id', 'hotel_id', 'hotel_room_id', 'check_in', 'check_out',
        'nights', 'room_qty', 'extra_bed_qty', 'booking_name',
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
}
