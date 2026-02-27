<?php

declare(strict_types=1);


// Example screens removed - routes commented out below

use App\Orchid\Screens\PlatformScreen;
use App\Orchid\Screens\Role\RoleEditScreen;
use App\Orchid\Screens\Role\RoleListScreen;
use App\Orchid\Screens\User\UserEditScreen;
use App\Orchid\Screens\User\UserListScreen;
use App\Orchid\Screens\User\UserProfileScreen;
use Illuminate\Support\Facades\Route;
use Tabuna\Breadcrumbs\Trail;

/*
|--------------------------------------------------------------------------
| Dashboard Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the need "dashboard" middleware group. Now create something great!
|
*/

// Spaces
use App\Orchid\Screens\Spaces\SpacesScreen;

Route::screen('/spaces', SpacesScreen::class)
    ->name('platform.spaces')
    ->breadcrumbs(fn(Trail $trail) => $trail
        ->parent('platform.index')
        ->push('Espacios Publicitarios', route('platform.spaces')));

use App\Orchid\Screens\Spaces\SpaceViewScreen;

Route::screen('/spaces/{space}', SpaceViewScreen::class)
    ->name('platform.spaces.view')
    ->breadcrumbs(fn(Trail $trail, $space) => $trail
        ->parent('platform.spaces')
        ->push($space->external_code, route('platform.spaces.view', $space)));

// Main
Route::screen('/main', PlatformScreen::class)
    ->name('platform.main')
    ->breadcrumbs(fn(Trail $trail) => $trail
        ->parent('platform.index')
        ->push(__('Panel de Control'), route('platform.main')));

// Audit Actions - Using distinct path to avoid Orchid screen route conflict
use App\Http\Controllers\AuditActionController;

Route::post('audit-action/{audit}/third-party', [AuditActionController::class, 'markAsThirdParty'])
    ->name('platform.audit.action.third-party');
Route::post('audit-action/{audit}/upload-revision', [AuditActionController::class, 'uploadRevision'])
    ->name('platform.audit.action.upload-revision');
Route::post('audit-action/{audit}/update', [AuditActionController::class, 'updateAudit'])
    ->name('platform.audit.action.update');
Route::post('audit-action/{audit}/request-maintenance', [AuditActionController::class, 'requestMaintenance'])
    ->name('platform.audit.action.request-maintenance');
Route::post('audit-action/{audit}/close-maintenance', [AuditActionController::class, 'closeMaintenanceFromAudit'])
    ->name('platform.audit.action.close-maintenance');

// Audit Detail Screen
use App\Orchid\Screens\Audit\AuditDetailScreen;
use App\Models\Audit;

Route::screen('audit/{audit}', AuditDetailScreen::class)
    ->name('platform.audit.detail')
    ->breadcrumbs(function (Trail $trail, $audit) {
        // Ensure $audit is loaded as a model  
        if (!$audit instanceof Audit) {
            $audit = Audit::with('space')->find($audit);
        }
        $code = $audit?->space?->external_code ?? $audit?->id ?? 'Detalle';
        return $trail
            ->parent('platform.main')
            ->push('Auditoría ' . $code, route('platform.audit.detail', $audit));
    });

// Audit Report Builder
use App\Orchid\Screens\Reports\AuditReportBuilderScreen;

Route::screen('reports/audit-builder', AuditReportBuilderScreen::class)
    ->name('platform.reports.audit-builder')
    ->breadcrumbs(fn(Trail $trail) => $trail
        ->parent('platform.main')
        ->push('Constructor de Reportes', route('platform.reports.audit-builder')));

// Report Builder Actions
Route::post('reports/audit-builder/update-columns', [AuditReportBuilderScreen::class, 'updateColumns'])
    ->name('platform.reports.update-columns');
Route::post('reports/audit-builder/generate-preview', [AuditReportBuilderScreen::class, 'generatePreview'])
    ->name('platform.reports.generate-preview');
Route::post('reports/audit-builder/download-excel', [AuditReportBuilderScreen::class, 'downloadExcel'])
    ->name('platform.reports.download-excel');

// Platform > Profile
Route::screen('profile', UserProfileScreen::class)
    ->name('platform.profile')
    ->breadcrumbs(fn(Trail $trail) => $trail
        ->parent('platform.index')
        ->push(__('Profile'), route('platform.profile')));

// Platform > System > Users > User
Route::screen('users/{user}/edit', UserEditScreen::class)
    ->name('platform.systems.users.edit')
    ->breadcrumbs(fn(Trail $trail, $user) => $trail
        ->parent('platform.systems.users')
        ->push($user->name, route('platform.systems.users.edit', $user)));

// Platform > System > Users > Create
Route::screen('users/create', UserEditScreen::class)
    ->name('platform.systems.users.create')
    ->breadcrumbs(fn(Trail $trail) => $trail
        ->parent('platform.systems.users')
        ->push(__('Create'), route('platform.systems.users.create')));

// Platform > System > Users
Route::screen('users', UserListScreen::class)
    ->name('platform.systems.users')
    ->breadcrumbs(fn(Trail $trail) => $trail
        ->parent('platform.index')
        ->push(__('Users'), route('platform.systems.users')));

// Platform > System > Roles > Role
Route::screen('roles/{role}/edit', RoleEditScreen::class)
    ->name('platform.systems.roles.edit')
    ->breadcrumbs(fn(Trail $trail, $role) => $trail
        ->parent('platform.systems.roles')
        ->push($role->name, route('platform.systems.roles.edit', $role)));

// Platform > System > Roles > Create
Route::screen('roles/create', RoleEditScreen::class)
    ->name('platform.systems.roles.create')
    ->breadcrumbs(fn(Trail $trail) => $trail
        ->parent('platform.systems.roles')
        ->push(__('Create'), route('platform.systems.roles.create')));

// Platform > System > Roles
Route::screen('roles', RoleListScreen::class)
    ->name('platform.systems.roles')
    ->breadcrumbs(fn(Trail $trail) => $trail
        ->parent('platform.index')
        ->push(__('Roles'), route('platform.systems.roles')));

// Example routes (commented out - not needed in production)
// Route::screen('example', ExampleScreen::class)
//     ->name('platform.example')
//     ->breadcrumbs(fn(Trail $trail) => $trail
//         ->parent('platform.index')
//         ->push('Example Screen'));
//
// Route::screen('/examples/form/fields', ExampleFieldsScreen::class)->name('platform.example.fields');
// Route::screen('/examples/form/advanced', ExampleFieldsAdvancedScreen::class)->name('platform.example.advanced');
// Route::screen('/examples/form/editors', ExampleTextEditorsScreen::class)->name('platform.example.editors');
// Route::screen('/examples/form/actions', ExampleActionsScreen::class)->name('platform.example.actions');
//
// Route::screen('/examples/layouts', ExampleLayoutsScreen::class)->name('platform.example.layouts');
// Route::screen('/examples/grid', ExampleGridScreen::class)->name('platform.example.grid');
// Route::screen('/examples/charts', ExampleChartsScreen::class)->name('platform.example.charts');
// Route::screen('/examples/cards', ExampleCardsScreen::class)->name('platform.example.cards');


// Audit Criteria Management
use App\Orchid\Screens\AuditCriterion\AuditCriterionListScreen;
use App\Orchid\Screens\AuditCriterion\AuditCriterionEditScreen;

Route::screen('audit-criteria/{criterion}/edit', AuditCriterionEditScreen::class)
    ->name('platform.audit.criteria.edit')
    ->breadcrumbs(fn(Trail $trail, $criterion) => $trail
        ->parent('platform.audit.criteria')
        ->push($criterion->name, route('platform.audit.criteria.edit', $criterion)));

Route::screen('audit-criteria/create', AuditCriterionEditScreen::class)
    ->name('platform.audit.criteria.create')
    ->breadcrumbs(fn(Trail $trail) => $trail
        ->parent('platform.audit.criteria')
        ->push(__('Create'), route('platform.audit.criteria.create')));

Route::screen('audit-criteria', AuditCriterionListScreen::class)
    ->name('platform.audit.criteria')
    ->breadcrumbs(fn(Trail $trail) => $trail
        ->parent('platform.index')
        ->push('Criterios de Auditoría', route('platform.audit.criteria')));

// Maintenance Screens
use App\Orchid\Screens\Maintenance\MaintenanceListScreen;
use App\Orchid\Screens\Maintenance\MaintenanceDetailScreen;

Route::screen('maintenances', MaintenanceListScreen::class)
    ->name('platform.maintenances')
    ->breadcrumbs(fn(Trail $trail) => $trail
        ->parent('platform.index')
        ->push('Mantenimientos', route('platform.maintenances')));

Route::screen('maintenances/{maintenance}', MaintenanceDetailScreen::class)
    ->name('platform.maintenances.detail')
    ->breadcrumbs(fn(Trail $trail, $maintenance) => $trail
        ->parent('platform.maintenances')
        ->push("Mantenimiento #{$maintenance->id}", route('platform.maintenances.detail', $maintenance)));

Route::post('maintenances/{maintenance}/close', [MaintenanceDetailScreen::class, 'close'])
    ->name('platform.maintenances.close');

Route::get('logout-quick', function () {
    auth()->logout();
    return redirect()->route('platform.login');
})->name('platform.logout.quick');
