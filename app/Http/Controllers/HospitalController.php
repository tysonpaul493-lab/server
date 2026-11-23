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
        $hasCoords = $request->has('lat') && $request->has('lng') && is_numeric($request->query('lat')) && is_numeric($request->query('lng'));
        $userLat = $hasCoords ? (float) $request->query('lat') : null;
        $userLng = $hasCoords ? (float) $request->query('lng') : null;

        $searchTerm = trim((string) ($request->query('search') ?: $request->query('name') ?: ''));
        $specialty = trim((string) ($request->query('specialty') ?: $request->query('service') ?: ''));

        $query = Hospital::query();

        if ($searchTerm !== '') {
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'like', "%{$searchTerm}%")
                  ->orWhere('address', 'like', "%{$searchTerm}%");
            });
        }

        if ($specialty !== '') {
            $query->where(function ($q) use ($specialty) {
                $q->where('trauma_level', 'like', "%{$specialty}%")
                  ->orWhereJsonContains('emergency_services', $specialty)
                  ->orWhere('emergency_services', 'like', "%{$specialty}%");
            });
        }

        $hospitals = $query->get();

        $results = $hospitals->map(function (Hospital $h) use ($userLat, $userLng) {
            $dist = ($userLat !== null && $userLng !== null)
                ? PhoneHelper::calculateDrivingDistanceKm($userLat, $userLng, (float) $h->lat, (float) $h->lng)
                : null;
            $driveTimeMin = $dist !== null ? max(2, min(90, (int) ceil($dist * 2.4))) : null;

            return [
                'id' => (string) ($h->slug ?: $h->id),
                'database_id' => $h->id,
                'name' => $h->name,
                'address' => $h->address,
                'lat' => (float) $h->lat,
                'lng' => (float) $h->lng,
                'emergency_phone' => $h->emergency_phone ?: '108',
                'emergency_services' => is_array($h->emergency_services) ? $h->emergency_services : [],
                'has_trauma_center' => (bool) $h->has_trauma_center,
                'has_icu_bed' => (bool) $h->has_icu_bed,
                'trauma_level' => $h->trauma_level,
                'available_icu_beds' => (int) $h->available_icu_beds,
                'total_icu_beds' => (int) $h->total_icu_beds,
                'available_general_beds' => (int) $h->available_general_beds,
                'total_general_beds' => (int) $h->total_general_beds,
                'distance_km' => $dist !== null ? round($dist, 1) : null,
                'estimated_drive_time' => $driveTimeMin !== null ? "{$driveTimeMin} min" : 'Location required',
                'verified' => (bool) $h->verified,
                'data_verified_at' => $h->updated_at ? $h->updated_at->toIso8601String() : now()->toIso8601String(),
            ];
        })->toArray();

        if ($userLat !== null && $userLng !== null) {
            usort($results, static fn (array $a, array $b) => ($a['distance_km'] ?? 9999) <=> ($b['distance_km'] ?? 9999));
        }

        return response()->json([
            'status' => 'success',
            'data' => $results,
            'hospitals' => $results,
        ]);
    }
}

