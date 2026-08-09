<?php

namespace App\Orchid\Screens\Reports;

use App\Exports\AuditsExport;
use App\Models\Audit;
use App\Models\AuditCriterion;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;

class AuditReportBuilderScreen extends Screen
{
    public $selectedColumns = [];

    public $availableStaticColumns = [];

    public $availableCriteria = [];

    public $previewData = null;

    public $showPreview = false;

    /**
     * Fetch data to be displayed on the screen.
     *
     * @return array
     */
    public function query(Request $request): iterable
    {
        // Define static columns available for selection
        $this->availableStaticColumns = [
            'audit_date' => 'Fecha de Auditoría',
            'auditor' => 'Auditor',
            'city' => 'Ciudad',
            'provider' => 'Proveedor',
            'external_code' => 'Código Externo',
            'general_status' => 'Estado General',
            'observation' => 'Observación',
            'year' => 'Año',
            'week' => 'Semana',
        ];

        // Load active audit criteria for dynamic columns
        $this->availableCriteria = AuditCriterion::where('is_active', true)
            ->orderBy('order_index')
            ->get();

        // Get selected columns from session or use defaults
        $this->selectedColumns = session('report_selected_columns', [
            'audit_date',
            'auditor',
            'city',
            'external_code',
            'general_status',
        ]);

        // Check if preview was requested
        if (session('show_preview')) {
            $this->showPreview = true;
            $this->previewData = Audit::with(['values.criterion', 'user', 'space'])
                ->orderBy('audit_date', 'desc')
                ->limit(10)
                ->get();

            // Clear the session flag
            session()->forget('show_preview');
        }

        return [
            'availableStaticColumns' => $this->availableStaticColumns,
            'availableCriteria' => $this->availableCriteria,
            'selectedColumns' => $this->selectedColumns,
            'previewData' => $this->previewData,
            'showPreview' => $this->showPreview,
        ];
    }

    /**
     * The name of the screen displayed in the header.
     *
     * @return string|null
     */
    /**
     * Without this, downloadExcel() — which exports every audit in the system —
     * is reachable by URL for anyone who can open the admin panel, since the
     * menu's ->permission('audit.can_audit') only hides the link.
     *
     * Orchid authorises with hasAnyAccess(), so this grants either permission.
     */
    public function permission(): ?iterable
    {
        return [
            'audit.can_audit',
            'reports.create_shared',
        ];
    }

    public function name(): ?string
    {
        return 'Constructor de Reportes de Auditoría';
    }

    /**
     * The description is displayed on the user's screen under the heading
     */
    public function description(): ?string
    {
        return 'Seleccione las columnas y descargue reportes personalizados de auditorías.';
    }

    /**
     * The screen's action buttons.
     *
     * @return \Orchid\Screen\Action[]
     */
    public function commandBar(): iterable
    {
        return [];
    }

    /**
     * The screen's layout elements.
     *
     * @return \Orchid\Screen\Layout[]|string[]
     */
    public function layout(): iterable
    {
        return [
            Layout::view('orchid.audit-report-builder-wrapper'),
        ];
    }

    /**
     * These three actions are wired up in routes/platform.php as plain
     * Route::post entries pointing straight at the methods, so they never pass
     * through Screen::handle() and therefore never hit permission(). The check
     * has to live in the methods themselves.
     */
    private function authorizeReportAccess(): void
    {
        abort_unless(auth()->user()->hasAnyAccess($this->permission()), 403);
    }

    /**
     * Update selected columns
     */
    public function updateColumns(Request $request)
    {
        $this->authorizeReportAccess();

        $selectedColumns = $request->input('selectedColumns', []);
        session(['report_selected_columns' => $selectedColumns]);

        return redirect()->route('platform.reports.audit-builder');
    }

    /**
     * Generate preview
     */
    public function generatePreview(Request $request)
    {
        $this->authorizeReportAccess();

        $selectedColumns = $request->input('selectedColumns', []);

        if (empty($selectedColumns)) {
            return redirect()->route('platform.reports.audit-builder')
                ->with('error', 'Debe seleccionar al menos una columna.');
        }

        session(['report_selected_columns' => $selectedColumns]);
        session(['show_preview' => true]);

        return redirect()->route('platform.reports.audit-builder');
    }

    /**
     * Download Excel
     */
    /**
     * Download Excel
     */
    public function downloadExcel(Request $request)
    {
        $this->authorizeReportAccess();

        \Illuminate\Support\Facades\Log::info('Inicio descarga Excel');

        $selectedColumns = $request->input('selectedColumns', []);

        if (empty($selectedColumns)) {
            return redirect()->route('platform.reports.audit-builder')
                ->with('error', 'Debe seleccionar al menos una columna para descargar.');
        }

        session(['report_selected_columns' => $selectedColumns]);

        $criteria = AuditCriterion::where('is_active', true)->orderBy('order_index')->get();
        $fileName = 'reporte_auditorias_'.now()->format('Y-m-d_His').'.xlsx';

        // Clear output buffer to prevent corrupted files or hangs
        if (ob_get_length()) {
            ob_end_clean();
        }

        \Illuminate\Support\Facades\Log::info('Generando Excel: '.$fileName);

        return Excel::download(
            new AuditsExport($selectedColumns, $criteria),
            $fileName
        );
    }
}
