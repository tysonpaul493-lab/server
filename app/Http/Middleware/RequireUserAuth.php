<?php

namespace App\Http\Middleware;

use App\Services\UserAuthResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireUserAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = UserAuthResolver::resolveUser($request);
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized: Valid user bearer token is required.',
            ], 401);
        }

        $request->attributes->set('auth_user', $user);

        return $next($request);
    }
}
