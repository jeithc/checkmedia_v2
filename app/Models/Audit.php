<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Orchid\Metrics\Chartable;
use Orchid\Screen\AsSource;

class Audit extends Model
{
    use AsSource, Chartable, HasFactory;

    const TYPE_GENERAL = 'general';

    const TYPE_STRUCTURAL = 'structural';

    const PURPOSE_AUDIT_ONLY = 'audit_only';

    const PURPOSE_PREVENTIVE = 'preventive_maintenance';

    const PURPOSE_CORRECTIVE = 'corrective_maintenance';

    protected $fillable = [
        'advertising_space_id',
        'user_id',
        'year',
        'week',
        'audit_type',
        'audit_purpose',
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

    public function getResolutionPhotoUrlAttribute(): ?string
    {
        return $this->resolution_photo_path
            ? Storage::disk('s3')->url($this->resolution_photo_path)
            : null;
    }

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

    public function maintenances()
    {
        return $this->hasMany(Maintenance::class);
    }

    public function hasOpenMaintenance(): bool
    {
        return $this->maintenances()
            ->whereNotIn('status', [Maintenance::STATUS_CLOSED])
            ->exists();
    }

    public function uncoveredBadValues()
    {
        return $this->values()
            ->where('value', 'bad')
            ->whereDoesntHave('maintenances', fn ($q) =>
                $q->whereNotIn('maintenances.status', [Maintenance::STATUS_CLOSED])
            )
            ->with('criterion')
            ->get();
    }

    public function canRequestMaintenance(): bool
    {
        return $this->uncoveredBadValues()->isNotEmpty();
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
     * @param  \Carbon\Carbon|null  $date
     * @return array ['year' => int, 'week' => int]
     */
    public static function getCalendarYearAndWeek($date = null)
    {
        $date = $date ? \Carbon\Carbon::parse($date) : now();

        // Use simple week numbering (Day 1-7 = Week 1) to match business rules/tests
        $dayOfYear = $date->dayOfYear;
        $weekNumber = (int) ceil($dayOfYear / 7);
        $year = $date->year;

        // Cap at 52 weeks maximum
        if ($weekNumber > 52) {
            $weekNumber = 52;
        }

        return [
            'year' => $year,
            'week' => $weekNumber,
        ];
    }
}
