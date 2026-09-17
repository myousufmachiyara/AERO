<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChargeType extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'code', 'calculation_type', 'default_value', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
        'default_value' => 'decimal:4',
    ];
}
