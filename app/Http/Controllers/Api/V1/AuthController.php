<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\RegisterExternalRequest;
use App\Http\Resources\Api\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

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

    public function register(RegisterExternalRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->name,
            'username' => $request->username,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'is_external' => true,
            'is_active' => true,
            'permissions' => [
                'audit.can_audit' => true,
            ],
        ]);

        $abilities = ['audit:create', 'audit:read-own'];
        $token = $user->createToken('external-auditor-token', $abilities);

        return response()->json([
            'message' => 'Registro exitoso. Sus auditorías quedarán pendientes de aprobación.',
            'data' => [
                'user' => new UserResource($user),
                'token' => $token->plainTextToken,
                'abilities' => $abilities,
            ],
        ], 201);
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
