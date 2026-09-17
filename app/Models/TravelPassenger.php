<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TravelPassenger extends Model
{
    protected $fillable = [
        'passengerable_type', 'passengerable_id', 'name',
        'passport_no_nic', 'pax_type', 'nationality', 'dob',
    ];

    protected $casts = ['dob' => 'date'];

    public function passengerable()
    {
        return $this->morphTo();
    }
}
