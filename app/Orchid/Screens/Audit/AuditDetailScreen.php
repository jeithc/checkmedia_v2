<?php

namespace App\Orchid\Screens\Audit;

use App\Models\Audit;
use App\Models\SpaceActivityLog;
use Illuminate\Http\Request;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
use Orchid\Screen\Actions\Link;
use Orchid\Support\Facades\Toast;

class AuditDetailScreen extends Screen
{
    /**
     * @var Audit
     */
    public $audit;

    /**
     * Fetch data to be displayed on the screen.
     *
     * @return array
     */
    public function query(Audit $audit): iterable
    {
        $audit->load(['space', 'values.criterion', 'photos', 'user', 'comments.user', 'maintenances.requestedBy', 'maintenances.closedBy']);

        // Fetch Booking for Client Name
        $booking = $audit->space->bookings()
            ->where('year', $audit->year)
            ->where('week', $audit->week)
            ->first();

        // Fetch Activity Logs for this space
        $activityLogs = SpaceActivityLog::where('advertising_space_id', $audit->space->id)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        // Backfill synthetic entries for audits without an audit_created log
        $loggedAuditIds = $activityLogs
            ->whereIn('activity_type', [SpaceActivityLog::TYPE_AUDIT_CREATED, SpaceActivityLog::TYPE_AUDIT_UPDATED])
            ->pluck('audit_id')
            ->filter()
            ->unique();

        $missingAudits = Audit::where('advertising_space_id', $audit->space->id)
            ->whereNotIn('id', $loggedAuditIds)
            ->with('user')
            ->get();

        foreach ($missingAudits as $missing) {
            $synthetic = new SpaceActivityLog([
                'advertising_space_id' => $missing->advertising_space_id,
                'user_id' => $missing->user_id,
                'audit_id' => $missing->id,
                'activity_type' => SpaceActivityLog::TYPE_AUDIT_CREATED,
                'description' => 'Auditoría registrada con estado: ' . ($missing->general_status ?? 'n/a'),
                'metadata' => [
                    'general_status' => $missing->general_status,
                    'audit_purpose' => $missing->audit_purpose,
                ],
                'year' => $missing->year,
                'week' => $missing->week,
            ]);
            $synthetic->setRelation('user', $missing->user);
            $synthetic->created_at = $missing->audit_date ?? $missing->created_at;
            $activityLogs->push($synthetic);
        }

        $activityLogs = $activityLogs->sortByDesc('created_at')->values();

        return [
            'audit' => $audit,
            'space' => $audit->space,
            'booking' => $booking,
            'activityLogs' => $activityLogs,
        ];
    }

    /**
     * The name of the screen displayed in the header.
     *
     * @return string|null
     */
    public function name(): ?string
    {
        return 'Detalle de Auditoría';
    }

    /**
     * Display header description.
     */
    public function description(): ?string
    {
        return 'Vista detallada del reporte de auditoría y estado del espacio.';
    }

    public function permission(): ?iterable
    {
        return [
            'audit.can_audit',
        ];
    }

    /**
     * The screen's action buttons.
     *
     * @return \Orchid\Screen\Action[]
     */
    public function commandBar(): iterable
    {
        return [
            Link::make('Volver al Dashboard')
                ->icon('bs.house')
                ->route('platform.main'),

            Link::make('Ver en Mapa')
                ->icon('bs.map')
                ->href('https://maps.google.com/?q=' . $this->audit->space->latitude . ',' . $this->audit->space->longitude)
                ->target('_blank')
                ->canSee((bool) $this->audit->space->latitude),
        ];
    }

    /**
     * The screen's layout elements.
     *
     * @return \Orchid\Screen\Layout[]
     */
    public function layout(): iterable
    {
        return [
            Layout::view('orchid.audit.detail'),
        ];
    }

}
