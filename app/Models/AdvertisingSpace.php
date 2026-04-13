<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Orchid\Screen\AsSource;
use App\Models\Audit;
use App\Models\PreventiveSchedule;

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
     * Get the latest audit for the space.
     */
    public function latestAudit()
    {
        return $this->hasOne(Audit::class)->latestOfMany();
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

    /**
     * Get the most recent preventive audit for this space.
     */
    public function lastPreventiveAudit(): ?Audit
    {
        return $this->audits()
            ->where('audit_purpose', 'preventive_maintenance')
            ->orderByDesc('audit_date')
            ->first();
    }

    /**
     * Get the applicable PreventiveSchedule for this space.
     * Prefers city-specific rule over national rule.
     */
    public function getPreventiveSchedule(): ?PreventiveSchedule
    {
        return PreventiveSchedule::where('element_type', $this->type)
            ->where('is_active', true)
            ->orderByDesc('city') // city-specific (non-null) comes first
            ->when($this->city, fn($q) => $q->where(fn($subQ) =>
                $subQ->where('city', $this->city)->orWhereNull('city')
            ), fn($q) => $q->whereNull('city'))
            ->first();
    }

    /**
     * Calculate preventive maintenance urgency for this space.
     * Returns array with: last_audit_date, due_date, days_remaining, status, status_color.
     */
    public function getPreventiveMatrix(): array
    {
        $lastAudit = $this->lastPreventiveAudit();
        $schedule = $this->getPreventiveSchedule();

        if (!$schedule) {
            // No schedule found — treat as critical
            return [
                'last_audit_date' => $lastAudit?->audit_date,
                'due_date' => null,
                'days_remaining' => -999,
                'status' => 'VENCIDO',
                'status_color' => 'danger',
            ];
        }

        if (!$lastAudit) {
            // No audit yet — treat as already overdue
            return [
                'last_audit_date' => null,
                'due_date' => null,
                'days_remaining' => -999,
                'status' => 'VENCIDO',
                'status_color' => 'danger',
            ];
        }

        $dueDate = $lastAudit->audit_date->copy()->addDays($schedule->frequency_days);
        $daysRemaining = now()->diffInDays($dueDate, false); // false = signed

        if ($daysRemaining < 0) {
            $status = 'VENCIDO';
            $statusColor = 'danger';
        } elseif ($daysRemaining <= 30) {
            $status = 'CRÍTICO';
            $statusColor = 'warning';
        } else {
            $status = 'OK';
            $statusColor = 'success';
        }

        return [
            'last_audit_date' => $lastAudit->audit_date,
            'due_date' => $dueDate,
            'days_remaining' => (int) $daysRemaining,
            'status' => $status,
            'status_color' => $statusColor,
        ];
    }
}
