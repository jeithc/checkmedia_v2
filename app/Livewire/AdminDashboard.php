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

        $metrics = [
            'total_spaces' => [
                'label' => 'Espacios Publicitarios',
                'value' => number_format(AdvertisingSpace::count()),
                'subtext' => 'Total Activos',
                'icon' => 'bs.geo-alt'
            ],
            'audits_week' => [
                'label' => 'Auditorías (Semana)',
                'value' => number_format(Audit::where('year', $weekData['year'])->where('week', $weekData['week'])->count()),
                'subtext' => 'Esta Semana (S' . $weekData['week'] . ')',
                'icon' => 'bs.check-circle'
            ],
            'pending_maint' => [
                'label' => 'Mantenimientos Pend.',
                'value' => number_format(Maintenance::where('status', '!=', 'completed')->count()),
                'subtext' => 'Por Atender',
                'icon' => 'bs.tools'
            ],
            'active_bookings' => [
                'label' => 'Pautas Activas',
                'value' => number_format(CommercialBooking::where('year', $weekData['year'])->where('week', $weekData['week'])->count()),
                'subtext' => 'Con Cliente',
                'icon' => 'bs.megaphone'
            ],
        ];

        $recentAudits = Audit::with('space')->latest()->limit(5)->get();

        return view('livewire.admin-dashboard', [
            'metrics' => $metrics,
            'recentAudits' => $recentAudits,
        ]);
    }
}
