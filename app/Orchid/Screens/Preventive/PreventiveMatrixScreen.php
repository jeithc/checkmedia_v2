<?php

namespace App\Orchid\Screens\Preventive;

use App\Models\AdvertisingSpace;
use Orchid\Screen\Screen;
use Orchid\Screen\TD;
use Orchid\Support\Facades\Layout;

class PreventiveMatrixScreen extends Screen
{
    /**
     * Fetch data to be displayed on the screen.
     *
     * @return array
     */
    public function query(): iterable
    {
        return [
            'spaces' => AdvertisingSpace::with(['audits', 'preventiveSchedule'])
                ->filters()
                ->paginate(20),
        ];
    }

    /**
     * The name of the screen displayed in the header.
     *
     * @return string|null
     */
    public function name(): ?string
    {
        return 'Matriz de Urgencia Preventiva';
    }

    /**
     * The description is displayed on the user's screen under the heading
     */
    public function description(): ?string
    {
        return 'Estado de mantenimiento preventivo de todos los espacios publicitarios.';
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
            Layout::table('spaces', [
                TD::make('external_code', 'Código')
                    ->sort()
                    ->filter(TD::FILTER_TEXT),

                TD::make('city', 'Ciudad')
                    ->sort()
                    ->filter(TD::FILTER_TEXT),

                TD::make('type', 'Tipo')
                    ->sort()
                    ->filter(TD::FILTER_TEXT),

                TD::make('last_audit_date', 'Últ. Preventivo')
                    ->render(fn(AdvertisingSpace $space) => $this->renderLastAuditDate($space)),

                TD::make('due_date', 'Próx. Vencimiento')
                    ->render(fn(AdvertisingSpace $space) => $this->renderDueDate($space)),

                TD::make('days_remaining', 'Días')
                    ->render(fn(AdvertisingSpace $space) => $this->renderDaysRemaining($space)),
            ]),
        ];
    }

    /**
     * Render the last audit date column.
     */
    private function renderLastAuditDate(AdvertisingSpace $space): string
    {
        $matrix = $space->getPreventiveMatrix();
        $lastAuditDate = $matrix['last_audit_date'];

        if (!$lastAuditDate) {
            return '—';
        }

        return $lastAuditDate->format('d/m/Y');
    }

    /**
     * Render the due date column.
     */
    private function renderDueDate(AdvertisingSpace $space): string
    {
        $matrix = $space->getPreventiveMatrix();
        $dueDate = $matrix['due_date'];

        if (!$dueDate) {
            return '—';
        }

        return $dueDate->format('d/m/Y');
    }

    /**
     * Render the days remaining column with color badge.
     */
    private function renderDaysRemaining(AdvertisingSpace $space): string
    {
        $matrix = $space->getPreventiveMatrix();
        $daysRemaining = $matrix['days_remaining'];
        $status = $matrix['status'];
        $statusColor = $matrix['status_color'];

        // Map status color to Bootstrap badge class
        $badgeClass = match($statusColor) {
            'danger' => 'bg-danger',
            'warning' => 'bg-warning',
            'success' => 'bg-success',
            default => 'bg-secondary',
        };

        return sprintf(
            '<span class="badge %s text-white">%d %s</span>',
            $badgeClass,
            $daysRemaining,
            $status
        );
    }
}
