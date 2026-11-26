<?php

namespace App\Http\Middleware;

use App\Http\Controllers\AdminApiController;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireAdminAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $admin = AdminApiController::resolveAdmin($request);
        if (!$admin) {
            return response()->json([
                'success' => false,
                'status' => 'error',
                'message' => 'Unauthorized: Valid administrator bearer token is required.',
            ], 401);
        }

        $request->attributes->set('auth_admin', $admin);

        return $next($request);
    }
}
