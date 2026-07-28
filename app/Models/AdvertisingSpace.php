<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Orchid\Filters\Filterable;
use Orchid\Filters\Types\Like;
use Orchid\Screen\AsSource;

class AdvertisingSpace extends Model
{
    use AsSource, Filterable, HasFactory;

    public const BUSINESS_UNITS = [
        'AEROPUERTOS ESTATICOS',
        'AEROPUERTOS DIGITAL',
        'RETAIL',
        'MASIVO - ST',
        'MASIVO - AU',
        'MASIVO - VALLAS ESTATICAS',
        'MASIVO - VALLAS DIGITAL',
    ];

    // Tipos de elemento considerados digitales (para el split estático/digital)
    public const DIGITAL_TYPE_REGEX = '/\b(DIGITAL|LED|PANTALLA|MONITOR|VERTICAL DISPLAY)\b/u';

    protected $allowedFilters = [
        'external_code' => Like::class,
        'city' => Like::class,
        'type' => Like::class,
        'provider' => Like::class,
        'category' => Like::class,
    ];

    protected $allowedSorts = [
        'external_code',
        'city',
        'type',
    ];

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

    public function getBusinessUnitAttribute(): ?string
    {
        $category = mb_strtoupper(trim($this->category ?? ''));

        if ($category === '') {
            return null;
        }

        $digital = (bool) preg_match(self::DIGITAL_TYPE_REGEX, mb_strtoupper($this->type ?? ''));

        if ($category === 'AEROPUERTOS') {
            return $digital ? 'AEROPUERTOS DIGITAL' : 'AEROPUERTOS ESTATICOS';
        }

        if (str_starts_with($category, 'RETAIL')) {
            return 'RETAIL';
        }

        if ($category === 'SISTEMAS DE TRANSPORTE') {
            return 'MASIVO - ST';
        }

        if (str_starts_with($category, 'AMOBLAMIENTO URBANO')) {
            return 'MASIVO - AU';
        }

        if ($category === 'VALLAS') {
            return $digital ? 'MASIVO - VALLAS DIGITAL' : 'MASIVO - VALLAS ESTATICAS';
        }

        return null;
    }

    /**
     * Filtra espacios por unidad de negocio (producto) replicando getBusinessUnitAttribute en SQL.
     * ponytail: aproxima DIGITAL_TYPE_REGEX con LIKEs para ser portable MySQL/SQLite;
     * si un tipo real produce falso positivo, persistir business_unit como columna.
     */
    public function scopeOfBusinessUnit($query, string $unit)
    {
        $digitalLikes = ['%DIGITAL%', '%LED%', '%PANTALLA%', '%MONITOR%', '%VERTICAL DISPLAY%'];

        $isDigital = function ($q) use ($digitalLikes) {
            foreach ($digitalLikes as $like) {
                $q->orWhere('type', 'LIKE', $like);
            }
        };

        $notDigital = function ($q) use ($digitalLikes) {
            $q->whereNull('type')
                ->orWhere(function ($q) use ($digitalLikes) {
                    foreach ($digitalLikes as $like) {
                        $q->where('type', 'NOT LIKE', $like);
                    }
                });
        };

        return match ($unit) {
            'AEROPUERTOS ESTATICOS' => $query->where('category', 'AEROPUERTOS')->where($notDigital),
            'AEROPUERTOS DIGITAL' => $query->where('category', 'AEROPUERTOS')->where($isDigital),
            'RETAIL' => $query->where('category', 'LIKE', 'RETAIL%'),
            'MASIVO - ST' => $query->where('category', 'SISTEMAS DE TRANSPORTE'),
            'MASIVO - AU' => $query->where('category', 'LIKE', 'AMOBLAMIENTO URBANO%'),
            'MASIVO - VALLAS ESTATICAS' => $query->where('category', 'VALLAS')->where($notDigital),
            'MASIVO - VALLAS DIGITAL' => $query->where('category', 'VALLAS')->where($isDigital),
            default => $query->whereRaw('1 = 0'),
        };
    }

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
     * Prefers city-specific rule over national rule; falls back to category match.
     * Kept for backward compatibility — the matrix uses getApplicablePreventiveSchedules().
     */
    public function getPreventiveSchedule(): ?PreventiveSchedule
    {
        return $this->getApplicablePreventiveSchedules()->first();
    }

    /**
     * Get every active PreventiveSchedule that applies to this space.
     * A schedule applies when:
     *   - City matches the space city OR is null (national).
     *   - Unit matches the space category OR is null (covers all units).
     * One preventive audit covers all aspects, so we evaluate every applicable
     * cadence and pick the earliest due_date downstream.
     */
    public function getApplicablePreventiveSchedules(): \Illuminate\Database\Eloquent\Collection
    {
        return PreventiveSchedule::query()
            ->where('is_active', true)
            ->where(function ($q) {
                if ($this->city) {
                    $q->where('city', $this->city)->orWhereNull('city');
                } else {
                    $q->whereNull('city');
                }
            })
            ->where(function ($q) {
                if ($this->category) {
                    $q->where('unit', $this->category)->orWhereNull('unit');
                } else {
                    $q->whereNull('unit');
                }
            })
            ->get();
    }

    /**
     * Calculate preventive maintenance urgency for this space.
     * Returns array with: last_audit_date, due_date, days_remaining, status, status_color.
     */
    public function getPreventiveMatrix(): array
    {
        $lastAudit = $this->lastPreventiveAudit();
        $schedules = $this->getApplicablePreventiveSchedules();

        if ($schedules->isEmpty()) {
            return [
                'last_audit_date' => $lastAudit?->audit_date,
                'due_date' => null,
                'days_remaining' => null,
                'status' => 'SIN FRECUENCIA',
                'status_color' => 'secondary',
            ];
        }

        if (! $lastAudit) {
            return [
                'last_audit_date' => null,
                'due_date' => null,
                'days_remaining' => -999,
                'status' => 'VENCIDO',
                'status_color' => 'danger',
            ];
        }

        $minFrequency = $schedules->min('frequency_days');
        $dueDate = $lastAudit->audit_date->copy()->addDays($minFrequency);
        $daysRemaining = (int) now()->diffInDays($dueDate, false);

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
            'days_remaining' => $daysRemaining,
            'status' => $status,
            'status_color' => $statusColor,
        ];
    }
}
