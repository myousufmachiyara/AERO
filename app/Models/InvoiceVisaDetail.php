<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceVisaDetail extends Model
{
    protected $fillable = ['service_line_id', 'visa_type_id', 'apply_date', 'expiry_date', 'reference_no'];

    protected $casts = [
        'apply_date' => 'date',
        'expiry_date' => 'date',
    ];

    public function serviceLine()
    {
        return $this->belongsTo(ServiceLine::class);
    }

    public function visaType()
    {
        return $this->belongsTo(VisaType::class);
    }
}
