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
 * Membatasi akses route berdasarkan role.
 *
 * Pemakaian: ->middleware('role:admin')
 *
 * User di-load dari DB (bukan dari klaim token) agar penonaktifan akun maupun
 * perubahan role oleh admin langsung berlaku tanpa menunggu token kedaluwarsa.
 */
class RoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            if (! $user) {
                throw new UnauthorizedHttpException('', 'Token invalid (user tidak ditemukan)');
            }

            if (! $user->is_active) {
                return response()->json(['message' => 'Akun dinonaktifkan'], 401);
            }

            $request->attributes->set('auth_user_id', $user->id);
            $request->attributes->set('auth_user_role', $user->role);

            // Super admin bypass; tanpa role yang disyaratkan → allow.
            if ($user->role === Role::SUPER_ADMIN->value || empty($roles)) {
                return $next($request);
            }

            if (! in_array($user->role, $roles, true)) {
                return response()->json([
                    'message' => 'Akses ditolak (role tidak sesuai)',
                ], 403);
            }

        } catch (TokenExpiredException $e) {
            return response()->json(['message' => 'Token expired'], 401);
        } catch (JWTException $e) {
            return response()->json(['message' => 'Token invalid'], 401);
        } catch (\Exception $e) {
            Log::info('RoleMiddleware unauthorized', ['reason' => $e->getMessage()]);

            return response()->json(['message' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
