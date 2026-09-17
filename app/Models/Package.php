<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Package extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'category', 'duration_days', 'base_price', 'currency',
        'inclusions', 'is_active', 'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'base_price' => 'decimal:2',
    ];

    public function services()
    {
        return $this->hasMany(PackageService::class);
    }
}
