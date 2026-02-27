<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\AdvertisingSpace;
use App\Models\Audit;
use App\Models\Maintenance;
use App\Models\CommercialBooking;
use Livewire\Attributes\Url;

class AdminDashboard extends Component
{
    #[Url(as: 'from')]
    public $dateFrom;

    #[Url(as: 'to')]
    public $dateTo;

    public function mount()
    {
        // Default to current week if no query params
        if (!$this->dateFrom) {
            $this->dateFrom = now()->startOfWeek()->format('Y-m-d');
        }
        if (!$this->dateTo) {
            $this->dateTo = now()->endOfWeek()->format('Y-m-d');
        }
    }

    public function filter()
    {
        // Ensure dateFrom doesn't exceed dateTo
        if ($this->dateFrom && $this->dateTo && $this->dateFrom > $this->dateTo) {
            $this->dateTo = $this->dateFrom;
        }

        // Redirect to reload the full page (so Orchid charts also update)
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

        // Auditorías con errores en el rango
        $auditsWithIssues = Audit::where('general_status', 'bad')
            ->where($dateQuery)
            ->count();

        // Auditorías críticas (bad) sin resolver
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
                'color' => 'primary'
            ],
            'audits_week' => [
                'label' => 'Auditorías (Período)',
                'value' => number_format($totalAuditsInRange),
                'subtext' => 'En rango seleccionado',
                'icon' => 'bs.check-circle',
                'color' => 'primary'
            ],
            'audits_with_issues' => [
                'label' => 'Auditorías con Errores',
                'value' => number_format($auditsWithIssues),
                'subtext' => $criticalAudits > 0 ? $criticalAudits . ' críticas sin resolver' : 'En rango seleccionado',
                'icon' => 'bs.exclamation-triangle',
                'color' => $auditsWithIssues > 0 ? 'danger' : 'success'
            ],
            'pending_maint' => [
                'label' => 'Mantenimientos Pend.',
                'value' => number_format(Maintenance::whereNotIn('status', [Maintenance::STATUS_CLOSED])->count()),
                'subtext' => 'Por Atender',
                'icon' => 'bs.tools',
                'color' => 'primary'
            ],
        ];

        // Auditorías recientes con errores primero
        $recentAudits = Audit::with('space')
            ->where($dateQuery)
            ->orderByRaw("CASE WHEN general_status = 'bad' THEN 1 ELSE 2 END")
            ->orderBy('audit_date', 'desc')
            ->limit(10)
            ->get();

        return view('livewire.admin-dashboard', [
            'metrics' => $metrics,
            'recentAudits' => $recentAudits,
            'isDefaultWeek' => $isDefaultWeek,
        ]);
    }
}
