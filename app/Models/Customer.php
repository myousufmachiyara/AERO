<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'chart_of_account_id',
        'name',
        'customer_type',
        'contact_person',
        'phone',
        'email',
        'address',
        'cnic_passport',
        'credit_limit',
        'credit_days',
        'opening_balance',
        'remarks',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'credit_limit' => 'decimal:2',
        'opening_balance' => 'decimal:2',
    ];

    public function account()
    {
        return $this->belongsTo(ChartOfAccounts::class, 'chart_of_account_id');
    }
}
