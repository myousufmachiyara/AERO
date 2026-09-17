<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Quotation extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'quotation_no', 'quotation_date', 'valid_until', 'customer_id',
        'package_id', 'visit_type', 'status', 'converted_invoice_type',
        'converted_invoice_id', 'remarks', 'total_receivable',
        'total_payable', 'total_income', 'created_by',
    ];

    protected $casts = [
        'quotation_date' => 'date',
        'valid_until' => 'date',
    ];

    public const STATUSES = ['draft', 'sent', 'approved', 'rejected', 'expired', 'converted'];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function package()
    {
        return $this->belongsTo(Package::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function serviceLines()
    {
        return $this->morphMany(ServiceLine::class, 'linkable')->orderBy('sort_order');
    }

    public function passengers()
    {
        return $this->morphMany(TravelPassenger::class, 'passengerable');
    }

    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'sent'], true);
    }

    /**
     * Roll the header totals up from the current service lines. Called
     * inside the same transaction as any line create/update/delete.
     */
    public function recalculateTotals(): void
    {
        $lines = $this->serviceLines()->get();

        $this->total_receivable = $lines->sum('receivable_l_amount');
        $this->total_payable = $lines->sum('payable_l_amount');
        $this->total_income = $lines->sum('income_l_amount');
        $this->save();
    }
}
