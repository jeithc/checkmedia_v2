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

    public function filter()
    {
        if ($this->dateFrom && $this->dateTo && $this->dateFrom > $this->dateTo) {
            $this->dateTo = $this->dateFrom;
        }

        return redirect()->route('platform.main', array_filter([
            'from' => $this->dateFrom,
            'to' => $this->dateTo,
            'externalCode' => $this->externalCode,
            'city' => $this->city,
            'producto' => $this->producto,
            'category' => $this->category,
            'maintenanceType' => $this->maintenanceType,
            'status' => $this->status,
        ], fn ($v) => $v !== null && $v !== ''));
    }

    public function resetFilters()
    {
        return redirect()->route('platform.main');
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

        $auditsWithIssues = (clone $auditBase())->where('audits.general_status', 'bad')->count();

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

        $metrics = [
            'total_spaces' => [
                'label' => 'Espacios Publicitarios',
                'value' => number_format(AdvertisingSpace::count()),
                'subtext' => 'Total Activos',
                'icon' => 'bs.geo-alt',
                'color' => 'primary',
            ],
            'audits_week' => [
                'label' => 'Auditorías (Período)',
                'value' => number_format($totalAuditsInRange),
                'subtext' => 'En rango seleccionado',
                'icon' => 'bs.check-circle',
                'color' => 'primary',
            ],
            'audits_with_issues' => [
                'label' => 'Auditorías con Errores',
                'value' => number_format($auditsWithIssues),
                'subtext' => $criticalAudits > 0 ? $criticalAudits.' críticas sin resolver' : 'En rango seleccionado',
                'icon' => 'bs.exclamation-triangle',
                'color' => $auditsWithIssues > 0 ? 'danger' : 'success',
            ],
            'pending_maint' => [
                'label' => 'Mantenimientos Pend.',
                'value' => number_format(Maintenance::whereNotIn('status', [Maintenance::STATUS_CLOSED])->count()),
                'subtext' => 'Por Atender',
                'icon' => 'bs.tools',
                'color' => 'primary',
            ],
            'audits_general' => [
                'label' => 'Auditorías Generales',
                'value' => number_format($generalAudits),
                'subtext' => 'Perfil auditor general',
                'icon' => 'bs.clipboard-check',
                'color' => 'primary',
            ],
            'audits_structural' => [
                'label' => 'Auditorías Estructurales',
                'value' => number_format($structuralAudits),
                'subtext' => 'Perfil auditor estructural',
                'icon' => 'bs.building-gear',
                'color' => 'warning',
            ],
        ];

        // --- KPI: Open vs Closed maintenances ---
        $openMaintenances = Maintenance::whereNotIn('status', [Maintenance::STATUS_CLOSED])->count();
        $closedMaintenances = Maintenance::where('status', Maintenance::STATUS_CLOSED)->count();
        $totalMaintenances = $openMaintenances + $closedMaintenances;

        // --- KPI: Average closure time (days) — single SQL aggregate, driver-portable ---
        $driver = DB::connection()->getDriverName();
        $diffExpr = $driver === 'sqlite'
            ? 'AVG(julianday(closed_at) - julianday(requested_at))'
            : 'AVG(DATEDIFF(closed_at, requested_at))';

        $avgClosureRaw = Maintenance::where('status', Maintenance::STATUS_CLOSED)
            ->whereNotNull('closed_at')
            ->whereNotNull('requested_at')
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
        ];

        // Recent audits (top 10, prioritize bad)
        $recentAudits = (clone $auditBase())->with('space', 'user')
            ->orderByRaw("CASE WHEN audits.general_status = 'bad' THEN 1 ELSE 2 END")
            ->orderBy('audits.audit_date', 'desc')
            ->limit(10)
            ->get();

        // Paginated audit list for the table
        $audits = (clone $auditBase())->with('space', 'user')
            ->orderBy('audits.audit_date', 'desc')
            ->paginate(25);

        // --- Chart: Criterios con más fallas ---
        $criteriaFailures = AuditValue::whereIn('audit_id', (clone $auditBase())->select('audits.id'))
            ->where('value', 'bad')
            ->join('audit_criteria', 'audit_values.audit_criterion_id', '=', 'audit_criteria.id')
            ->select('audit_criteria.name', DB::raw('COUNT(*) as total'))
            ->groupBy('audit_criteria.name')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        // --- Chart: Top espacios con errores ---
        $topBadSpaces = (clone $auditBase())
            ->where('audits.general_status', 'bad')
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

        // --- Chart: Mantenimientos por estado (filtered) ---
        $maintQuery = $filterService->applyToMaintenanceQuery(Maintenance::query(), $filters);
        $maintByStatus = (clone $maintQuery)->select('maintenances.status', DB::raw('COUNT(*) as total'))
            ->groupBy('maintenances.status')
            ->pluck('total', 'status');

        return view('livewire.admin-dashboard', [
            'metrics' => $metrics,
            'kpis' => $kpis,
            'recentAudits' => $recentAudits,
            'audits' => $audits,
            'isDefaultWeek' => $isDefaultWeek,
            'criteriaFailures' => $criteriaFailures,
            'topBadSpaces' => $topBadSpaces,
            'maintByStatus' => $maintByStatus,
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
