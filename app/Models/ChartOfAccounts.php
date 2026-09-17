<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ChartOfAccounts extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'shoa_id',
        'name',
        'account_code',
        'account_type',
        'receivables',
        'payables',
        'credit_limit',
        'opening_date',
        'remarks',
        'address',
        'contact_no',
        'created_by',
        'updated_by',
    ];

    // Define the relationship with SubHeadOfAccounts (belongs to)
    public function subHeadOfAccount()
    {
        return $this->belongsTo(SubHeadOfAccounts::class, 'shoa_id', 'id');
    }

    public function purchaseInvoices()
    {
        return $this->hasMany(PurchaseInvoice::class, 'vendor_id');
    }

    public function supplier()
    {
        return $this->hasOne(Supplier::class, 'chart_of_account_id');
    }

    public function customer()
    {
        return $this->hasOne(Customer::class, 'chart_of_account_id');
    }

    /**
     * Same account-code scheme used by COAController::store() —
     * head-id + zero-padded sub-head-id + zero-padded sequence within
     * that sub-head, e.g. sub-head 5 under head 2 -> "205001", "205002", ...
     * Centralised here so any part of the app that needs to provision a
     * chart_of_accounts row (e.g. Supplier/Customer auto-linking) stays in
     * sync with the codes COA's own screen generates.
     */
    public static function nextAccountCode(int $shoaId): string
    {
        $subHead = SubHeadOfAccounts::findOrFail($shoaId);
        $prefix  = $subHead->hoa_id . str_pad((string) $subHead->id, 2, '0', STR_PAD_LEFT);

        $nextNumber = static::withTrashed()
            ->where('account_code', 'like', $prefix . '%')
            ->pluck('account_code')
            ->map(fn ($code) => (int) substr($code, strlen($prefix)))
            ->sort()
            ->last();

        return $prefix . str_pad((string) (($nextNumber ?? 0) + 1), 3, '0', STR_PAD_LEFT);
    }

}
