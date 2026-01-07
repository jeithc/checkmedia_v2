<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Orchid\Screen\AsSource;

class AdvertisingSpace extends Model
{
    use HasFactory, AsSource;

    protected $fillable = [
        'external_code',
        'provider',
        'type',
        'category',
        'ownership',
        'is_third_party',
        'third_party_user_id',
        'third_party_modified_at',
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

    public function activityLogs()
    {
        return $this->hasMany(SpaceActivityLog::class);
    }

    /**
     * Get the booking for a specific date (or current date if null)
     */
    public function getBookingForDate($date = null)
    {
        $date = $date ? \Carbon\Carbon::parse($date) : now();
        $weekData = \App\Models\Audit::getCalendarYearAndWeek($date);

        return $this->bookings()
            ->where('year', $weekData['year'])
            ->where('week', $weekData['week'])
            ->first();
    }
}
