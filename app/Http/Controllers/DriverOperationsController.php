<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\BookingDecline;
use App\Models\BookingStatusLog;
use App\Models\Driver;
use App\Models\DriverLocationHistory;
use App\Models\DriverNotification;
use App\Services\DriverAuthResolver;
use App\Services\PhoneHelper;
use App\Services\WebSocketBroadcaster;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DriverOperationsController extends Controller
{
    public function dashboardSummary(Request $request): JsonResponse
    {
        $driver = DriverAuthResolver::resolveDriver($request);
        if (!$driver) {
            return response()->json(['status' => 'error', 'message' => 'Unauthenticated driver session.'], 401);
        }

        $activeBooking = Booking::with('driver')
            ->where('driver_id', $driver->id)
            ->whereNotIn('status', ['trip_completed', 'completed', 'cancelled'])
            ->orderByDesc('accepted_at')
            ->first();

        $activeTrip = $activeBooking?->toTripModelJson();

        $completedTrips = Booking::where('driver_id', $driver->id)
            ->whereIn('status', ['trip_completed', 'completed'])
            ->get();
        $completedCount = $completedTrips->count();
        $completedDistance = (float) $completedTrips->sum(static fn (Booking $b) => (float) ($b->distance_km ?: 0.0));
        $criticalCount = $completedTrips->filter(static fn (Booking $b) => stripos((string) $b->emergency_category, 'critical') !== false || stripos((string) $b->emergency_category, 'cardiac') !== false)->count();
        $earningsToday = (float) $completedTrips->sum(static fn (Booking $b) => (float) ($b->earnings ?: 0.0));

        $summary = [
            'is_online' => (bool) $driver->is_online,
            'completed_trips_today' => $completedCount,
            'today_trips' => $completedCount,
            'distance_covered_km' => round($completedDistance, 1),
            'online_hours' => round($completedCount * 0.4, 1),
            'earnings_today' => round($earningsToday, 2),
            'today_earnings' => round($earningsToday, 2),
            'emergency_critical_trips' => $criticalCount,
            'total_trips' => (int) ($driver->total_trips ?: $completedCount),
            'rating' => (float) ($driver->rating ?: 5.0),
            'has_active_trip' => $activeTrip !== null,
            'active_trip_id' => $activeTrip ? $activeTrip['booking_reference'] : null,
            'active_trip' => $activeTrip,
        ];

        return response()->json(array_merge(
            ['status' => 'success'],
            $summary,
            ['summary' => $summary]
        ));
    }

    public function toggleStatus(Request $request): JsonResponse
    {
        $driver = DriverAuthResolver::resolveDriver($request);
        if (!$driver) {
            return response()->json(['status' => 'error', 'message' => 'Unauthenticated driver session.'], 401);
        }
        $updates = [];

        if ($request->has('is_online')) {
            $updates['is_online'] = (bool) $request->input('is_online');
        }
        if ($request->input('latitude') !== null) {
            $updates['lat'] = (float) $request->input('latitude');
        }
        if ($request->input('longitude') !== null) {
            $updates['lng'] = (float) $request->input('longitude');
        }

        if (!empty($updates)) {
            $driver->update($updates);
        }

        return response()->json([
            'status' => 'success',
            'is_online' => (bool) $driver->fresh()->is_online,
        ]);
    }

    public function pendingRequests(Request $request): JsonResponse
    {
        $driver = DriverAuthResolver::resolveDriver($request);
        if (!$driver) {
            return response()->json(['status' => 'error', 'message' => 'Unauthenticated driver session.'], 401);
        }

        // 1. Offline drivers should not receive pending booking requests
        if (!$driver->is_online) {
            return response()->json([
                'status' => 'success',
                'requests' => [],
            ]);
        }

        // 2. Strict exclusivity: Drivers currently on an ongoing active trip must not receive new requests
        $hasActiveTrip = Booking::where('driver_id', $driver->id)
            ->whereNotIn('status', ['trip_completed', 'completed', 'cancelled'])
            ->exists();
        if ($hasActiveTrip) {
            return response()->json([
                'status' => 'success',
                'requests' => [],
            ]);
        }

        $declinedBookingIds = BookingDecline::where('driver_id', $driver->id)
            ->pluck('booking_id')
            ->all();

        $allPending = Booking::whereIn('status', ['finding_ambulance', 'pending', 'searching'])
            ->whereNull('driver_id')
            ->when(!empty($declinedBookingIds), static fn ($q) => $q->whereNotIn('id', $declinedBookingIds))
            ->orderByDesc('created_at')
            ->get();

        $hasDriverCoords = (float) $driver->lat !== 0.0 && (float) $driver->lng !== 0.0;

        $pending = $allPending
            ->filter(static function (Booking $b) use ($driver, $hasDriverCoords): bool {
                // If driver and pickup both have coordinates, allow up to 100 km dispatch radius
                if ($hasDriverCoords && (float) $b->pickup_lat !== 0.0 && (float) $b->pickup_lng !== 0.0) {
                    $distKm = PhoneHelper::calculateDrivingDistanceKm((float) $driver->lat, (float) $driver->lng, (float) $b->pickup_lat, (float) $b->pickup_lng);
                    return $distKm <= 100.0;
                }
                return true;
            })
            ->map(static function (Booking $b) use ($driver, $hasDriverCoords): array {
                $payload = $b->toBookingRequestJson();
                if ($hasDriverCoords && (float) $b->pickup_lat !== 0.0 && (float) $b->pickup_lng !== 0.0) {
                    $distKm = PhoneHelper::calculateDrivingDistanceKm((float) $driver->lat, (float) $driver->lng, (float) $b->pickup_lat, (float) $b->pickup_lng);
                    $payload['distance_meters'] = round($distKm * 1000.0, 1);
                    $payload['estimated_eta_minutes'] = PhoneHelper::estimateEtaMinutes($distKm);
                }

                return $payload;
            })
            ->values();

        return response()->json([
            'status' => 'success',
            'requests' => $pending,
        ]);
    }

    public function acceptBooking(Request $request, string $id): JsonResponse
    {
        $driver = DriverAuthResolver::resolveDriver($request);
        if (!$driver) {
            return response()->json(['status' => 'error', 'message' => 'Unauthenticated driver session.'], 401);
        }

        return DB::transaction(function () use ($request, $id, $driver) {
            $booking = Booking::where(function ($q) use ($id) {
                $q->where('id', $id)->orWhere('booking_reference', $id);
            })->lockForUpdate()->first();

            if (!$booking) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Booking request not found.',
                ], 404);
            }

            if ($booking->driver_id !== null || !in_array($booking->status, ['finding_ambulance', 'pending', 'searching'])) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'This emergency booking was already accepted by another ambulance unit.',
                ], 409);
            }

            // Strict dispatch rule: Driver can only accept if they do NOT have an active ongoing trip
            $alreadyOnTrip = Booking::where('driver_id', $driver->id)
                ->where('id', '!=', $booking->id)
                ->whereNotIn('status', ['trip_completed', 'completed', 'cancelled'])
                ->exists();
            if ($alreadyOnTrip) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'You already have an active emergency trip in progress. Complete your ongoing trip before accepting another.',
                ], 400);
            }

            $hasDriverCoords = (float) $driver->lat !== 0.0 && (float) $driver->lng !== 0.0;
            $distKm = ($hasDriverCoords && (float) $booking->pickup_lat !== 0.0 && (float) $booking->pickup_lng !== 0.0)
                ? PhoneHelper::calculateDrivingDistanceKm((float) $driver->lat, (float) $driver->lng, (float) $booking->pickup_lat, (float) $booking->pickup_lng)
                : (float) ($booking->distance_km ?: 0.0);
            $booking->update([
                'driver_id' => $driver->id,
                'status' => 'driver_assigned',
                'accepted_at' => now(),
                'distance_km' => round($distKm, 2),
                'distance_meters' => round($distKm * 1000.0, 1),
                'eta_minutes' => PhoneHelper::estimateEtaMinutes($distKm),
            ]);
            $booking->load('driver');

            $bookingJson = $booking->toUserBookingJson();
            $userPayload = [
                'event' => 'DriverAssigned',
                'booking' => $bookingJson,
                'driver' => $bookingJson['driver'],
            ];

            $userChannels = [
                'emergency-broadcasts',
                'private-bookings.'.$booking->id,
                'private-bookings.'.$booking->booking_reference,
                'private-booking.'.$booking->id,
                'private-booking.'.$booking->booking_reference,
            ];
            WebSocketBroadcaster::broadcast($userChannels, 'DriverAssigned', $userPayload);
            WebSocketBroadcaster::broadcast($userChannels, 'BookingAccepted', $userPayload);

            // Crucial: Instantly notify ALL other drivers to dismiss this request from their screens
            $claimedPayload = [
                'booking_id' => $booking->id,
                'id' => $booking->id,
                'booking_reference' => $booking->booking_reference,
                'driver_id' => $driver->id,
                'driver_name' => $driver->name,
                'status' => 'driver_assigned',
                'message' => 'This emergency booking was accepted by another ambulance unit (' . $driver->name . ').',
            ];

            $otherDriverChannels = Driver::where('id', '!=', $driver->id)->pluck('id')->map(static fn($did) => 'private-driver.'.$did)->all();
            $dismissChannels = array_values(array_unique(array_merge(['emergency-broadcasts'], $otherDriverChannels)));

            WebSocketBroadcaster::broadcast($dismissChannels, 'BookingAcceptedByOther', $claimedPayload);
            WebSocketBroadcaster::broadcast($dismissChannels, 'BookingClaimedByOther', $claimedPayload);
            WebSocketBroadcaster::broadcast($dismissChannels, 'booking-claimed', $claimedPayload);

            return response()->json([
                'status' => 'success',
                'trip' => $booking->toTripModelJson(),
            ]);
        });
    }

    public function declineBooking(Request $request, string $id): JsonResponse
    {
        $driver = DriverAuthResolver::resolveDriver($request);
        if (!$driver) {
            return response()->json(['status' => 'error', 'message' => 'Unauthenticated driver session.'], 401);
        }
        $booking = BookingController::findBookingByKey($id);

        if ($booking) {
            BookingDecline::firstOrCreate([
                'booking_id' => $booking->id,
                'driver_id' => $driver->id,
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Booking declined. Routed to nearest backup unit.',
        ]);
    }

    public function supportInfo(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'emergency_number' => '108',
            'disaster_helpline' => '112',
            'dispatch_support_phone' => '+91 1800 108 1120',
            'support_email' => 'dispatch@liferoute.in',
            'operating_hours' => '24/7 Emergency Control Room',
        ]);
    }

    public function activeTrip(Request $request): JsonResponse
    {
        $driver = DriverAuthResolver::resolveDriver($request);
        if (!$driver) {
            return response()->json(['status' => 'error', 'message' => 'Unauthenticated driver session.'], 401);
        }

        $booking = Booking::with('driver')
            ->where('driver_id', $driver->id)
            ->whereNotIn('status', ['trip_completed', 'completed', 'cancelled'])
            ->orderByDesc('accepted_at')
            ->first();

        return response()->json([
            'status' => 'success',
            'trip' => $booking?->toTripModelJson(),
        ]);
    }

    public function tripHistory(Request $request): JsonResponse
    {
        $driver = DriverAuthResolver::resolveDriver($request);
        if (!$driver) {
            return response()->json(['status' => 'error', 'message' => 'Unauthenticated driver session.'], 401);
        }

        $trips = Booking::with('driver')
            ->where('driver_id', $driver->id)
            ->whereIn('status', ['trip_completed', 'completed'])
            ->orderByDesc('completed_at')
            ->get()
            ->map(static fn (Booking $b) => $b->toCompletedHistoryJson())
            ->values();

        return response()->json([
            'status' => 'success',
            'trips' => $trips,
        ]);
    }

    public function showTrip(Request $request, string $id): JsonResponse
    {
        $driver = DriverAuthResolver::resolveDriver($request);
        if (!$driver) {
            return response()->json(['status' => 'error', 'message' => 'Unauthenticated driver session.'], 401);
        }

        $booking = BookingController::findBookingByKey($id);
        if (!$booking) {
            return response()->json(['status' => 'error', 'message' => 'Trip not found.'], 404);
        }

        // Authorization: Driver must only access their own assigned trip or pending unassigned requests
        if ($booking->driver_id !== null && (int) $booking->driver_id !== (int) $driver->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized: Cannot access another driver\'s emergency trip.',
            ], 403);
        }

        return response()->json([
            'status' => 'success',
            'trip' => $booking->toTripModelJson(),
        ]);
    }

    public function updateTripStatus(Request $request, string $id): JsonResponse
    {
        $driver = DriverAuthResolver::resolveDriver($request);
        if (!$driver) {
            return response()->json(['status' => 'error', 'message' => 'Unauthenticated driver session.'], 401);
        }

        $booking = BookingController::findBookingByKey($id);
        if (!$booking) {
            return response()->json(['status' => 'error', 'message' => 'Trip not found.'], 404);
        }

        // Security check: Driver can only update the status of trips explicitly assigned to their unit
        if ((int) $booking->driver_id !== (int) $driver->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized: This emergency trip is not assigned to your ambulance unit.',
            ], 403);
        }
        $lat = $request->input('latitude');
        $lng = $request->input('longitude');

        $driverUpdates = [];
        if ($lat !== null && is_numeric($lat)) {
            $driverUpdates['lat'] = (float) $lat;
        }
        if ($lng !== null && is_numeric($lng)) {
            $driverUpdates['lng'] = (float) $lng;
        }
        if (!empty($driverUpdates)) {
            $driver->update($driverUpdates);
            $driver->refresh();

            // Record location telemetry breadcrumbs
            try {
                if (isset($driverUpdates['lat']) && isset($driverUpdates['lng'])) {
                    DriverLocationHistory::create([
                        'driver_id' => $driver->id,
                        'booking_id' => $activeBooking?->id,
                        'lat' => (float) $driverUpdates['lat'],
                        'lng' => (float) $driverUpdates['lng'],
                        'heading' => (float) ($driverUpdates['heading'] ?? $driver->heading ?? 0.0),
                        'speed' => (float) ($driverUpdates['speed'] ?? $driver->speed ?? 0.0),
                        'recorded_at' => now(),
                    ]);
                }
            } catch (\Throwable $e) {
                // Non-blocking telemetry history write
            }
        }

        $status = (string) $request->input('status', '');
        $bookingUpdates = [];

        if ($status === 'en_route_to_pickup') {
            $bookingUpdates['status'] = 'driver_approaching';
        } elseif ($status === 'arrived_at_pickup') {
            $bookingUpdates['status'] = 'driver_arrived';
            $bookingUpdates['arrived_pickup_at'] = now();
        } elseif ($status === 'patient_onboard') {
            $bookingUpdates['status'] = 'patient_picked_up';
            $bookingUpdates['patient_picked_up_at'] = now();
        } elseif ($status === 'in_transit_to_hospital') {
            $bookingUpdates['status'] = 'traveling_to_hospital';
        } elseif ($status === 'arrived_at_hospital') {
            $bookingUpdates['status'] = 'arrived_at_hospital';
        } elseif ($status === 'completed' || $status === 'trip_completed') {
            $bookingUpdates['status'] = 'trip_completed';
            $bookingUpdates['completed_at'] = now();
            $bookingUpdates['handover_notes'] = (string) ($request->input('handover_notes') ?: 'Handover completed at Emergency Casualty.');
            $tripDistKm = PhoneHelper::calculateDrivingDistanceKm(
                (float) $booking->pickup_lat,
                (float) $booking->pickup_lng,
                (float) $booking->destination_latitude,
                (float) $booking->destination_longitude
            );
            $bookingUpdates['distance_km'] = round($tripDistKm, 2);
            $bookingUpdates['distance_meters'] = round($tripDistKm * 1000.0, 1);
            $bookingUpdates['eta_minutes'] = 0;
            if ($booking->accepted_at) {
                $bookingUpdates['duration_minutes'] = max(1, (int) $booking->accepted_at->diffInMinutes(now()));
            }
            $driver->increment('total_trips');
        }

        $oldStatus = $booking->status;
        if (!empty($bookingUpdates)) {
            $booking->update($bookingUpdates);
            $booking->refresh();

            // Log state transition into BookingStatusLog
            try {
                BookingStatusLog::create([
                    'booking_id' => $booking->id,
                    'booking_reference' => $booking->booking_reference,
                    'from_status' => $oldStatus,
                    'to_status' => $booking->status,
                    'actor_type' => 'driver',
                    'actor_id' => $driver->id,
                    'notes' => $booking->handover_notes ?: "Status transition to {$booking->status}",
                    'lat' => (float) $driver->lat,
                    'lng' => (float) $driver->lng,
                ]);

                AuditLog::create([
                    'admin_id' => null,
                    'admin_name' => $driver->name,
                    'action' => 'TRIP_STATUS_' . strtoupper($booking->status),
                    'entity_type' => 'Booking',
                    'entity_id' => (string) $booking->id,
                    'details' => [
                        'from_status' => $oldStatus,
                        'to_status' => $booking->status,
                        'driver_id' => $driver->id,
                        'reference' => $booking->booking_reference,
                    ],
                    'ip_address' => $request->ip(),
                ]);
            } catch (\Throwable $e) {
                // Silently tolerate if table schema is in migration
            }
        }
        $booking->load('driver');
        if (!in_array($booking->status, ['trip_completed', 'completed'], true)) {
            $metrics = $booking->computePhaseMetrics();
            $booking->update([
                'distance_km' => $metrics['distance_km'],
                'distance_meters' => $metrics['distance_meters'],
                'eta_minutes' => $metrics['eta_minutes'],
            ]);
        }
        $bookingJson = $booking->toUserBookingJson();

        $eventName = $booking->status === 'trip_completed' ? 'TripCompleted' : 'TripStatusUpdated';
        $userPayload = [
            'event' => $eventName,
            'booking' => $bookingJson,
            'driver' => $bookingJson['driver'],
        ];

        WebSocketBroadcaster::broadcast([
            'emergency-broadcasts',
            'private-bookings.'.$booking->id,
            'private-bookings.'.$booking->booking_reference,
            'private-booking.'.$booking->id,
            'private-booking.'.$booking->booking_reference,
        ], $eventName, $userPayload);

        return response()->json([
            'status' => 'success',
            'trip' => $booking->toTripModelJson(),
        ]);
    }

    public function telemetry(Request $request): JsonResponse
    {
        $driver = DriverAuthResolver::resolveDriver($request);
        if (!$driver) {
            return response()->json(['status' => 'error', 'message' => 'Unauthenticated driver session.'], 401);
        }

        $bookingRef = $request->input('booking_reference');
        $activeBooking = null;
        if (is_string($bookingRef) && $bookingRef !== '') {
            $activeBooking = BookingController::findBookingByKey($bookingRef);
        }
        if (!$activeBooking) {
            $activeBooking = Booking::with('driver')
                ->where('driver_id', $driver->id)
                ->whereNotIn('status', ['trip_completed', 'completed', 'cancelled'])
                ->orderByDesc('accepted_at')
                ->first();
        }

        $driverUpdates = [];

        foreach (['latitude' => 'lat', 'longitude' => 'lng', 'heading' => 'heading', 'speed' => 'speed'] as $in => $col) {
            $val = $request->input($in);
            if ($val !== null && is_numeric($val)) {
                $driverUpdates[$col] = (float) $val;
            }
        }
        if (!empty($driverUpdates)) {
            $driver->update($driverUpdates);
            $driver->refresh();

            // Record location telemetry breadcrumbs
            try {
                if (isset($driverUpdates['lat']) && isset($driverUpdates['lng'])) {
                    DriverLocationHistory::create([
                        'driver_id' => $driver->id,
                        'booking_id' => $activeBooking?->id,
                        'lat' => (float) $driverUpdates['lat'],
                        'lng' => (float) $driverUpdates['lng'],
                        'heading' => (float) ($driverUpdates['heading'] ?? $driver->heading ?? 0.0),
                        'speed' => (float) ($driverUpdates['speed'] ?? $driver->speed ?? 0.0),
                        'recorded_at' => now(),
                    ]);
                }
            } catch (\Throwable $e) {
                // Non-blocking telemetry history write
            }
        }

        $locationPayload = [
            'event' => 'DriverLocationUpdated',
            'driver_id' => (int) $driver->id,
            'driverId' => (int) $driver->id,
            'driver_name' => $driver->name,
            'latitude' => (float) $driver->lat,
            'longitude' => (float) $driver->lng,
            'current_lat' => (float) $driver->lat,
            'current_lng' => (float) $driver->lng,
            'heading' => (float) $driver->heading,
            'speed' => (float) $driver->speed,
            'timestamp' => now()->toIso8601String(),
            'driver' => $driver->toAssignedDriverPayload(),
        ];

        $targetChannels = ['emergency-broadcasts'];

        if ($activeBooking && $activeBooking->driver_id !== null) {
            $activeBooking->setRelation('driver', $driver);
            $metrics = $activeBooking->computePhaseMetrics();
            $activeBooking->update([
                'distance_km' => $metrics['distance_km'],
                'distance_meters' => $metrics['distance_meters'],
                'eta_minutes' => $metrics['eta_minutes'],
            ]);

            $locationPayload['booking_id'] = $activeBooking->id;
            $locationPayload['booking_reference'] = $activeBooking->booking_reference;
            $locationPayload['status'] = $activeBooking->status;
            $locationPayload['distance_km'] = $metrics['distance_km'];
            $locationPayload['distance_meters'] = $metrics['distance_meters'];
            $locationPayload['eta_minutes'] = $metrics['eta_minutes'];

            $targetChannels = array_merge($targetChannels, [
                'private-bookings.'.$activeBooking->id,
                'private-bookings.'.$activeBooking->booking_reference,
                'private-booking.'.$activeBooking->id,
                'private-booking.'.$activeBooking->booking_reference,
            ]);
        }

        WebSocketBroadcaster::broadcast(array_values(array_unique($targetChannels)), 'DriverLocationUpdated', $locationPayload);

        return response()->json([
            'status' => 'success',
            'message' => 'Telemetry synchronized',
            'driver_id' => $driver->id,
            'latitude' => (float) $driver->lat,
            'longitude' => (float) $driver->lng,
            'distance_km' => $metrics['distance_km'] ?? null,
            'distance_meters' => $metrics['distance_meters'] ?? null,
            'eta_minutes' => $metrics['eta_minutes'] ?? null,
        ]);
    }

    public function notifications(): JsonResponse
    {
        $items = DriverNotification::orderByDesc('id')
            ->get()
            ->map(static fn (DriverNotification $n) => $n->toApiArray())
            ->values();

        return response()->json([
            'status' => 'success',
            'notifications' => $items,
        ]);
    }

    public function markNotificationRead(string $id): JsonResponse
    {
        if (ctype_digit($id)) {
            DriverNotification::where('id', (int) $id)->update(['is_read' => true]);
        }

        return response()->json(['status' => 'success']);
    }
}
