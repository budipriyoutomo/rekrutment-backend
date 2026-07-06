<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use App\Enums\Role;

/**
 * Membatasi akses route berdasarkan izin menu per-user.
 *
 * Pemakaian: ->middleware('permission:vacancies')
 *
 * Aturan:
 *  - super_admin & admin  → selalu lolos (akses penuh)
 *  - user                 → harus punya key izin yang diminta di kolom `permissions`
 *
 * Berbeda dengan RoleMiddleware, di sini user di-load dari DB agar perubahan
 * izin oleh admin langsung berlaku tanpa perlu login ulang.
 */
class PermissionMiddleware
{
    public function handle(Request $request, Closure $next, ...$permissions)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            if (!$user) {
                throw new UnauthorizedHttpException('', 'Token invalid (user tidak ditemukan)');
            }

            // ✅ ADMIN & SUPER ADMIN BYPASS
            if (in_array($user->role, [Role::SUPER_ADMIN->value, Role::ADMIN->value], true)) {
                return $next($request);
            }

            // ✅ Kalau tidak ada izin yang disyaratkan → allow (route publik untuk user)
            if (empty($permissions)) {
                return $next($request);
            }

            $granted = $user->permissions ?? [];

            // User harus punya SALAH SATU izin yang diminta
            if (empty(array_intersect($permissions, $granted))) {
                return response()->json([
                    'message' => 'Akses ditolak (izin menu tidak mencukupi)',
                ], 403);
            }

            $request->attributes->set('auth_user_id', $user->id);
            $request->attributes->set('auth_user_role', $user->role);

        } catch (\PHPOpenSourceSaver\JWTAuth\Exceptions\TokenExpiredException $e) {
            return response()->json(['message' => 'Token expired'], 401);
        } catch (\PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException $e) {
            return response()->json(['message' => 'Token invalid'], 401);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Unauthorized',
                'error'   => $e->getMessage(),
            ], 401);
        }

        return $next($request);
    }
}
