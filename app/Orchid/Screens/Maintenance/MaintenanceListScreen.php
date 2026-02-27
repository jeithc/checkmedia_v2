<?php

namespace App\Orchid\Screens\Maintenance;

use App\Models\Maintenance;
use App\Orchid\Layouts\Maintenance\MaintenanceListLayout;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;

class MaintenanceListScreen extends Screen
{
    public function query(): iterable
    {
        return [
            'maintenances' => Maintenance::with(['advertisingSpace', 'audit', 'requestedBy'])
                ->filters()
                ->orderByRaw("CASE WHEN status = 'closed' THEN 1 ELSE 0 END")
                ->orderBy('created_at', 'desc')
                ->paginate(15),
        ];
    }

    public function name(): ?string
    {
        return 'Mantenimientos';
    }

    public function description(): ?string
    {
        return 'Listado de mantenimientos solicitados desde auditorías.';
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
            MaintenanceListLayout::class,
        ];
    }
}
