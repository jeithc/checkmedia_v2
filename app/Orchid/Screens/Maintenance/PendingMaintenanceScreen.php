<?php

namespace App\Orchid\Screens\Maintenance;

use App\Models\AdvertisingSpace;
use App\Services\AuditDashboardFilterService;
use App\Services\PendingMaintenanceService;
use Illuminate\Http\Request;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;

class PendingMaintenanceScreen extends Screen
{
    public function query(Request $request, AuditDashboardFilterService $filterService, PendingMaintenanceService $pending): iterable
    {
        // Same query-string keys as the dashboard (city, producto, from, to) so
        // the widget's "Ver todas" link lands here already filtered.
        $filters = $filterService->parseFromRequest($request);

        return [
            'audits' => $pending->query($filters)->paginate(25)->withQueryString(),
            'total' => $pending->count($filters),
            'filters' => $filters,
            'categories' => AdvertisingSpace::query()->whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
            'cities' => AdvertisingSpace::query()->whereNotNull('city')->distinct()->orderBy('city')->pluck('city'),
        ];
    }

    public function name(): ?string
    {
        return 'Pendientes por Solicitar Mantenimiento';
    }

    public function description(): ?string
    {
        return 'Auditorías con criterios en mal estado que aún no tienen un mantenimiento solicitado.';
    }

    public function permission(): ?iterable
    {
        return ['maintenance.view'];
    }

    public function commandBar(): iterable
    {
        return [];
    }

    public function layout(): iterable
    {
        return [
            Layout::view('orchid.maintenance.pending-filters'),
            Layout::view('orchid.maintenance.pending-list'),
        ];
    }
}
