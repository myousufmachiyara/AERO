<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PackageService extends Model
{
    protected $fillable = [
        'package_id', 'service_type', 'hotel_room_id', 'vehicle_id',
        'visa_type_id', 'service_id', 'qty', 'notes',
    ];

    public function package()
    {
        return $this->belongsTo(Package::class);
    }

    public function hotelRoom()
    {
        return $this->belongsTo(HotelRoom::class);
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function visaType()
    {
        return $this->belongsTo(VisaType::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * Human label for whichever reference this row carries, used both in
     * the Package screen and when pre-filling a Quotation's description.
     */
    public function label(): string
    {
        return match ($this->service_type) {
            'hotel' => $this->hotelRoom ? "{$this->hotelRoom->hotel->name} — {$this->hotelRoom->room_type}" : 'Hotel',
            'transport' => $this->vehicle->name ?? 'Transport',
            'visa' => $this->visaType->name ?? 'Visa',
            'other' => $this->service->name ?? 'Other Service',
            default => ucfirst($this->service_type),
        };
    }
}
