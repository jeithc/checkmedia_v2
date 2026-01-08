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
        $audit->load(['space', 'values.criterion', 'photos', 'user', 'comments.user']);

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

        // Fetch all audits for this space (for history)
        $allAudits = Audit::where('advertising_space_id', $audit->space->id)
            ->orderBy('year', 'desc')
            ->orderBy('week', 'desc')
            ->limit(20)
            ->get();

        return [
            'audit' => $audit,
            'space' => $audit->space,
            'booking' => $booking,
            'activityLogs' => $activityLogs,
            'allAudits' => $allAudits,
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

    /**
     * Mark audit as Third Party (Tercero).
     */
    public function markAsThirdParty(Audit $audit)
    {
        // 1. Mark Space as third party
        $audit->space->is_third_party = true;
        // Optionally store WHO marked it and WHEN
        if (\Illuminate\Support\Facades\Schema::hasColumn('advertising_spaces', 'third_party_user_id')) {
            $audit->space->third_party_user_id = auth()->id();
            $audit->space->third_party_modified_at = now();
        }
        $audit->space->save();

        // 2. Update all values to 'good'
        foreach ($audit->values as $value) {
            $value->value = 'good';
            $value->comment = 'Marcado como Tercero - Automático';
            $value->save();
        }

        // 3. Update General Status
        $audit->general_status = 'good';
        $audit->observation = trim($audit->observation . " [Marcado como Tercero]");
        $audit->save();

        Toast::info('La auditoría se ha marcado como Tercero y todos los criterios están Aprobados.');
    }
}
