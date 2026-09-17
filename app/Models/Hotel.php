<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Hotel extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'city', 'star_rating', 'supplier_id',
        'address', 'contact_no', 'remarks', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function rooms()
    {
        return $this->hasMany(HotelRoom::class);
    }
}
