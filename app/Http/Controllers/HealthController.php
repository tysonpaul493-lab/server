<?php

namespace App\Http\Controllers;

use App\Services\DriverAuthResolver;
use App\Services\PhoneHelper;
use App\Services\UserAuthResolver;
use App\Services\WebSocketBroadcaster;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HealthController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'service' => 'LifeRoute Emergency Backend API (Laravel PHP + MySQL)',
            'status' => 'online',
            'version' => '1.0.0',
            'database' => config('database.default'),
            'connected_ws_clients' => WebSocketBroadcaster::connectedClientsCount(),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function health(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function broadcastingAuth(Request $request): JsonResponse
    {
        $channelName = trim((string) $request->input('channel_name', ''));
        $socketId = trim((string) $request->input('socket_id', ''));

        if ($channelName === '' || $socketId === '') {
            return response()->json(['message' => 'channel_name and socket_id are required.'], 400);
        }

        $admin = AdminApiController::resolveAdmin($request);
        $driver = DriverAuthResolver::resolveDriver($request);
        $user = UserAuthResolver::resolveUser($request);

        if (!$admin && !$driver && !$user) {
            return response()->json(['message' => 'Unauthorized WebSocket channel request.'], 401);
        }

        $authorized = false;
        if ($admin) {
            $authorized = true;
        } elseif (preg_match('/^private-driver\.(\d+)$/', $channelName, $m)) {
            $authorized = $driver && (int) $driver->id === (int) $m[1];
        } elseif (preg_match('/^private-bookings?\.(.+)$/', $channelName, $m)) {
            $booking = BookingController::findBookingByKey($m[1]);
            if ($booking) {
                if ($driver && (int) $booking->driver_id === (int) $driver->id) {
                    $authorized = true;
                } elseif ($user) {
                    if ($booking->user_id && (int) $booking->user_id === (int) $user->id) {
                        $authorized = true;
                    } elseif (!$booking->user_id) {
                        $uPhone = PhoneHelper::normalizePhone10((string) $user->phone);
                        $bPhone = PhoneHelper::normalizePhone10((string) $booking->callback_phone);
                        $authorized = $uPhone !== '' && $uPhone === $bPhone;
                    }
                }
            }
        }

        if (!$authorized) {
            return response()->json(['message' => 'Forbidden: Not authorized for this private channel.'], 403);
        }

        $appKey = (string) config('liferoute.reverb_app_key', 'liferoute_reverb_key');
        $appSecret = (string) config('liferoute.reverb_app_secret', 'liferoute_emergency_ws_secret_2026');

        $channelData = null;
        if (str_starts_with($channelName, 'presence-')) {
            $channelData = json_encode([
                'user_id' => $driver?->id ?? $user?->id ?? $admin?->id ?? 0,
                'user_info' => ['name' => $driver?->name ?? $user?->name ?? $admin?->name ?? 'User'],
            ]);
        }

        $stringToSign = $socketId . ':' . $channelName . ($channelData ? ':' . $channelData : '');
        $signature = hash_hmac('sha256', $stringToSign, $appSecret);

        $response = [
            'auth' => $appKey . ':' . $signature,
        ];
        if ($channelData) {
            $response['channel_data'] = $channelData;
        }

        return response()->json($response);
    }
}
