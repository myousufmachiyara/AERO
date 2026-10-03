<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceHotelRoom extends Model
{
    protected $fillable = [
        'invoice_hotel_detail_id', 'hotel_room_id', 'room_view_id', 'qty', 'rate', 'total_amount', 'sort_order',
    ];

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
