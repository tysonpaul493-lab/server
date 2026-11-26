<?php

namespace App\Services;

use App\Models\Driver;
use App\Models\DriverToken;
use Illuminate\Http\Request;

class DriverAuthResolver
{
    public static function extractBearerToken(Request $request): string
    {
        $auth = (string) $request->header('Authorization', '');
        if (str_starts_with($auth, 'Bearer ')) {
            return trim(substr($auth, 7));
        }

        return '';
    }

    public static function issueToken(Driver $driver, string $deviceName = 'Driver Mobile App'): string
    {
        $token = $driver->id.'|driver_sanctum_token_'.time().'_'.random_int(100, 999);
        DriverToken::create([
            'driver_id' => $driver->id,
            'token' => $token,
            'device_name' => $deviceName,
        ]);

        return $token;
    }

    public static function resolveDriver(Request $request, ?int $explicitDriverId = null): ?Driver
    {
        if ($explicitDriverId !== null) {
            $explicit = Driver::find($explicitDriverId);
            if ($explicit) {
                return $explicit;
            }
        }

        $token = self::extractBearerToken($request);
        if ($token !== '') {
            $record = DriverToken::where('token', $token)
                ->where('created_at', '>=', now()->subDays(30))
                ->first();
            if ($record) {
                $driver = Driver::find($record->driver_id);
                if ($driver && strtolower((string) ($driver->verification_status ?? '')) !== 'suspended') {
                    return $driver;
                }
            }
        }

        return null;
    }
}
