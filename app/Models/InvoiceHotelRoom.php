<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceHotelRoom extends Model
{
    protected $fillable = [
        'invoice_hotel_detail_id', 'hotel_room_id', 'room_view_id', 'qty', 'rate', 'cost', 'total_amount', 'total_cost', 'sort_order',
    ];

    /**
     * Rate/Cost are per room per night. Returns [rooms total, rooms cost]:
     *   Single Room Total = Rate × Nights, Rooms Total = that × No. of Rooms
     *   Single Room Cost  = Cost × Nights, Rooms Cost  = that × No. of Rooms
     */
    public static function totalsFor($qty, $rate, $cost, $nights): array
    {
        $qty = (float) $qty;
        $nights = (float) $nights;

        return [
            round(round((float) $rate * $nights, 2) * $qty, 2),
            round(round((float) $cost * $nights, 2) * $qty, 2),
        ];
    }

    public function hotelDetail()
    {
        return $this->belongsTo(InvoiceHotelDetail::class, 'invoice_hotel_detail_id');
    }

    public function hotelRoom()
    {
        return $this->belongsTo(HotelRoom::class);
    }

    public function roomView()
    {
        return $this->belongsTo(RoomView::class);
    }
}
