<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class UserAuthResolver
{
    public static function extractBearerToken(Request $request): string
    {
        $auth = (string) $request->header('Authorization', '');
        if (str_starts_with($auth, 'Bearer ')) {
            return trim(substr($auth, 7));
        }

        return '';
    }

    public static function issueToken(User $user): string
    {
        $token = 'sanctum_user_' . bin2hex(random_bytes(32));
        Cache::put('token_user_' . $token, $user->id, now()->addDays(30));

        return $token;
    }

    public static function revokeToken(string $token): void
    {
        if ($token !== '') {
            Cache::forget('token_user_' . $token);
        }
    }

    public static function resolveUser(Request $request): ?User
    {
        $token = self::extractBearerToken($request);
        if ($token === '') {
            $u = $request->user();
            return $u instanceof User && strtoupper((string) ($u->status ?? 'ACTIVE')) !== 'SUSPENDED' ? $u : null;
        }

        $userId = Cache::get('token_user_' . $token);
        if (!$userId) {
            return null;
        }

        $user = User::find($userId);
        if (!$user || strtoupper((string) ($user->status ?? 'ACTIVE')) === 'SUSPENDED') {
            return null;
        }

        return $user;
    }
}
