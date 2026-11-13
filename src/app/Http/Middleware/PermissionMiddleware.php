<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenExpiredException;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * Membatasi akses route berdasarkan izin menu per-user.
 *
 * Pemakaian: ->middleware('permission:vacancies')
 *
 * Aturan:
 *  - super_admin & admin  → selalu lolos (akses penuh)
 *  - user                 → harus punya key izin yang diminta di kolom `permissions`
 *
 * User di-load dari DB agar perubahan izin maupun penonaktifan akun oleh admin
 * langsung berlaku tanpa perlu login ulang.
 */
class PermissionMiddleware
{
    public function handle(Request $request, Closure $next, ...$permissions)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            if (! $user) {
                throw new UnauthorizedHttpException('', 'Token invalid (user tidak ditemukan)');
            }

            if (! $user->is_active) {
                return response()->json(['message' => 'Akun dinonaktifkan'], 401);
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

        } catch (TokenExpiredException $e) {
            return response()->json(['message' => 'Token expired'], 401);
        } catch (JWTException $e) {
            return response()->json(['message' => 'Token invalid'], 401);
        } catch (\Exception $e) {
            Log::info('PermissionMiddleware unauthorized', ['reason' => $e->getMessage()]);

            return response()->json(['message' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
