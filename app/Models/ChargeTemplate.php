<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChargeTemplate extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'service_category', 'effective_date',
        'default_currency', 'default_exchange_rate', 'is_active', 'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'effective_date' => 'date',
        'default_exchange_rate' => 'decimal:4',
    ];

    public function items()
    {
        return $this->hasMany(ChargeTemplateItem::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
