<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Orchid\Metrics\Chartable;
use Orchid\Screen\AsSource;

class Audit extends Model
{
    use HasFactory, Chartable, AsSource;

    protected $fillable = [
        'advertising_space_id',
        'user_id',
        'year',
        'week',
        'audit_date',
        'general_status',
        'observation',
        'resolution_photo_path',
        'resolution_comment',
        'resolved_at',
    ];

    protected $casts = [
        'audit_date' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function space()
    {
        return $this->belongsTo(AdvertisingSpace::class, 'advertising_space_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function values()
    {
        return $this->hasMany(AuditValue::class);
    }

    public function photos()
    {
        return $this->hasMany(AuditPhoto::class);
    }

    public function comments()
    {
        return $this->hasMany(AuditComment::class);
    }

    /**
     * Helper to get the total score or status from values
     */
    public function calculateGeneralStatus()
    {
        // Example logic: if any criterion is bad -> bad
        $hasIssues = $this->values()->where('value', 'bad')->exists();
        $this->update(['general_status' => $hasIssues ? 'bad' : 'good']);
    }

    /**
     * Get calendar-based year and week for a given date.
     * Uses ISO-8601 week numbering (weeks start on Monday).
     * Adjusts year for edge cases (late December / early January).
     * 
     * @param \Carbon\Carbon|null $date
     * @return array ['year' => int, 'week' => int]
     */
    public static function getCalendarYearAndWeek($date = null)
    {
        $date = $date ? \Carbon\Carbon::parse($date) : now();

        // Use ISO week number (1-53, weeks start Monday)
        $weekNumber = (int) $date->format('W'); // ISO-8601 week number
        $isoYear = (int) $date->format('o');    // ISO-8601 week-numbering year

        // Cap at 52 weeks maximum (business requirement)
        if ($weekNumber > 52) {
            $weekNumber = 52;
        }

        return [
            'year' => $isoYear,
            'week' => $weekNumber
        ];
    }
}
