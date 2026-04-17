<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use App\Enums\Role;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        try {
            // ✅ Ambil payload tanpa query DB
            $payload = JWTAuth::parseToken()->getPayload();

            $userId = $payload->get('sub');
            $role   = $payload->get('role');

            if (!$userId) {
                throw new UnauthorizedHttpException('', 'Token invalid (no subject)');
            }

            // ✅ SUPER ADMIN BYPASS
            if ($role === Role::SUPER_ADMIN->value) {
                return $next($request);
            }

            // ✅ Kalau tidak ada role di middleware → allow
            if (empty($roles)) {
                return $next($request);
            }

            // ✅ Role check
            if (!in_array($role, $roles)) {
                return response()->json([
                    'message' => 'Akses ditolak (role tidak sesuai)'
                ], 403);
            }

            // ✅ Optional: inject ke request (biar controller bisa pakai)
            $request->attributes->set('auth_user_id', $userId);
            $request->attributes->set('auth_user_role', $role);

        } catch (\PHPOpenSourceSaver\JWTAuth\Exceptions\TokenExpiredException $e) {
            return response()->json([
                'message' => 'Token expired'
            ], 401);

        } catch (\PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException $e) {
            return response()->json([
                'message' => 'Token invalid'
            ], 401);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Unauthorized',
                'error' => $e->getMessage()
            ], 401);
        }

        return $next($request);
    }
}