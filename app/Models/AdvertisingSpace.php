<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdvertisingSpace extends Model
{
    use HasFactory;

    protected $fillable = [
        'external_code',
        'provider',
        'type',
        'illumination_type',
        'city',
        'location_name',
        'address',
        'zone',
        'location',
    ];

    public function audits()
    {
        return $this->hasMany(Audit::class);
    }

    public function bookings()
    {
        return $this->hasMany(CommercialBooking::class);
    }

    public function maintenances()
    {
        return $this->hasMany(Maintenance::class);
    }

    /**
     * Get the booking for a specific date (or current date if null)
     */
    public function getBookingForDate($date = null)
    {
        $date = $date ? \Carbon\Carbon::parse($date) : now();
        $year = $date->year;
        $week = $date->weekOfYear;

        return $this->bookings()
            ->where('year', $year)
            ->where('week', $week)
            ->first();
    }
}
