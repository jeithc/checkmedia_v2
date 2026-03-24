<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CreateAccessCodeRequest;
use App\Http\Resources\Api\AccessCodeResource;
use App\Models\ExternalAccessCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccessCodeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->is_external || ! $user->hasAccess('platform.index')) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        $query = ExternalAccessCode::with('createdBy')
            ->orderByDesc('created_at');

        if ($request->boolean('active_only')) {
            $query->where('is_revoked', false)
                ->where(function ($q) {
                    $q->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                })
                ->whereColumn('times_used', '<', 'max_uses');
        }

        $codes = $query->paginate($request->integer('per_page', 15));

        return response()->json([
            'data' => AccessCodeResource::collection($codes),
            'meta' => [
                'current_page' => $codes->currentPage(),
                'last_page' => $codes->lastPage(),
                'per_page' => $codes->perPage(),
                'total' => $codes->total(),
            ],
        ]);
    }

    public function store(CreateAccessCodeRequest $request): JsonResponse
    {
        $user = $request->user();

        if ($user->is_external || ! $user->hasAccess('platform.index')) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        $code = ExternalAccessCode::create([
            'code' => ExternalAccessCode::generateCode(),
            'label' => $request->label,
            'created_by' => $user->id,
            'max_uses' => $request->input('max_uses', 1),
            'expires_at' => $request->expires_at,
        ]);

        $code->load('createdBy');

        return response()->json([
            'message' => 'Código de acceso creado exitosamente.',
            'data' => new AccessCodeResource($code),
        ], 201);
    }

    public function show(ExternalAccessCode $code, Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->is_external || ! $user->hasAccess('platform.index')) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        $code->load(['createdBy', 'audits.space']);

        return response()->json([
            'data' => new AccessCodeResource($code),
            'audits_count' => $code->audits->count(),
        ]);
    }

    public function revoke(ExternalAccessCode $code, Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->is_external || ! $user->hasAccess('platform.index')) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        if ($code->is_revoked) {
            return response()->json(['message' => 'Este código ya está revocado.'], 409);
        }

        $code->update(['is_revoked' => true]);

        return response()->json([
            'message' => 'Código revocado exitosamente.',
            'data' => new AccessCodeResource($code),
        ]);
    }
}
