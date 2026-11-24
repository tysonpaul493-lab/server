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
        $rawLat = $hasCoords ? (float) $request->query('lat') : null;
        $rawLng = $hasCoords ? (float) $request->query('lng') : null;

        // If coordinates are missing or 0,0, anchor to regional Coimbatore/Tamil Nadu emergency coordinates
        $userLat = ($rawLat !== null && ($rawLat != 0.0 || $rawLng != 0.0)) ? $rawLat : 11.0318;
        $userLng = ($rawLng !== null && ($rawLat != 0.0 || $rawLng != 0.0)) ? $rawLng : 76.9437;

        $searchTerm = trim((string) ($request->query('search') ?: $request->query('name') ?: ''));
        $specialty = trim((string) ($request->query('specialty') ?: $request->query('service') ?: ''));

        $query = Hospital::query();

        if ($searchTerm !== '') {
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'like', "%{$searchTerm}%")
                  ->orWhere('address', 'like', "%{$searchTerm}%");
            });
        }

        if ($specialty !== '' && strtolower($specialty) !== 'all') {
            $specLower = strtolower($specialty);
            $query->where(function ($q) use ($specLower) {
                // Government hospitals (e.g. CMCH 108) cater to all emergency trauma and casualty specialties
                $q->where('name', 'like', '%Government%')
                  ->orWhere('name', 'like', '%CMCH%')
                  ->orWhere('emergency_phone', '108');

                if (str_contains($specLower, 'cardiac') || str_contains($specLower, 'heart')) {
                    $q->orWhere('has_cardiac_care', true)
                      ->orWhere('emergency_services', 'like', '%cardiac%')
                      ->orWhere('emergency_services', 'like', '%cath lab%')
                      ->orWhere('emergency_services', 'like', '%ccu%');
                } elseif (str_contains($specLower, 'trauma') || str_contains($specLower, 'accident')) {
                    $q->orWhere('has_trauma_center', true)
                      ->orWhere('emergency_services', 'like', '%trauma%')
                      ->orWhere('emergency_services', 'like', '%casualty%')
                      ->orWhere('emergency_services', 'like', '%ortho%');
                } elseif (str_contains($specLower, 'stroke') || str_contains($specLower, 'brain') || str_contains($specLower, 'neuro')) {
                    $q->orWhere('has_stroke_unit', true)
                      ->orWhere('emergency_services', 'like', '%stroke%')
                      ->orWhere('emergency_services', 'like', '%neuro%');
                } elseif (str_contains($specLower, 'breath') || str_contains($specLower, 'asthma') || str_contains($specLower, 'air') || str_contains($specLower, 'pulmonary')) {
                    $q->orWhere('has_icu_bed', true)
                      ->orWhere('emergency_services', 'like', '%icu%')
                      ->orWhere('emergency_services', 'like', '%ventilator%');
                } elseif (str_contains($specLower, 'pediatric') || str_contains($specLower, 'child') || str_contains($specLower, 'nicu') || str_contains($specLower, 'picu')) {
                    $q->orWhere('emergency_services', 'like', '%pediatric%')
                      ->orWhere('emergency_services', 'like', '%picu%')
                      ->orWhere('emergency_services', 'like', '%nicu%');
                } elseif (str_contains($specLower, 'burn') || str_contains($specLower, 'scald')) {
                    $q->orWhere('emergency_services', 'like', '%burn%')
                      ->orWhere('emergency_services', 'like', '%plastic%');
                } elseif (str_contains($specLower, 'icu')) {
                    $q->orWhere('has_icu_bed', true)
                      ->orWhere('available_icu_beds', '>', 0);
                } elseif (str_contains($specLower, 'gov') || str_contains($specLower, '108')) {
                    // Already covered by government condition
                } else {
                    $q->orWhere('trauma_level', 'like', "%{$specLower}%")
                      ->orWhere('emergency_services', 'like', "%{$specLower}%");
                }
            });
        }

        $hospitals = $query->get();

        // Safety fallback: Never leave an emergency patient with zero hospitals
        if ($hospitals->isEmpty()) {
            $hospitals = Hospital::all();
        }

        $results = $hospitals->map(function (Hospital $h) use ($userLat, $userLng) {
            $dist = ($userLat !== null && $userLng !== null)
                ? PhoneHelper::calculateDrivingDistanceKm($userLat, $userLng, (float) $h->lat, (float) $h->lng)
                : 1.5;
            $driveTimeMin = max(2, min(90, (int) ceil($dist * 2.4)));

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
                'distance_km' => round($dist, 1),
                'estimated_drive_time' => "{$driveTimeMin} min",
                'verified' => (bool) $h->verified,
                'data_verified_at' => $h->updated_at ? $h->updated_at->toIso8601String() : now()->toIso8601String(),
            ];
        })->toArray();

        // Priority 1: Government CMCH 108 hospital at top, Priority 2: Closest distance
        usort($results, function (array $a, array $b) {
            $aGov = str_contains(strtolower($a['name']), 'government') || str_contains(strtolower($a['name']), 'cmch') || ($a['emergency_phone'] ?? '') === '108';
            $bGov = str_contains(strtolower($b['name']), 'government') || str_contains(strtolower($b['name']), 'cmch') || ($b['emergency_phone'] ?? '') === '108';
            if ($aGov && !$bGov) return -1;
            if (!$aGov && $bGov) return 1;
            return ($a['distance_km'] ?? 9999) <=> ($b['distance_km'] ?? 9999);
        });

        return response()->json([
            'status' => 'success',
            'data' => $results,
            'hospitals' => $results,
        ]);
    }
}

