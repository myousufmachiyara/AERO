<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceOtherDetail extends Model
{
    protected $fillable = ['service_line_id', 'service_id', 'qty'];

    public function serviceLine()
    {
        return $this->belongsTo(ServiceLine::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }
}
