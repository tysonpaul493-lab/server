<?php

namespace App\Http\Controllers;

use App\Services\DriverAuthResolver;
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
        $channelName = (string) $request->input('channel_name', '');
        $socketId = (string) $request->input('socket_id', '');
        $driver = DriverAuthResolver::resolveDriver($request);

        $appKey = (string) config('liferoute.reverb_app_key', 'liferoute_key');
        $appSecret = (string) config('liferoute.reverb_app_secret', 'liferoute_secret');

        $channelData = null;
        if (str_starts_with($channelName, 'presence-')) {
            $channelData = json_encode([
                'user_id' => $driver?->id ?? 0,
                'user_info' => ['name' => $driver?->name ?? 'User'],
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
