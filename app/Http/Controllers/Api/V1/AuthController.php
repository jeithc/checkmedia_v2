<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\RedeemAccessCodeRequest;
use App\Http\Resources\Api\UserResource;
use App\Models\ExternalAccessCode;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('username', $request->username)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Credenciales inválidas.',
            ], 401);
        }

        if (! $user->is_active) {
            return response()->json([
                'message' => 'Su cuenta está desactivada. Contacte al administrador.',
            ], 403);
        }

        $tokenName = $user->is_external ? 'external-auditor-token' : 'auditor-token';
        $abilities = $this->resolveAbilities($user);

        $token = $user->createToken($tokenName, $abilities);

        return response()->json([
            'message' => 'Inicio de sesión exitoso.',
            'data' => [
                'user' => new UserResource($user),
                'token' => $token->plainTextToken,
                'abilities' => $abilities,
            ],
        ]);
    }

    /**
     * External auditor redeems an access code to get a session token.
     * No account/password needed — the code IS the credential.
     */
    public function redeemCode(RedeemAccessCodeRequest $request): JsonResponse
    {
        $accessCode = ExternalAccessCode::where('code', strtoupper($request->code))->first();

        if (! $accessCode) {
            return response()->json([
                'message' => 'Código de acceso no encontrado.',
            ], 404);
        }

        if (! $accessCode->isValid()) {
            $reason = 'Código de acceso inválido.';
            if ($accessCode->is_revoked) {
                $reason = 'Este código ha sido revocado.';
            } elseif ($accessCode->expires_at?->isPast()) {
                $reason = 'Este código ha expirado.';
            } elseif ($accessCode->times_used >= $accessCode->max_uses) {
                $reason = 'Este código ya alcanzó el límite de usos.';
            }

            return response()->json(['message' => $reason], 403);
        }

        $user = $this->getOrCreateExternalUser($accessCode);

        $accessCode->recordUsage();

        $abilities = ['audit:create', 'audit:read-own'];
        $token = $user->createToken("access-code:{$accessCode->code}", $abilities);

        return response()->json([
            'message' => 'Acceso concedido. Sus auditorías quedarán pendientes de aprobación.',
            'data' => [
                'user' => new UserResource($user),
                'token' => $token->plainTextToken,
                'abilities' => $abilities,
                'code_info' => [
                    'label' => $accessCode->label,
                    'remaining_uses' => $accessCode->remainingUses(),
                    'expires_at' => $accessCode->expires_at?->toIso8601String(),
                ],
            ],
        ]);
    }

    public function logout(): JsonResponse
    {
        auth()->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Sesión cerrada exitosamente.',
        ]);
    }

    public function me(): JsonResponse
    {
        $user = auth()->user();

        return response()->json([
            'data' => new UserResource($user),
        ]);
    }

    /**
     * Get or create a system user for this access code.
     * Each code maps to a single ephemeral user so audits have a user_id.
     */
    private function getOrCreateExternalUser(ExternalAccessCode $code): User
    {
        $username = 'ext_'.strtolower(str_replace('-', '', $code->code));

        return User::firstOrCreate(
            ['username' => $username],
            [
                'name' => $code->label,
                'email' => $username.'@external.audit',
                'password' => Hash::make(Str::random(40)),
                'is_external' => true,
                'is_active' => true,
                'permissions' => ['audit.can_audit' => true],
            ]
        );
    }

    private function resolveAbilities(User $user): array
    {
        $abilities = [];

        if ($user->is_external) {
            return ['audit:create', 'audit:read-own'];
        }

        if ($user->hasAccess('audit.can_audit') || $user->hasAccess('audit.can_audit_structural')) {
            $abilities[] = 'audit:create';
            $abilities[] = 'audit:read-own';
        }

        if ($user->hasAccess('audit.close_with_error') || $user->hasAccess('audit.upload_fixes')) {
            $abilities[] = 'audit:manage';
        }

        if ($user->hasAccess('platform.index')) {
            $abilities[] = 'audit:approve';
            $abilities[] = 'audit:read-all';
        }

        return $abilities;
    }
}
