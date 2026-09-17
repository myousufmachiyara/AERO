<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceTransportDetail extends Model
{
    protected $fillable = ['service_line_id', 'vehicle_id', 'sector', 'booking_name'];

    public function serviceLine()
    {
        return $this->belongsTo(ServiceLine::class);
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }
}
