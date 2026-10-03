<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceTransportVehicle extends Model
{
    protected $fillable = [
        'invoice_transport_detail_id', 'vehicle_id', 'sector', 'sort_order',
    ];

    public function transportDetail()
    {
        return $this->belongsTo(InvoiceTransportDetail::class, 'invoice_transport_detail_id');
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }
}
