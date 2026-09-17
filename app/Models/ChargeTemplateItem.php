<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChargeTemplateItem extends Model
{
    protected $fillable = ['charge_template_id', 'charge_type_id', 'value'];

    protected $casts = ['value' => 'decimal:4'];

    public function template()
    {
        return $this->belongsTo(ChargeTemplate::class, 'charge_template_id');
    }

    public function chargeType()
    {
        return $this->belongsTo(ChargeType::class, 'charge_type_id');
    }
}
