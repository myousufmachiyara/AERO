<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class HotelRoom extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'hotel_id', 'room_type', 'room_view_id', 'capacity',
        'default_rate', 'currency', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'default_rate' => 'decimal:2',
    ];

    public function hotel()
    {
        return $this->belongsTo(Hotel::class);
    }

    public function roomView()
    {
        return $this->belongsTo(RoomView::class, 'room_view_id');
    }
}
