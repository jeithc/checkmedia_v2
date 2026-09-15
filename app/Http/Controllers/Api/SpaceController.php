<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\AdvisualUnavailableException;
use App\Http\Controllers\Controller;
use App\Http\Resources\SpaceResource;
use App\Models\AdvertisingSpace;
use App\Models\Audit;
use App\Services\AdvisualSyncService;
use Illuminate\Http\Request;

class SpaceController extends Controller
{
    public function search(Request $request, AdvisualSyncService $sync)
    {
        $request->validate(['code' => ['required', 'string']]);
        $code = $request->query('code');
        $type = $request->query('type', Audit::TYPE_GENERAL);

        $space = AdvertisingSpace::where('external_code', $code)->first();

        if (! $space) {
            try {
                $space = $sync->syncSpaceByCcde($code);
            } catch (AdvisualUnavailableException $e) {
                return response()->json([
                    'message' => 'No se pudo consultar Advisual (servicio caído). El código puede existir; reintenta en unos minutos.',
                ], 503);
            }
        }

        if (! $space) {
            return response()->json(['message' => 'Espacio no encontrado.'], 404);
        }

        $weekData = Audit::getCalendarYearAndWeek(now());
        $existing = Audit::where('advertising_space_id', $space->id)
            ->where('year', $weekData['year'])
            ->where('week', $weekData['week'])
            ->where('audit_type', $type)
            ->first();

        $booking = $space->getBookingForDate(now());

        $resource = new SpaceResource($space);
        $resource->meta = [
            'duplicate' => (bool) $existing,
            'existing_audit_id' => $existing?->id,
            'booking' => $booking ? [
                'id' => $booking->id,
                'client_name' => $booking->client_name,
                'contract_code' => $booking->contract_code,
                'product_name' => $booking->product_name,
            ] : null,
        ];

        return $resource;
    }
}
