<?php

namespace App\Services;

use App\Models\AdvertisingSpace;
use App\Models\Audit;
use App\Models\AuditCriterion;
use App\Models\Maintenance;
use App\Models\MaintenanceCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AuditDashboardFilterService
{
    /**
     * Maps user-facing maintenance category keys to audit_criteria.key values.
     * Needed because the maintenance "category" vocabulary differs from the
     * criterion "key" vocabulary used by audit_values.
     */
    private const CATEGORY_TO_CRITERION_KEY = [
        'estructural' => 'structural',
        'ambiental'   => 'environmental',
        'electrico'   => 'electrical',
        'material'    => 'material',
    ];

    public const AUDIT_STATUSES = [
        'good' => 'Bueno',
        'bad' => 'Malo',
    ];

    public const MAINTENANCE_TYPES = [
        Maintenance::TYPE_CORRECTIVE => 'Correctivo',
        Maintenance::TYPE_PREVENTIVE => 'Preventivo',
    ];

    public function parseFromRequest(Request $request): array
    {
        return [
            'external_code' => trim((string) $request->query('externalCode', '')) ?: null,
            'city' => $request->query('city') ?: null,
            'producto' => $request->query('producto') ?: null,
            'category' => $request->query('category') ?: null,
            'maintenance_type' => $request->query('maintenanceType') ?: null,
            'status' => $request->query('status') ?: null,
            'date_from' => $request->query('from') ?: null,
            'date_to' => $request->query('to') ?: null,
            'has_rc' => null,
        ];
    }

    /**
     * Normalize Orchid `filter[…]` nested array (used by AuditDashboardScreen)
     * into the canonical flat shape consumed by applyTo*Query.
     */
    public function parseFromOrchidFilter(array $filter): array
    {
        $date = $filter['date'] ?? [];

        return [
            'external_code'    => null,
            'city'             => $filter['city'] ?? null,
            'producto'         => $filter['producto'] ?? null,
            'category'         => $filter['category'] ?? null,
            'maintenance_type' => $filter['type'] ?? null,
            'status'           => match ($filter['status'] ?? null) {
                'abiertas' => 'open',
                'closed'   => 'closed',
                null, ''   => null,
                default    => $filter['status'],
            },
            'date_from'        => is_array($date) ? ($date['start'] ?? null) : null,
            'date_to'          => is_array($date) ? ($date['end'] ?? null) : null,
            'has_rc'           => $filter['has_rc'] ?? null,
        ];
    }

    public function applyToAuditQuery(Builder $query, array $f): Builder
    {
        $query->when($f['date_from'] ?? null, fn ($q, $v) => $q->whereDate('audits.audit_date', '>=', $v));
        $query->when($f['date_to'] ?? null, fn ($q, $v) => $q->whereDate('audits.audit_date', '<=', $v));

        if (!empty($f['external_code']) || !empty($f['city']) || !empty($f['producto'])) {
            $query->whereHas('space', function (Builder $q) use ($f) {
                if (!empty($f['external_code'])) {
                    $q->where('external_code', 'like', '%'.$f['external_code'].'%');
                }
                if (!empty($f['city'])) {
                    $q->where('city', $f['city']);
                }
                if (!empty($f['producto'])) {
                    $q->where('category', $f['producto']);
                }
            });
        }

        if (!empty($f['category'])) {
            $criterionKey = self::CATEGORY_TO_CRITERION_KEY[$f['category']] ?? null;
            if ($criterionKey) {
                $query->whereHas('values', function (Builder $q) use ($criterionKey) {
                    $q->where('value', 'bad')
                        ->whereHas('criterion', fn (Builder $c) => $c->where('key', $criterionKey));
                });
            }
        }

        if (!empty($f['status'])) {
            $query->where('audits.general_status', $f['status']);
        }

        return $query;
    }

    public function applyToMaintenanceQuery(Builder $query, array $f): Builder
    {
        $query->when($f['date_from'] ?? null, fn ($q, $v) => $q->whereDate('maintenances.requested_at', '>=', $v));
        $query->when($f['date_to'] ?? null, fn ($q, $v) => $q->whereDate('maintenances.requested_at', '<=', $v));

        if (!empty($f['external_code']) || !empty($f['city']) || !empty($f['producto'])) {
            $query->whereHas('advertisingSpace', function (Builder $q) use ($f) {
                if (!empty($f['external_code'])) {
                    $q->where('external_code', 'like', '%'.$f['external_code'].'%');
                }
                if (!empty($f['city'])) {
                    $q->where('city', $f['city']);
                }
                if (!empty($f['producto'])) {
                    $q->where('category', $f['producto']);
                }
            });
        }

        if (!empty($f['category'])) {
            $query->where('maintenances.category', $f['category']);
        }

        if (!empty($f['maintenance_type'])) {
            $query->where('maintenances.type', $f['maintenance_type']);
        }

        if (!empty($f['status'])) {
            if ($f['status'] === 'closed') {
                $query->where('maintenances.status', Maintenance::STATUS_CLOSED);
            } elseif ($f['status'] === 'open') {
                $query->where('maintenances.status', '!=', Maintenance::STATUS_CLOSED);
            }
        }

        if (isset($f['has_rc']) && $f['has_rc'] !== null && $f['has_rc'] !== '') {
            if ((string) $f['has_rc'] === '1') {
                $query->whereNotNull('maintenances.advisual_requisition_id');
            } elseif ((string) $f['has_rc'] === '0') {
                $query->whereNull('maintenances.advisual_requisition_id');
            }
        }

        return $query;
    }

    public function cities(): array
    {
        return Cache::remember('dashboard.filter.cities', 300, fn () =>
            AdvertisingSpace::query()
                ->select('city')
                ->whereNotNull('city')
                ->where('city', '!=', '')
                ->distinct()
                ->orderBy('city')
                ->pluck('city', 'city')
                ->toArray()
        );
    }

    public function productos(): array
    {
        return AdvertisingSpace::query()
            ->select('category')
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->orderBy('category')
            ->pluck('category', 'category')
            ->toArray();
    }

    public function categories(): array
    {
        return MaintenanceCategory::options();
    }

    public function auditStatuses(): array
    {
        return self::AUDIT_STATUSES;
    }

    public function maintenanceTypes(): array
    {
        return self::MAINTENANCE_TYPES;
    }
}
