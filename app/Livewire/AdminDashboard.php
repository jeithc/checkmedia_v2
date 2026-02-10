<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\AdvertisingSpace;
use App\Models\Audit;
use App\Models\Maintenance;
use App\Models\CommercialBooking;

class AdminDashboard extends Component
{
    public function render()
    {
        $now = now();
        $weekData = Audit::getCalendarYearAndWeek($now);

        // Auditorías con errores (bad o acceptable) de la semana actual
        $auditsWithIssues = Audit::where('year', $weekData['year'])
            ->where('week', $weekData['week'])
            ->where('general_status', 'bad')
            ->count();

        // Auditorías críticas (bad) sin resolver
        $criticalAudits = Audit::where('year', $weekData['year'])
            ->where('week', $weekData['week'])
            ->where('general_status', 'bad')
            ->whereNull('resolved_at')
            ->count();

        $metrics = [
            'total_spaces' => [
                'label' => 'Espacios Publicitarios',
                'value' => number_format(AdvertisingSpace::count()),
                'subtext' => 'Total Activos',
                'icon' => 'bs.geo-alt',
                'color' => 'primary'
            ],
            'audits_week' => [
                'label' => 'Auditorías (Semana)',
                'value' => number_format(Audit::where('year', $weekData['year'])->where('week', $weekData['week'])->count()),
                'subtext' => 'Esta Semana (S' . $weekData['week'] . ')',
                'icon' => 'bs.check-circle',
                'color' => 'primary'
            ],
            'audits_with_issues' => [
                'label' => 'Auditorías con Errores',
                'value' => number_format($auditsWithIssues),
                'subtext' => $criticalAudits > 0 ? $criticalAudits . ' críticas sin resolver' : 'Esta Semana',
                'icon' => 'bs.exclamation-triangle',
                'color' => $auditsWithIssues > 0 ? 'danger' : 'success'
            ],
            'pending_maint' => [
                'label' => 'Mantenimientos Pend.',
                'value' => number_format(Maintenance::where('status', '!=', 'completed')->count()),
                'subtext' => 'Por Atender',
                'icon' => 'bs.tools',
                'color' => 'primary'
            ],
        ];

        // Auditorías recientes con errores primero
        $recentAudits = Audit::with('space')
            ->where('year', $weekData['year'])
            ->where('week', $weekData['week'])
            ->orderByRaw("CASE WHEN general_status = 'bad' THEN 1 ELSE 2 END")
            ->orderBy('audit_date', 'desc')
            ->limit(10)
            ->get();

        return view('livewire.admin-dashboard', [
            'metrics' => $metrics,
            'recentAudits' => $recentAudits,
        ]);
    }
}
