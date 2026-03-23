<?php

namespace App\Livewire;

use App\Models\AdvertisingSpace;
use App\Models\Audit;
use App\Models\AuditValue;
use App\Models\Maintenance;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;

class AdminDashboard extends Component
{
    #[Url(as: 'from')]
    public $dateFrom;

    #[Url(as: 'to')]
    public $dateTo;

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

        return redirect()->route('platform.main', [
            'from' => $this->dateFrom,
            'to' => $this->dateTo,
        ]);
    }

    public function resetDates()
    {
        return redirect()->route('platform.main');
    }

    public function render()
    {
        $dateFrom = $this->dateFrom;
        $dateTo = $this->dateTo;

        $dateQuery = function ($query) use ($dateFrom, $dateTo) {
            if ($dateFrom) {
                $query->whereDate('audit_date', '>=', $dateFrom);
            }
            if ($dateTo) {
                $query->whereDate('audit_date', '<=', $dateTo);
            }
        };

        $auditsWithIssues = Audit::where('general_status', 'bad')
            ->where($dateQuery)
            ->count();

        $criticalAudits = Audit::where('general_status', 'bad')
            ->whereNull('resolved_at')
            ->where($dateQuery)
            ->count();

        $totalAuditsInRange = Audit::where($dateQuery)->count();

        $isDefaultWeek = $dateFrom === now()->startOfWeek()->format('Y-m-d')
            && $dateTo === now()->endOfWeek()->format('Y-m-d');

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
        ];

        // --- KPI: Open vs Closed maintenances ---
        $openMaintenances = Maintenance::whereNotIn('status', [Maintenance::STATUS_CLOSED])->count();
        $closedMaintenances = Maintenance::where('status', Maintenance::STATUS_CLOSED)->count();
        $totalMaintenances = $openMaintenances + $closedMaintenances;

        // --- KPI: Average closure time (days) ---
        $avgClosureTime = Maintenance::where('status', Maintenance::STATUS_CLOSED)
            ->whereNotNull('closed_at')
            ->whereNotNull('requested_at')
            ->selectRaw('AVG(JULIANDAY(closed_at) - JULIANDAY(requested_at)) as avg_days')
            ->value('avg_days');

        $avgClosureDays = $avgClosureTime !== null ? round($avgClosureTime, 1) : null;

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

        // Recent audits
        $recentAudits = Audit::with('space')
            ->where($dateQuery)
            ->orderByRaw("CASE WHEN general_status = 'bad' THEN 1 ELSE 2 END")
            ->orderBy('audit_date', 'desc')
            ->limit(10)
            ->get();

        // --- Chart: Criterios con más fallas ---
        $auditIds = Audit::where($dateQuery)->pluck('id');
        $criteriaFailures = AuditValue::whereIn('audit_id', $auditIds)
            ->where('value', 'bad')
            ->join('audit_criteria', 'audit_values.audit_criterion_id', '=', 'audit_criteria.id')
            ->select('audit_criteria.name', DB::raw('COUNT(*) as total'))
            ->groupBy('audit_criteria.name')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        // --- Chart: Top espacios con errores ---
        $topBadSpaces = Audit::where('general_status', 'bad')
            ->where($dateQuery)
            ->select('advertising_space_id', DB::raw('COUNT(*) as total'))
            ->groupBy('advertising_space_id')
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

        // --- Chart: Mantenimientos por estado ---
        $maintByStatus = Maintenance::select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('livewire.admin-dashboard', [
            'metrics' => $metrics,
            'kpis' => $kpis,
            'recentAudits' => $recentAudits,
            'isDefaultWeek' => $isDefaultWeek,
            'criteriaFailures' => $criteriaFailures,
            'topBadSpaces' => $topBadSpaces,
            'maintByStatus' => $maintByStatus,
        ]);
    }
}
