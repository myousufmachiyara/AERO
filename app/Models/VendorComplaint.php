<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VendorComplaint extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'supplier_id', 'service_type', 'customer_id', 'reference', 'complaint_date',
        'severity', 'status', 'description', 'resolution_notes', 'created_by',
    ];

    protected $casts = ['complaint_date' => 'date'];

    public const SERVICE_TYPES = ['ticket', 'hotel', 'transport', 'visa', 'other'];
    public const SEVERITIES = ['low', 'medium', 'high'];
    public const STATUSES = ['open', 'resolved', 'dismissed'];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
