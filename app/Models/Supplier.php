<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'chart_of_account_id',
        'name',
        'type',
        'contact_person',
        'phone',
        'email',
        'address',
        'license_no',
        'ntn',
        'credit_limit',
        'credit_days',
        'opening_balance',
        'is_flagged',
        'flagged_reason',
        'complaint_threshold_override',
        'remarks',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_flagged' => 'boolean',
        'is_active'  => 'boolean',
        'credit_limit' => 'decimal:2',
        'opening_balance' => 'decimal:2',
    ];

    public function account()
    {
        return $this->belongsTo(ChartOfAccounts::class, 'chart_of_account_id');
    }

    public function complaints()
    {
        return $this->hasMany(VendorComplaint::class);
    }

    public function payments()
    {
        return $this->hasMany(InvoicePayment::class);
    }

    /** Total paid to this vendor to date — see InvoicePayment (direction = payment). */
    public function totalPaid(): float
    {
        return (float) $this->payments()->where('direction', 'payment')->sum('amount');
    }

    public function effectiveComplaintThreshold(): int
    {
        return $this->complaint_threshold_override ?? (int) config('travel.complaint_threshold.max_count');
    }

    protected function complaintPeriodDays(): int
    {
        return (int) config('travel.complaint_threshold.period_days');
    }

    /**
     * Open/unresolved complaints raised within the configured rolling
     * window — the count the auto-flag rule actually judges a vendor by.
     * "Dismissed" complaints (found to be unfounded) never count.
     */
    public function recentComplaintsCount(): int
    {
        return $this->complaints()
            ->where('status', '!=', 'dismissed')
            ->where('complaint_date', '>=', now()->subDays($this->complaintPeriodDays()))
            ->count();
    }

    /**
     * Complaints-per-service-line-delivered, as a percentage — a rate
     * rather than a raw count, so a high-volume vendor with 3 complaints
     * out of 500 bookings isn't judged the same as one with 3 out of 10.
     * Returns null when the vendor has no delivered lines yet (avoids a
     * divide-by-zero misreading as a 0% — "no data" and "spotless record"
     * are different things).
     */
    public function complaintRatePercent(): ?float
    {
        $totalLines = ServiceLine::where('supplier_id', $this->id)->count();
        if ($totalLines === 0) {
            return null;
        }

        $totalComplaints = $this->complaints()->where('status', '!=', 'dismissed')->count();

        return round(($totalComplaints / $totalLines) * 100, 1);
    }

    /**
     * Recompute is_flagged/flagged_reason from the current complaint
     * count vs this vendor's effective threshold. Called after any
     * complaint is created, updated, (re)opened, dismissed or deleted —
     * see VendorComplaintController — so the flag never goes stale.
     */
    public function refreshComplaintFlag(): void
    {
        $count = $this->recentComplaintsCount();
        $threshold = $this->effectiveComplaintThreshold();

        if ($count >= $threshold) {
            $this->is_flagged = true;
            $this->flagged_reason = "{$count} complaint(s) in the last {$this->complaintPeriodDays()} days (threshold: {$threshold})";
        } else {
            $this->is_flagged = false;
            $this->flagged_reason = null;
        }

        $this->save();
    }
}
