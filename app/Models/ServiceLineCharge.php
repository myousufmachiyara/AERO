<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceLineCharge extends Model
{
    protected $fillable = ['service_line_id', 'charge_type_id', 'value', 'computed_amount'];

    protected $casts = [
        'value' => 'decimal:4',
        'computed_amount' => 'decimal:2',
    ];

    public function serviceLine()
    {
        return $this->belongsTo(ServiceLine::class);
    }

    public function chargeType()
    {
        return $this->belongsTo(ChargeType::class);
    }
}
