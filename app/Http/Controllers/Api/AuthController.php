<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string'],
        ]);

        if (! Auth::attempt(['username' => $request->username, 'password' => $request->password])) {
            throw ValidationException::withMessages([
                'username' => ['Las credenciales no coinciden con nuestros registros.'],
            ]);
        }

        $user = Auth::user();
        $token = $user->createToken($request->device_name)->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $this->userPayload($user),
            'permissions' => $this->permissionFlags($user),
        ]);
    }

    public function me(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'user' => $this->userPayload($user),
            'permissions' => $this->permissionFlags($user),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['ok' => true]);
    }

    private function userPayload($user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'avatar' => $this->avatarUrl($user),
        ];
    }

    /** Avatar URL: uploaded avatar_path (served from storage) or a Gravatar identicon fallback. */
    private function avatarUrl($user): string
    {
        $path = $user->avatar_path;

        if ($path) {
            if (filter_var($path, FILTER_VALIDATE_URL)) {
                if (str_contains($path, '/storage/')) {
                    return asset('storage/'.explode('/storage/', $path)[1]);
                }

                return $path;
            }

            return asset('storage/'.ltrim($path, '/'));
        }

        $hash = md5(strtolower(trim((string) $user->email)));

        return "https://www.gravatar.com/avatar/{$hash}?d=identicon";
    }

    private function permissionFlags($user): array
    {
        $isStructural = $user->hasAccess('audit.can_audit_structural');
        $isGeneral = $user->hasAccess('audit.can_audit');
        $isAdmin = $user->hasAccess('platform.index');

        return [
            'can_audit' => $isGeneral,
            'can_audit_structural' => $isStructural,
            'can_select_audit_type' => $isStructural && $isGeneral,
            'can_select_purpose' => $isStructural || $isGeneral || $isAdmin || $user->hasAccess('audit.can_select_purpose'),
            'can_do_preventive' => $isAdmin || $user->hasAccess('audit.can_select_purpose'),
            'is_admin' => $isAdmin,
        ];
    }
}
