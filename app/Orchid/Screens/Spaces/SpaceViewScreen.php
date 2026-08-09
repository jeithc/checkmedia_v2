<?php

namespace App\Orchid\Screens\Spaces;

use App\Models\AdvertisingSpace;
use App\Models\SpaceActivityLog;
use Illuminate\Http\Request;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;

class SpaceViewScreen extends Screen
{
    /**
     * Matches the permission the menu entry already uses; without it the screen
     * is reachable by URL for anyone who can open the admin panel.
     */
    public function permission(): ?iterable
    {
        return [
            'audit.can_audit',
        ];
    }

    /**
     * @var AdvertisingSpace
     */
    public $space;

    /**
     * Fetch data to be displayed on the screen.
     *
     *
     * @return array
     */
    public function query(AdvertisingSpace $space, Request $request): iterable
    {
        $space->load('latestAudit');

        $activityQuery = SpaceActivityLog::where('advertising_space_id', $space->id)
            ->with(['user', 'audit'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('activity_type')) {
            $activityQuery->where('activity_type', $request->input('activity_type'));
        }

        if ($request->filled('date_from')) {
            $activityQuery->whereDate('created_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $activityQuery->whereDate('created_at', '<=', $request->input('date_to'));
        }

        return [
            'space' => $space,
            'metrics' => [
                'status' => $space->latestAudit ? $space->latestAudit->general_status : 'N/A',
                'last_audit' => $space->latestAudit ? $space->latestAudit->audit_date->format('d/m/Y') : '-',
                'total_audits' => $space->audits()->count(),
            ],
            'audits' => $space->audits()->with('user')->orderBy('audit_date', 'desc')->paginate(10, ['*'], 'audits_page'),
            'activityLogs' => $activityQuery->paginate(20, ['*'], 'timeline_page'),
            'activityFilters' => [
                'activity_type' => $request->input('activity_type', ''),
                'date_from' => $request->input('date_from', ''),
                'date_to' => $request->input('date_to', ''),
            ],
        ];
    }

    /**
     * The name of the screen displayed in the header.
     */
    public function name(): ?string
    {
        return $this->space->external_code.' - '.$this->space->location_name;
    }

    /**
     * The description is displayed on the user's screen under the heading
     */
    public function description(): ?string
    {
        return $this->space->city.', '.$this->space->category;
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
            Layout::view('orchid.spaces.dashboard'),
        ];
    }
}
