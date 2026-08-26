<?php

namespace App\Livewire;

use App\Models\AdvertisingSpace;
use App\Models\Audit;
use App\Models\AuditValue;
use App\Models\Maintenance;
use App\Services\AuditDashboardFilterService;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class AdminDashboard extends Component
{
    use WithPagination;

    #[Url(as: 'from')]
    public $dateFrom;

    #[Url(as: 'to')]
    public $dateTo;

    #[Url(as: 'externalCode', except: '')]
    public string $externalCode = '';

    #[Url(as: 'city', except: '')]
    public string $city = '';

    #[Url(as: 'producto', except: '')]
    public string $producto = '';

    #[Url(as: 'category', except: '')]
    public string $category = '';

    #[Url(as: 'maintenanceType', except: '')]
    public string $maintenanceType = '';

    #[Url(as: 'status', except: '')]
    public string $status = '';

    public function mount()
    {
        if (! $this->dateFrom) {
            $this->dateFrom = now()->startOfWeek()->format('Y-m-d');
        }
        if (! $this->dateTo) {
            $this->dateTo = now()->endOfWeek()->format('Y-m-d');
        }
    }

    protected function buildFilters(): array
    {
        return [
            'external_code' => $this->externalCode ?: null,
            'city' => $this->city ?: null,
            'producto' => $this->producto ?: null,
            'category' => $this->category ?: null,
            'maintenance_type' => $this->maintenanceType ?: null,
            'status' => $this->status ?: null,
            'date_from' => $this->dateFrom ?: null,
            'date_to' => $this->dateTo ?: null,
        ];
    }

    public function render(AuditDashboardFilterService $filterService)
    {
        $dateFrom = $this->dateFrom;
        $dateTo = $this->dateTo;
        $filters = $this->buildFilters();

        $auditBase = fn () => $filterService->applyToAuditQuery(Audit::query(), $filters);

        // "Con novedades" = audits que actualmente están bad O tuvieron al menos un audit_value
        // vinculado a una maintenance (historial preservado aunque ya se haya resuelto).
        $hasIssueExpr = fn ($q) => $q->where(function ($outer) {
            $outer->where('audits.general_status', 'bad')
                ->orWhereExists(function ($sub) {
                    $sub->from('audit_values as av')
                        ->join('maintenance_audit_value as mav', 'mav.audit_value_id', '=', 'av.id')
                        ->whereColumn('av.audit_id', 'audits.id');
                });
        });

        $auditsWithIssues = $hasIssueExpr(clone $auditBase())->count();

        $criticalAudits = (clone $auditBase())
            ->where('audits.general_status', 'bad')
            ->whereNull('audits.resolved_at')
            ->count();

        $totalAuditsInRange = $auditBase()->count();

        $generalAudits = (clone $auditBase())->where('audits.audit_type', Audit::TYPE_GENERAL)->count();
        $structuralAudits = (clone $auditBase())->where('audits.audit_type', Audit::TYPE_STRUCTURAL)->count();

        $hasNonDateFilter = (bool) ($this->externalCode || $this->city || $this->producto
            || $this->category || $this->maintenanceType || $this->status);

        $isDefaultWeek = $dateFrom === now()->startOfWeek()->format('Y-m-d')
            && $dateTo === now()->endOfWeek()->format('Y-m-d')
            && ! $hasNonDateFilter;

        $maintenanceBase = fn () => $filterService->applyToMaintenanceQuery(Maintenance::query(), $filters);

        $spaceQuery = AdvertisingSpace::query();
        if (! empty($filters['external_code'])) {
            $spaceQuery->where('external_code', 'like', '%'.$filters['external_code'].'%');
        }
        if (! empty($filters['city'])) {
            $spaceQuery->where('city', $filters['city']);
        }
        if (! empty($filters['producto'])) {
            $spaceQuery->ofBusinessUnit($filters['producto']);
        }
        $totalSpaces = (clone $spaceQuery)->count();

        $metrics = [
            'total_spaces' => [
                'label' => 'Espacios Publicitarios',
                'value' => number_format($totalSpaces),
                'subtext' => 'activos',
                'color' => 'neutral',
            ],
            'audits_week' => [
                'label' => 'Auditorías del Período',
                'value' => number_format($totalAuditsInRange),
                'subtext' => number_format($generalAudits).' generales · '.number_format($structuralAudits).' estructurales',
                'color' => 'neutral',
            ],
            'audits_with_issues' => [
                'label' => 'Auditorías con Errores',
                'value' => number_format($auditsWithIssues),
                'subtext' => $criticalAudits > 0 ? $criticalAudits.' sin resolver' : 'todas resueltas',
                'color' => $auditsWithIssues > 0 ? 'danger' : 'success',
                'href' => '#auditorias-periodo',
            ],
            'pending_maint' => [
                'label' => 'Mantenimientos por Atender',
                'value' => number_format((clone $maintenanceBase())->whereNotIn('maintenances.status', [Maintenance::STATUS_CLOSED])->count()),
                'subtext' => 'abiertos actualmente',
                'color' => 'warning',
                'href' => route('platform.maintenances'),
            ],
        ];

        // --- KPI: Novedades Abiertas vs Cerradas ---
        // Una novedad = audit_value vinculado a maintenance via pivot. Una maintenance puede cubrir varios audit_values.
        $novedadesBase = fn () => (clone $maintenanceBase())
            ->join('maintenance_audit_value as mav', 'mav.maintenance_id', '=', 'maintenances.id')
            ->join('audit_values as av', 'av.id', '=', 'mav.audit_value_id');

        $openMaintenances = $novedadesBase()
            ->whereNotIn('maintenances.status', [Maintenance::STATUS_CLOSED])
            ->distinct('av.id')
            ->count('av.id');

        // Cancelled-batch rows are STATUS_CLOSED but no work was done: keep them
        // out of completion KPIs or a cancelled 58-space batch reads as 58 closures.
        $closedMaintenances = $novedadesBase()
            ->completedWork()
            ->distinct('av.id')
            ->count('av.id');

        $totalMaintenances = $openMaintenances + $closedMaintenances;

        // --- KPI: Average closure time (days) — single SQL aggregate, driver-portable ---
        $driver = DB::connection()->getDriverName();
        $diffExpr = $driver === 'sqlite'
            ? 'AVG(julianday(closed_at) - julianday(requested_at))'
            : 'AVG(DATEDIFF(closed_at, requested_at))';

        $avgClosureRaw = (clone $maintenanceBase())
            ->completedWork()
            ->whereNotNull('maintenances.closed_at')
            ->whereNotNull('maintenances.requested_at')
            ->selectRaw("$diffExpr as avg_days")
            ->value('avg_days');

        $avgClosureDays = $avgClosureRaw !== null ? round((float) $avgClosureRaw, 1) : null;

        // --- KPI: Compliance rate (audits without issues / total) ---
        $goodAudits = $totalAuditsInRange - $auditsWithIssues;
        $complianceRate = $totalAuditsInRange > 0
            ? round(($goodAudits / $totalAuditsInRange) * 100, 1)
            : null;

        $kpis = [
            'open_maintenances' => $openMaintenances,
            'closed_maintenances' => $closedMaintenances,
            'total_maintenances' => $totalMaintenances,
            'avg_closure_days' => $avgClosureDays,
            'compliance_rate' => $complianceRate,
            'good_audits' => $goodAudits,
            'bad_audits' => $auditsWithIssues,
            'total_audits' => $totalAuditsInRange,
        ];

        // Recent audits (top 10, prioritize bad)
        $recentAudits = (clone $auditBase())->with('space', 'user')
            ->orderByRaw("CASE WHEN audits.general_status = 'bad' THEN 1 ELSE 2 END")
            ->orderBy('audits.audit_date', 'desc')
            ->limit(10)
            ->get();

        // --- Chart: Criterios con más fallas ---
        // Una "falla" = audit_value que está bad O fue resuelto via maintenance (pivot).
        // El flip bad→good al cerrar maintenance no debe ocultarlo del histórico.
        $criteriaFailures = AuditValue::query()
            ->whereIn('audit_values.audit_id', (clone $auditBase())->select('audits.id'))
            ->join('audit_criteria', 'audit_values.audit_criterion_id', '=', 'audit_criteria.id')
            ->leftJoin('maintenance_audit_value as mav', 'mav.audit_value_id', '=', 'audit_values.id')
            ->where(function ($q) {
                $q->where('audit_values.value', 'bad')
                    ->orWhereNotNull('mav.maintenance_id');
            })
            ->select('audit_criteria.name', DB::raw('COUNT(DISTINCT audit_values.id) as total'))
            ->groupBy('audit_criteria.name')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        // --- Chart: Top espacios con errores (incluye históricos via pivot) ---
        $topBadSpaces = $hasIssueExpr(clone $auditBase())
            ->select('audits.advertising_space_id', DB::raw('COUNT(*) as total'))
            ->groupBy('audits.advertising_space_id')
            ->orderByDesc('total')
            ->limit(5)
            ->with('space')
            ->get()
            ->map(fn ($a) => [
                'code' => $a->space->external_code ?? '—',
                'city' => $a->space->city ?? '—',
                'type' => $a->space->type ?? '—',
                'total' => $a->total,
            ]);

        // --- Chart: Mantenimientos por estado (filtered, solo correctivos) ---
        $maintQuery = $filterService->applyToMaintenanceQuery(Maintenance::query(), $filters);
        $maintByStatus = (clone $maintQuery)
            ->where('maintenances.type', Maintenance::TYPE_CORRECTIVE)
            ->select('maintenances.status', DB::raw('COUNT(*) as total'))
            ->groupBy('maintenances.status')
            ->pluck('total', 'status');

        // --- Chart: Pendientes por solicitar mantenimiento ---
        // Auditorías con audit_values "bad" no cubiertos por mantenimiento abierto.
        $pendingFilter = function ($q) {
            $q->where('audit_values.value', 'bad')
                ->whereDoesntHave('maintenances', fn ($mq) => $mq->whereNotIn('maintenances.status', [Maintenance::STATUS_CLOSED])
                );
        };

        $pendingRequisitions = (clone $auditBase())
            ->whereHas('values', $pendingFilter)
            ->with([
                'space',
                'values' => function ($q) use ($pendingFilter) {
                    $pendingFilter($q);
                    $q->with('criterion');
                },
            ])
            ->orderBy('audits.audit_date', 'asc')
            ->limit(20)
            ->get()
            ->map(function (Audit $a) {
                $criteria = $a->values
                    ->pluck('criterion.name')
                    ->filter()
                    ->unique()
                    ->values()
                    ->join(', ');

                return [
                    'audit_id' => $a->id,
                    'space_code' => $a->space?->external_code ?? '—',
                    'city' => $a->space?->city ?? '—',
                    'criteria' => $criteria !== '' ? $criteria : '—',
                    'audit_date' => $a->audit_date,
                    'days_waiting' => $a->audit_date ? (int) floor($a->audit_date->diffInDays(now())) : 0,
                ];
            });

        $pendingTotal = (clone $auditBase())
            ->whereHas('values', $pendingFilter)
            ->count();

        return view('livewire.admin-dashboard', [
            'metrics' => $metrics,
            'kpis' => $kpis,
            'recentAudits' => $recentAudits,
            'isDefaultWeek' => $isDefaultWeek,
            'criteriaFailures' => $criteriaFailures,
            'topBadSpaces' => $topBadSpaces,
            'maintByStatus' => $maintByStatus,
            'pendingRequisitions' => $pendingRequisitions,
            'pendingTotal' => $pendingTotal,
            'filterOptions' => [
                'cities' => $filterService->cities(),
                'productos' => $filterService->productos(),
                'categories' => $filterService->categories(),
                'auditStatuses' => $filterService->auditStatuses(),
                'maintenanceTypes' => $filterService->maintenanceTypes(),
            ],
            'hasNonDateFilter' => $hasNonDateFilter,
        ]);
    }
}
