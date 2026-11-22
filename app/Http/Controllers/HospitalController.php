<?php

namespace App\Http\Controllers;

use App\Models\Hospital;
use App\Services\PhoneHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HospitalController extends Controller
{
    public function nearby(Request $request): JsonResponse
    {
        $userLat = (float) ($request->query('lat') ?: 11.0318);
        $userLng = (float) ($request->query('lng') ?: 76.9437);

        $hospitals = Hospital::where('verified', true)->get();

        if ($hospitals->isEmpty()) {
            $hospitals = Hospital::all();
        }

        $results = $hospitals->map(function (Hospital $h) use ($userLat, $userLng) {
            $dist = PhoneHelper::calculateDrivingDistanceKm($userLat, $userLng, (float) $h->lat, (float) $h->lng);
            $driveTimeMin = max(2, min(60, (int) ceil($dist * 2.4)));

            return [
                'id' => (string) ($h->slug ?: $h->id),
                'database_id' => $h->id,
                'name' => $h->name,
                'address' => $h->address,
                'lat' => (float) $h->lat,
                'lng' => (float) $h->lng,
                'emergency_phone' => $h->emergency_phone ?: '108',
                'emergency_services' => is_array($h->emergency_services) ? $h->emergency_services : ['Free 108 Emergency', 'Level 1 Trauma Care', '24x7 Casualty', '100-Bed Emergency ICU'],
                'has_trauma_center' => (bool) $h->has_trauma_center,
                'has_icu_bed' => (bool) $h->has_icu_bed,
                'trauma_level' => $h->trauma_level,
                'available_icu_beds' => (int) $h->available_icu_beds,
                'total_icu_beds' => (int) $h->total_icu_beds,
                'available_general_beds' => (int) $h->available_general_beds,
                'total_general_beds' => (int) $h->total_general_beds,
                'distance_km' => round($dist, 1),
                'estimated_drive_time' => "{$driveTimeMin} min",
            ];
        })->toArray();

        usort($results, static fn (array $a, array $b) => $a['distance_km'] <=> $b['distance_km']);

        return response()->json([
            'status' => 'success',
            'data' => $results,
            'hospitals' => $results,
        ]);
    }
}

