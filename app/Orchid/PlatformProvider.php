<?php

declare(strict_types=1);

namespace App\Orchid;

use Orchid\Platform\Dashboard;
use Orchid\Platform\ItemPermission;
use Orchid\Platform\OrchidServiceProvider;
use Orchid\Screen\Actions\Menu;
use Orchid\Support\Color;

class PlatformProvider extends OrchidServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @param Dashboard $dashboard
     *
     * @return void
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
            
            Menu::make('Espacios')
                ->icon('bs.geo-alt')
                ->route('platform.spaces')
                ->permission('audit.can_audit'),

            Menu::make('Sample Screen')
                ->icon('bs.collection')
                ->route('platform.example')
                ->badge(fn() => 6),

            Menu::make('Form Elements')
                ->icon('bs.card-list')
                ->route('platform.example.fields')
                ->active('*/examples/form/*'),

            Menu::make('Layouts Overview')
                ->icon('bs.window-sidebar')
                ->route('platform.example.layouts'),

            Menu::make('Grid System')
                ->icon('bs.columns-gap')
                ->route('platform.example.grid'),

            Menu::make('Charts')
                ->icon('bs.bar-chart')
                ->route('platform.example.charts'),

            

            Menu::make('Cards')
                ->icon('bs.card-text')
                ->route('platform.example.cards')
                ->divider(),

            Menu::make('Reportes de Auditoría')
                ->icon('bs.file-earmark-spreadsheet')
                ->route('platform.reports.audit-builder')
                ->permission('audit.can_audit')
                ->divider(),

            Menu::make(__('Users'))
                ->icon('bs.people')
                ->route('platform.systems.users')
                ->permission('system.edit_users')
                ->title(__('Admin Zone')),

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
                ->addPermission('audit.can_audit', 'Realizar Auditorías')
                ->addPermission('audit.close_with_error', 'Cerrar estados con error')
                ->addPermission('audit.upload_fixes', 'Subir arreglos/revisiones'),

            ItemPermission::group('Reportes')
                ->addPermission('reports.create_shared', 'Crear reportes compartidos'),
        ];
    }
}
