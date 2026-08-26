<?php

namespace App\Services;

use App\Models\Audit;
use App\Models\Maintenance;
use Illuminate\Database\Eloquent\Builder;

/**
 * Audits that still need a maintenance request: at least one criterion marked
 * "bad" that no open maintenance covers. Single source for the dashboard
 * widget and the full /pending-maintenance screen so the two never disagree.
 */
class PendingMaintenanceService
{
    public function __construct(protected AuditDashboardFilterService $filters) {}

    /**
     * Scope on audit_values: bad, and not covered by any non-closed maintenance.
     */
    public function pendingValuesFilter(): \Closure
    {
        return function ($q) {
            $q->where('audit_values.value', 'bad')
                ->whereDoesntHave('maintenances', fn ($mq) => $mq->whereNotIn('maintenances.status', [Maintenance::STATUS_CLOSED]));
        };
    }

    /**
     * @param  array  $filters  canonical shape from AuditDashboardFilterService::parseFromRequest
     */
    public function query(array $filters): Builder
    {
        $pending = $this->pendingValuesFilter();

        return $this->filters->applyToAuditQuery(Audit::query(), $filters)
            ->whereHas('values', $pending)
            ->with([
                'space',
                'values' => function ($q) use ($pending) {
                    $pending($q);
                    $q->with('criterion');
                },
            ])
            ->orderBy('audits.audit_date', 'asc');
    }

    public function count(array $filters): int
    {
        return $this->filters->applyToAuditQuery(Audit::query(), $filters)
            ->whereHas('values', $this->pendingValuesFilter())
            ->count();
    }
}
