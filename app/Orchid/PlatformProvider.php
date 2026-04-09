<?php

declare(strict_types=1);

namespace App\Orchid;

use Orchid\Platform\Dashboard;
use Orchid\Platform\ItemPermission;
use Orchid\Platform\OrchidServiceProvider;
use Orchid\Screen\Actions\Menu;

class PlatformProvider extends OrchidServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot(Dashboard $dashboard): void
    {
        parent::boot($dashboard);

        // ...
    }

    /**
     * Register the application menu.
     *
     * @return Menu[]
     */
    public function menu(): array
    {
        return [
            Menu::make('Dashboard')
                ->icon('bs.collection')
                ->route(config('platform.index')),

            Menu::make('Panel Principal (Test Dash 2)')
                ->icon('bs.bar-chart-line')
                ->route('platform.dashboard2'),

            Menu::make('Espacios')
                ->icon('bs.geo-alt')
                ->route('platform.spaces')
                ->permission('audit.can_audit'),

            Menu::make('Reportes de Auditoría')
                ->icon('bs.file-earmark-spreadsheet')
                ->route('platform.reports.audit-builder')
                ->permission('audit.can_audit'),

            Menu::make('Mantenimientos')
                ->icon('bs.tools')
                ->route('platform.maintenances')
                ->permission('maintenance.view')
                ->divider(),

            Menu::make(__('Users'))
                ->icon('bs.people')
                ->route('platform.systems.users')
                ->permission('system.edit_users')
                ->title(__('Admin Zone')),

            Menu::make('Criterios de Auditoría')
                ->icon('bs.list-check')
                ->route('platform.audit.criteria')
                ->permission('audit.manage_criteria'),

            Menu::make('Matriz Preventivo')
                ->icon('bs.calendar-check')
                ->route('platform.preventive.schedule.list')
                ->permission('system.edit_users'),

            Menu::make(__('Roles'))
                ->icon('bs.shield')
                ->route('platform.systems.roles')
                ->permission('platform.systems.roles')
                ->divider(),

            Menu::make('Cerrar Sesión')
                ->icon('bs.box-arrow-left')
                ->route('platform.logout.quick')
                ->divider(),
        ];
    }

    /**
     * Register permissions for the application.
     *
     * @return ItemPermission[]
     */
    public function permissions(): array
    {
        return [
            ItemPermission::group(__('System'))
                ->addPermission('platform.systems.roles', __('Roles'))
                ->addPermission('platform.systems.users', __('Users'))
                ->addPermission('system.create_users', 'Crear usuarios')
                ->addPermission('system.edit_users', 'Editar usuarios')
                ->addPermission('system.be_notified', 'Recibir notificaciones'),

            ItemPermission::group('Auditoría')
                ->addPermission('audit.can_audit', 'Auditoría General')
                ->addPermission('audit.can_audit_structural', 'Auditoría Estructural')
                ->addPermission('audit.manage_criteria', 'Criterios de Aud.')
                ->addPermission('audit.close_with_error', 'Cerrar con Novedades')
                ->addPermission('audit.upload_fixes', 'Subir Revisiones')
                ->addPermission('audit.request_maintenance', 'Solicitar Mantenimiento')
                ->addPermission('audit.can_select_purpose', 'Seleccionar Propósito (Mant.)'),

            ItemPermission::group('Mantenimientos')
                ->addPermission('maintenance.view', 'Ver Mantenimientos')
                ->addPermission('maintenance.close', 'Cerrar Mantenimientos'),

            ItemPermission::group('Reportes')
                ->addPermission('reports.create_shared', 'Crear Reportes'),
        ];
    }
}
