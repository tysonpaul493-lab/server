<?php

namespace App\Http\Controllers;

use App\Models\Hospital;
use App\Services\PhoneHelper;
use Composer\CaBundle\CaBundle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HospitalController extends Controller
{
    public function nearby(Request $request): JsonResponse
    {
        $hasCoords = $request->has('lat')
            && $request->has('lng')
            && is_numeric($request->query('lat'))
            && is_numeric($request->query('lng'));
        $rawLat = $hasCoords ? (float) $request->query('lat') : null;
        $rawLng = $hasCoords ? (float) $request->query('lng') : null;

        $hasValidCoords = $hasCoords
            && $rawLat !== null
            && $rawLng !== null
            && is_finite($rawLat)
            && is_finite($rawLng)
            && abs($rawLat) <= 90.0
            && abs($rawLng) <= 180.0
            && ($rawLat != 0.0 || $rawLng != 0.0);

        $searchTerm = trim((string) ($request->query('search') ?: $request->query('name') ?: ''));
        $specialty = trim((string) ($request->query('specialty') ?: $request->query('service') ?: ''));

        if (!$hasValidCoords && $searchTerm === '') {
            return response()->json([
                'status' => 'location_required',
                'message' => 'Valid live GPS coordinates are required to locate surrounding hospitals.',
                'data' => [],
                'hospitals' => [],
            ]);
        }

        $results = [];
        $seenKeys = [];

        // 1. Load verified hospitals registered in LifeRoute database
        $dbQuery = Hospital::query();
        if ($searchTerm !== '') {
            $dbQuery->where(function ($q) use ($searchTerm) {
                $q->where('name', 'like', "%{$searchTerm}%")
                    ->orWhere('address', 'like', "%{$searchTerm}%");
            });
        }

        $dbHospitals = $dbQuery->get();
        foreach ($dbHospitals as $h) {
            $hLat = $h->lat !== null ? (float) $h->lat : 0.0;
            $hLng = $h->lng !== null ? (float) $h->lng : 0.0;
            $dist = ($hasValidCoords && ($hLat != 0.0 || $hLng != 0.0))
                ? PhoneHelper::calculateDrivingDistanceKm($rawLat, $rawLng, $hLat, $hLng)
                : null;

            // Only include regional DB hospitals in surrounding search (within 60 km)
            // unless user explicitly searched by name or running unit tests without network
            if ($hasValidCoords && $searchTerm === '' && !app()->runningUnitTests() && $dist !== null && $dist > 60.0) {
                continue;
            }

            $driveTimeMin = $dist !== null ? max(2, min(120, (int) ceil($dist * 2.4))) : null;
            $services = is_array($h->emergency_services) ? $h->emergency_services : [];

            $item = [
                'id' => (string) ($h->slug ?: $h->id),
                'database_id' => $h->id,
                'source' => 'database',
                'name' => $h->name,
                'address' => $h->address ?? '',
                'lat' => $hLat,
                'lng' => $hLng,
                'emergency_phone' => $h->emergency_phone ?? '',
                'emergency_services' => $services,
                'has_trauma_center' => (bool) $h->has_trauma_center,
                'has_icu_bed' => (bool) $h->has_icu_bed,
                'has_cardiac_care' => (bool) $h->has_cardiac_care,
                'has_stroke_unit' => (bool) $h->has_stroke_unit,
                'trauma_level' => $h->trauma_level ?? '',
                'available_icu_beds' => $h->available_icu_beds !== null ? (int) $h->available_icu_beds : null,
                'total_icu_beds' => $h->total_icu_beds !== null ? (int) $h->total_icu_beds : null,
                'available_general_beds' => $h->available_general_beds !== null ? (int) $h->available_general_beds : null,
                'total_general_beds' => $h->total_general_beds !== null ? (int) $h->total_general_beds : null,
                'distance_km' => $dist !== null ? round($dist, 1) : null,
                'estimated_drive_time' => $driveTimeMin !== null ? "{$driveTimeMin} min" : null,
                'verified' => (bool) $h->verified,
                'data_verified_at' => $h->updated_at ? $h->updated_at->toIso8601String() : null,
            ];

            $normKey = self::normalizeHospitalKey($h->name);
            $seenKeys[$normKey] = ['lat' => $hLat, 'lng' => $hLng];
            $results[] = $item;
        }

        // 2. Fetch live OpenStreetMap Overpass hospitals around the user's actual GPS coordinates
        if ($hasValidCoords && !app()->runningUnitTests()) {
            $osmHospitals = $this->fetchOverpassHospitals($rawLat, $rawLng);
            foreach ($osmHospitals as $osm) {
                if ($searchTerm !== '') {
                    $haystack = strtolower(($osm['name'] ?? '') . ' ' . ($osm['address'] ?? ''));
                    if (!str_contains($haystack, strtolower($searchTerm))) {
                        continue;
                    }
                }

                $normKey = self::normalizeHospitalKey($osm['name']);
                $isDuplicate = false;
                if (isset($seenKeys[$normKey])) {
                    $isDuplicate = true;
                } else {
                    foreach ($seenKeys as $coords) {
                        $gap = PhoneHelper::calculateHaversineKm(
                            (float) $osm['lat'],
                            (float) $osm['lng'],
                            (float) $coords['lat'],
                            (float) $coords['lng']
                        );
                        if ($gap > 0.0 && $gap < 0.2 && similar_text(strtolower($osm['name']), $normKey) > 6) {
                            $isDuplicate = true;
                            break;
                        }
                    }
                }
                if ($isDuplicate) {
                    continue;
                }

                $dist = PhoneHelper::calculateDrivingDistanceKm($rawLat, $rawLng, (float) $osm['lat'], (float) $osm['lng']);
                $driveTimeMin = max(2, min(120, (int) ceil($dist * 2.4)));
                $osm['distance_km'] = round($dist, 1);
                $osm['estimated_drive_time'] = "{$driveTimeMin} min";

                $seenKeys[$normKey] = ['lat' => (float) $osm['lat'], 'lng' => (float) $osm['lng']];
                $results[] = $osm;
            }
        }

        // 3. Apply specialty prioritization/filtering without wiping out genuine acute hospitals
        if ($specialty !== '' && strtolower($specialty) !== 'all' && !empty($results)) {
            $specLower = strtolower($specialty);
            $matched = array_values(array_filter($results, static fn (array $h) => self::matchesSpecialty($h, $specLower)));

            if (!empty($matched)) {
                $results = $matched;
            } else {
                // Keep general/multispecialty/government acute hospitals and exclude narrow outpatient clinics
                $acuteFallback = array_values(array_filter($results, static fn (array $h) => !self::isNarrowOutpatientClinic($h['name'] ?? '')));
                if (!empty($acuteFallback)) {
                    $results = $acuteFallback;
                }
            }
        }

        // Sort: nearby Government/108 hospitals prioritized only within close proximity, else strictly by distance_km
        usort($results, static function (array $a, array $b) {
            $distA = $a['distance_km'] ?? 999999.0;
            $distB = $b['distance_km'] ?? 999999.0;
            $aGov = self::isGovernmentHospital($a) && $distA <= 25.0;
            $bGov = self::isGovernmentHospital($b) && $distB <= 25.0;
            if ($aGov && !$bGov) {
                return -1;
            }
            if (!$aGov && $bGov) {
                return 1;
            }
            return $distA <=> $distB;
        });

        return response()->json([
            'status' => 'success',
            'data' => array_values($results),
            'hospitals' => array_values($results),
        ]);
    }

    /**
     * Fetch genuine hospitals surrounding (lat, lng) from OpenStreetMap Overpass API.
     */
    private function fetchOverpassHospitals(float $lat, float $lng): array
    {
        $gridLat = round($lat, 2);
        $gridLng = round($lng, 2);
        $cacheKey = "overpass_hospitals_v2_{$gridLat}_{$gridLng}";

        $cached = Cache::get($cacheKey);
        if (is_array($cached) && !empty($cached)) {
            return $cached;
        }

        $query = sprintf(
            '[out:json][timeout:8];('
            . 'node["amenity"="hospital"]["name"](around:20000,%.6f,%.6f);'
            . 'way["amenity"="hospital"]["name"](around:20000,%.6f,%.6f);'
            . 'relation["amenity"="hospital"]["name"](around:20000,%.6f,%.6f);'
            . 'node["healthcare"="hospital"]["name"](around:20000,%.6f,%.6f);'
            . 'way["healthcare"="hospital"]["name"](around:20000,%.6f,%.6f);'
            . ');out center tags 60;',
            $lat, $lng, $lat, $lng, $lat, $lng, $lat, $lng, $lat, $lng
        );

        $endpoints = [
            'https://overpass-api.de/api/interpreter',
            'https://overpass.kumi.systems/api/interpreter',
        ];

        $caBundle = CaBundle::getSystemCaRootBundlePath();
        $elements = [];
        foreach ($endpoints as $endpoint) {
            try {
                $response = Http::timeout(10)
                    ->withOptions(['verify' => $caBundle])
                    ->withHeaders(['User-Agent' => 'LifeRouteEmergency/1.0'])
                    ->asForm()
                    ->post($endpoint, ['data' => $query]);

                if ($response->successful()) {
                    $json = $response->json();
                    if (is_array($json) && isset($json['elements']) && is_array($json['elements'])) {
                        $elements = $json['elements'];
                        if (!empty($elements)) {
                            break;
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::warning("Overpass API query failed on {$endpoint}: " . $e->getMessage());
            }
        }

        $parsed = [];
        foreach ($elements as $el) {
            $tags = isset($el['tags']) && is_array($el['tags']) ? $el['tags'] : [];
            $name = trim((string) ($tags['name'] ?? $tags['official_name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $elLat = $el['lat'] ?? ($el['center']['lat'] ?? null);
            $elLng = $el['lon'] ?? ($el['center']['lon'] ?? null);
            if (!is_numeric($elLat) || !is_numeric($elLng)) {
                continue;
            }

            $hLat = (float) $elLat;
            $hLng = (float) $elLng;
            if ($hLat == 0.0 && $hLng == 0.0) {
                continue;
            }

            $addrParts = array_filter([
                $tags['addr:housenumber'] ?? null,
                $tags['addr:street'] ?? null,
                $tags['addr:suburb'] ?? ($tags['addr:neighbourhood'] ?? null),
                $tags['addr:city'] ?? ($tags['addr:town'] ?? ($tags['addr:village'] ?? null)),
                $tags['addr:district'] ?? null,
                $tags['addr:state'] ?? null,
                $tags['addr:postcode'] ?? null,
            ], static fn ($v) => is_string($v) && trim($v) !== '');

            $address = trim((string) ($tags['addr:full'] ?? ''));
            if ($address === '' && !empty($addrParts)) {
                $address = implode(', ', $addrParts);
            }
            if ($address === '') {
                $address = sprintf('%.4f, %.4f', $hLat, $hLng);
            }

            $phone = trim((string) ($tags['emergency:phone'] ?? $tags['phone'] ?? $tags['contact:phone'] ?? ''));
            $services = [];
            if (strtolower((string) ($tags['emergency'] ?? '')) === 'yes') {
                $services[] = 'Emergency';
            }
            if (strtolower((string) ($tags['opening_hours'] ?? '')) === '24/7') {
                $services[] = '24x7';
            }
            if (!empty($tags['healthcare:speciality'])) {
                foreach (explode(';', (string) $tags['healthcare:speciality']) as $sp) {
                    $spClean = ucwords(str_replace(['_', '-'], ' ', trim($sp)));
                    if ($spClean !== '') {
                        $services[] = $spClean;
                    }
                }
            }
            $services = array_values(array_unique($services));

            $lowerText = strtolower($name . ' ' . implode(' ', $services));
            $isEmergencyTagged = strtolower((string) ($tags['emergency'] ?? '')) === 'yes';

            $parsed[] = [
                'id' => 'osm-' . ($el['type'] ?? 'node') . '-' . ($el['id'] ?? count($parsed)),
                'database_id' => null,
                'source' => 'openstreetmap',
                'name' => $name,
                'address' => $address,
                'lat' => $hLat,
                'lng' => $hLng,
                'emergency_phone' => $phone,
                'emergency_services' => $services,
                'has_trauma_center' => $isEmergencyTagged || str_contains($lowerText, 'trauma') || str_contains($lowerText, 'casualty'),
                'has_icu_bed' => str_contains($lowerText, 'icu') || str_contains($lowerText, 'multi') || str_contains($lowerText, 'super'),
                'has_cardiac_care' => str_contains($lowerText, 'cardiac') || str_contains($lowerText, 'heart'),
                'has_stroke_unit' => str_contains($lowerText, 'stroke') || str_contains($lowerText, 'neuro'),
                'trauma_level' => '',
                'available_icu_beds' => null,
                'total_icu_beds' => null,
                'available_general_beds' => null,
                'total_general_beds' => null,
                'distance_km' => null,
                'estimated_drive_time' => null,
                'verified' => false,
                'data_verified_at' => null,
            ];
        }

        // Fallback to OpenStreetMap Photon API if Overpass endpoints returned empty/rate-limited
        if (empty($parsed)) {
            try {
                $photonRes = Http::timeout(8)
                    ->withOptions(['verify' => $caBundle])
                    ->withHeaders(['User-Agent' => 'LifeRouteEmergency/1.0'])
                    ->get('https://photon.komoot.io/api/', [
                        'q' => 'hospital',
                        'lat' => $lat,
                        'lon' => $lng,
                        'limit' => 30,
                    ]);
                if ($photonRes->successful()) {
                    $features = $photonRes->json('features');
                    if (is_array($features)) {
                        foreach ($features as $idx => $feat) {
                            $props = is_array($feat['properties'] ?? null) ? $feat['properties'] : [];
                            $coords = is_array($feat['geometry']['coordinates'] ?? null) ? $feat['geometry']['coordinates'] : [];
                            if (count($coords) < 2) {
                                continue;
                            }
                            $osmKey = strtolower((string) ($props['osm_key'] ?? ''));
                            $osmVal = strtolower((string) ($props['osm_value'] ?? ''));
                            if (!in_array($osmVal, ['hospital', 'clinic'], true) && $osmKey !== 'healthcare') {
                                continue;
                            }
                            $pName = trim((string) ($props['name'] ?? ''));
                            if ($pName === '') {
                                continue;
                            }
                            $pLng = (float) $coords[0];
                            $pLat = (float) $coords[1];
                            $addrParts = array_values(array_filter([
                                trim((string) ($props['street'] ?? '')),
                                trim((string) ($props['district'] ?? '')),
                                trim((string) ($props['city'] ?? '')),
                                trim((string) ($props['state'] ?? '')),
                            ], fn ($v) => $v !== ''));
                            $lower = strtolower($pName);
                            $parsed[] = [
                                'id' => 'osm-photon-' . ($props['osm_id'] ?? $idx),
                                'database_id' => null,
                                'source' => 'openstreetmap',
                                'name' => $pName,
                                'address' => !empty($addrParts) ? implode(', ', $addrParts) : trim((string) ($props['country'] ?? '')),
                                'lat' => $pLat,
                                'lng' => $pLng,
                                'emergency_phone' => '',
                                'emergency_services' => ['Emergency Care'],
                                'has_trauma_center' => str_contains($lower, 'trauma') || str_contains($lower, 'casualty'),
                                'has_icu_bed' => str_contains($lower, 'icu') || str_contains($lower, 'multi') || str_contains($lower, 'super'),
                                'has_cardiac_care' => str_contains($lower, 'cardiac') || str_contains($lower, 'heart'),
                                'has_stroke_unit' => str_contains($lower, 'stroke') || str_contains($lower, 'neuro'),
                                'trauma_level' => '',
                                'available_icu_beds' => null,
                                'total_icu_beds' => null,
                                'available_general_beds' => null,
                                'total_general_beds' => null,
                                'distance_km' => null,
                                'estimated_drive_time' => null,
                                'verified' => false,
                                'data_verified_at' => null,
                            ];
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Photon OSM fallback query failed: ' . $e->getMessage());
            }
        }

        if (!empty($parsed)) {
            Cache::put($cacheKey, $parsed, 1800);
        }

        return $parsed;
    }

    private static function normalizeHospitalKey(string $name): string
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower($name)) ?? strtolower($name);
    }

    private static function isGovernmentHospital(array $h): bool
    {
        $name = strtolower((string) ($h['name'] ?? ''));
        return str_contains($name, 'government')
            || str_contains($name, 'govt')
            || str_contains($name, 'general hospital')
            || str_contains($name, 'medical college')
            || str_contains($name, 'cmch')
            || str_contains($name, 'esi')
            || str_contains($name, 'taluk')
            || str_contains($name, 'district headquarters')
            || ($h['emergency_phone'] ?? '') === '108';
    }

    private static function isNarrowOutpatientClinic(string $name): bool
    {
        $n = strtolower($name);
        foreach (['dental', 'eye ', 'eye care', 'optical', 'skin', 'derma', 'hair', 'cosmetic', 'ivf', 'fertility', 'veterinary', 'pet ', 'homeo', 'ayurveda', 'siddha'] as $kw) {
            if (str_contains($n, $kw)) {
                return true;
            }
        }
        return false;
    }

    private static function matchesSpecialty(array $h, string $specLower): bool
    {
        if (self::isGovernmentHospital($h)) {
            return true;
        }
        $name = strtolower((string) ($h['name'] ?? ''));
        $services = strtolower(implode(' ', $h['emergency_services'] ?? []));
        $traumaLevel = strtolower((string) ($h['trauma_level'] ?? ''));
        $combined = $name . ' ' . $services . ' ' . $traumaLevel;

        if (str_contains($specLower, 'cardiac') || str_contains($specLower, 'heart')) {
            return !empty($h['has_cardiac_care']) || str_contains($combined, 'cardiac') || str_contains($combined, 'heart') || str_contains($combined, 'cath lab') || str_contains($combined, 'ccu') || str_contains($combined, 'multispecialty') || str_contains($combined, 'multi specialty') || str_contains($combined, 'medical');
        }
        if (str_contains($specLower, 'trauma') || str_contains($specLower, 'accident')) {
            return !empty($h['has_trauma_center']) || str_contains($combined, 'trauma') || str_contains($combined, 'casualty') || str_contains($combined, 'ortho') || str_contains($combined, 'emergency');
        }
        if (str_contains($specLower, 'stroke') || str_contains($specLower, 'brain') || str_contains($specLower, 'neuro')) {
            return !empty($h['has_stroke_unit']) || str_contains($combined, 'stroke') || str_contains($combined, 'neuro');
        }
        if (str_contains($specLower, 'breath') || str_contains($specLower, 'asthma') || str_contains($specLower, 'air') || str_contains($specLower, 'pulmonary')) {
            return !empty($h['has_icu_bed']) || str_contains($combined, 'icu') || str_contains($combined, 'ventilator') || str_contains($combined, 'pulmon');
        }
        if (str_contains($specLower, 'pregnan') || str_contains($specLower, 'maternity')) {
            return str_contains($combined, 'matern') || str_contains($combined, 'gynaec') || str_contains($combined, 'obstet') || str_contains($combined, 'women');
        }
        if (str_contains($specLower, 'pediatric') || str_contains($specLower, 'child') || str_contains($specLower, 'nicu') || str_contains($specLower, 'picu')) {
            return str_contains($combined, 'pediatric') || str_contains($combined, 'paediatric') || str_contains($combined, 'child') || str_contains($combined, 'nicu') || str_contains($combined, 'picu');
        }
        if (str_contains($specLower, 'burn') || str_contains($specLower, 'scald')) {
            return str_contains($combined, 'burn') || str_contains($combined, 'plastic');
        }
        if (str_contains($specLower, 'icu')) {
            return !empty($h['has_icu_bed']) || ((int) ($h['available_icu_beds'] ?? 0) > 0) || str_contains($combined, 'icu');
        }

        return str_contains($combined, $specLower);
    }
}
