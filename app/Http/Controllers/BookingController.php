<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Driver;
use App\Models\DriverNotification;
use App\Services\PhoneHelper;
use App\Services\WebSocketBroadcaster;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->all();

        do {
            $numericId = random_int(10000, 99999);
        } while (Booking::where('id', $numericId)->exists());

        $bookingId = 'BK-'.$numericId;
        $referenceNumber = '#LR-'.$numericId;

        $rawLat = $data['pickup_lat'] ?? $data['pickup_latitude'] ?? null;
        $rawLng = $data['pickup_lng'] ?? $data['pickup_longitude'] ?? null;

        if ($rawLat === null || $rawLng === null || (!is_numeric($rawLat) || !is_numeric($rawLng))) {
            return response()->json([
                'status' => 'error',
                'message' => 'Valid pickup GPS coordinates (latitude and longitude) are required for emergency dispatch.',
                'errors' => [
                    'pickup_lat' => ['Valid pickup latitude is required.'],
                    'pickup_lng' => ['Valid pickup longitude is required.'],
                ],
            ], 422);
        }

        $pickupLat = (float) $rawLat;
        $pickupLng = (float) $rawLng;
        if ($pickupLat === 0.0 && $pickupLng === 0.0) {
            return response()->json([
                'status' => 'error',
                'message' => 'Valid non-zero GPS coordinates are required.',
                'errors' => [
                    'pickup_lat' => ['Pickup coordinates cannot be 0, 0.'],
                    'pickup_lng' => ['Pickup coordinates cannot be 0, 0.'],
                ],
            ], 422);
        }

        $destInput = $data['destination_hospital'] ?? null;
        $isDestObj = is_array($destInput);

        $destHospitalName = $isDestObj
            ? (string) ($destInput['name'] ?? 'Emergency Trauma Center')
            : (string) ($destInput ?: 'Emergency Trauma Center');
        $destAddress = $isDestObj
            ? (string) ($destInput['address'] ?? 'Casualty & Emergency Bay')
            : 'Casualty & Emergency Bay';

        $destLat = $isDestObj
            ? (float) ($destInput['lat'] ?? $destInput['latitude'] ?? ($pickupLat + 0.015))
            : (float) ($data['destination_latitude'] ?? $data['destination_lat'] ?? ($pickupLat + 0.015));
        $destLng = $isDestObj
            ? (float) ($destInput['lng'] ?? $destInput['longitude'] ?? ($pickupLng + 0.012))
            : (float) ($data['destination_longitude'] ?? $data['destination_lng'] ?? ($pickupLng + 0.012));

        $patientPhone = trim((string) ($data['callback_phone'] ?? $data['patient_phone'] ?? ''));
        $patientName = trim((string) ($data['patient_name'] ?? ''));
        $emergencyCategory = (string) ($data['emergency_category'] ?? $data['emergency_type'] ?? 'General Emergency');
        $notes = (string) ($data['notes'] ?? $data['medical_notes'] ?? '');
        $landmark = (string) ($data['landmark'] ?? $data['pickup_landmark'] ?? '');

        $hospitalDistanceKm = PhoneHelper::calculateDrivingDistanceKm($pickupLat, $pickupLng, $destLat, $destLng);
        $hospitalEtaMin = PhoneHelper::estimateEtaMinutes($hospitalDistanceKm);

        $hospitalObj = $isDestObj ? [
            'id' => $destInput['id'] ?? 'hosp-selected',
            'name' => $destHospitalName,
            'address' => $destAddress,
            'lat' => $destLat,
            'lng' => $destLng,
            'distance_km' => round($hospitalDistanceKm, 1),
            'estimated_drive_time' => $hospitalEtaMin.' min',
        ] : [
            'id' => 'hosp-default',
            'name' => $destHospitalName,
            'address' => $destAddress,
            'lat' => $destLat,
            'lng' => $destLng,
            'distance_km' => round($hospitalDistanceKm, 1),
            'estimated_drive_time' => $hospitalEtaMin.' min',
        ];

        // Exclude drivers who currently have an ongoing emergency trip
        $busyDriverIds = Booking::whereNotNull('driver_id')
            ->whereNotIn('status', ['trip_completed', 'completed', 'cancelled'])
            ->pluck('driver_id')
            ->filter()
            ->unique()
            ->all();

        // Dispatch to drivers who are ONLINE and FREE
        $onlineDrivers = Driver::where('is_online', true)
            ->whereNotIn('id', $busyDriverIds)
            ->get();

        if ($onlineDrivers->isEmpty()) {
            // Fallback: If all online drivers were flagged busy, ensure online drivers still get alerted
            $onlineDrivers = Driver::where('is_online', true)->get();
        }

        $nearbyChannels = [];
        $initialKm = $hospitalDistanceKm;

        if ($onlineDrivers->isNotEmpty()) {
            $sorted = $onlineDrivers->sortBy(static function (Driver $d) use ($pickupLat, $pickupLng) {
                if ((float) $d->lat === 0.0 && (float) $d->lng === 0.0) {
                    return 999.0;
                }
                return PhoneHelper::calculateDrivingDistanceKm(
                    $pickupLat,
                    $pickupLng,
                    (float) $d->lat,
                    (float) $d->lng
                );
            })->values();

            /** @var Driver $nearest */
            $nearest = $sorted->first();
            $initialKm = PhoneHelper::calculateDrivingDistanceKm(
                $pickupLat,
                $pickupLng,
                (float) ($nearest->lat != 0 ? $nearest->lat : $pickupLat),
                (float) ($nearest->lng != 0 ? $nearest->lng : $pickupLng)
            );

            // Filter available drivers and dispatch to all online drivers
            $nearbyChannels = $sorted->map(static fn (Driver $d) => 'private-driver.'.$d->id)->values()->all();
        }

        $initialMeters = round($initialKm * 1000.0, 1);
        $initialEta = PhoneHelper::estimateEtaMinutes($initialKm);

        $booking = Booking::create([
            'id' => $numericId,
            'booking_reference' => $bookingId,
            'reference_number' => $referenceNumber,
            'status' => 'finding_ambulance',
            'pickup_lat' => $pickupLat,
            'pickup_lng' => $pickupLng,
            'pickup_address' => (string) ($data['pickup_address'] ?? 'Current GPS Position'),
            'pickup_landmark' => $landmark,
            'callback_phone' => $patientPhone,
            'patient_name' => $patientName,
            'emergency_category' => $emergencyCategory,
            'notes' => $notes,
            'essential_instructions' => $notes !== '' ? $notes : "Emergency {$emergencyCategory}. Priority ambulance needed immediately.",
            'destination_hospital' => $hospitalObj,
            'destination_address' => $destAddress,
            'destination_latitude' => $destLat,
            'destination_longitude' => $destLng,
            'eta_minutes' => $initialEta,
            'distance_km' => round($initialKm, 2),
            'distance_meters' => $initialMeters,
        ]);

        $requestPayload = $booking->toBookingRequestJson();

        $contactLabel = $patientName !== '' ? $patientName : ($patientPhone !== '' ? $patientPhone : $bookingId);
        DriverNotification::create([
            'booking_id' => $numericId,
            'booking_reference' => $bookingId,
            'title' => "🚨 Emergency Dispatch: {$contactLabel} ({$bookingId})",
            'message' => "Emergency {$emergencyCategory} at {$booking->pickup_address}. Phone: {$patientPhone}. Destination: {$destHospitalName}. Notes: ".($notes !== '' ? $notes : 'Immediate assistance requested.'),
            'type' => 'new_booking',
            'request_payload' => $requestPayload,
            'is_read' => false,
        ]);

        // Dispatch exclusively to surrounding free drivers on their private channels
        if (!empty($nearbyChannels)) {
            WebSocketBroadcaster::broadcast($nearbyChannels, 'BookingRequested', $requestPayload);
            WebSocketBroadcaster::broadcast($nearbyChannels, 'new-booking-request', $requestPayload);
        }

        // Also broadcast to central command center for live telemetry and hospital dispatch log
        WebSocketBroadcaster::broadcast('emergency-broadcasts', 'NewEmergencyIncident', $requestPayload);
        WebSocketBroadcaster::broadcast('emergency-broadcasts', 'BookingRequested', $requestPayload);
        WebSocketBroadcaster::broadcast('emergency-broadcasts', 'new-booking-request', $requestPayload);

        return response()->json([
            'status' => 'success',
            'booking' => $booking->toUserBookingJson(),
        ], 201);
    }

    public function history(): JsonResponse
    {
        $bookings = Booking::with('driver')
            ->orderByDesc('created_at')
            ->get()
            ->map(static fn (Booking $b) => $b->toUserBookingJson())
            ->values();

        return response()->json([
            'status' => 'success',
            'bookings' => $bookings,
            'history' => $bookings,
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $booking = $this->findBookingByKey($id);

        if (!$booking) {
            return response()->json([
                'status' => 'error',
                'message' => 'Booking not found.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'booking' => $booking->toUserBookingJson(),
        ]);
    }

    public function cancel(Request $request, string $id): JsonResponse
    {
        $reason = (string) ($request->input('reason') ?: 'Cancelled by user');
        $booking = $this->findBookingByKey($id);

        if (!$booking) {
            return response()->json([
                'status' => 'error',
                'message' => 'Booking not found.',
            ], 404);
        }

        // Ownership authorization check: authenticated user via token or X-User-Phone
        $user = $request->user();
        if (!$user) {
            $token = $request->bearerToken();
            if ($token) {
                $user = \App\Models\User::where('remember_token', $token)->first();
            }
        }

        if ($user && !empty($booking->callback_phone)) {
            $userNorm = PhoneHelper::normalizePhone10($user->phone);
            $bookingNorm = PhoneHelper::normalizePhone10($booking->callback_phone);
            if ($userNorm && $bookingNorm && $userNorm !== $bookingNorm) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized: You are only permitted to cancel your own emergency booking.',
                ], 403);
            }
        }

        $callerPhone = trim((string) ($request->input('phone') ?? $request->header('X-User-Phone') ?? ''));
        if (!empty($callerPhone) && !empty($booking->callback_phone)) {
            $callerNorm = PhoneHelper::normalizePhone10($callerPhone);
            $bookingNorm = PhoneHelper::normalizePhone10($booking->callback_phone);
            if ($callerNorm && $bookingNorm && $callerNorm !== $bookingNorm) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized: You are only permitted to cancel your own emergency booking.',
                ], 403);
            }
        }

        $driverId = $booking->driver_id;

            $booking->update([
                'status' => 'cancelled',
                'cancel_reason' => $reason,
            ]);

            if ($driverId) {
                Driver::where('id', $driverId)->update([
                    'is_online' => true,
                ]);
            }

            $cancelPayload = [
                'booking_id' => $booking->id,
                'booking_reference' => $booking->booking_reference,
                'status' => 'cancelled',
                'reason' => $reason,
                'driver_id' => $driverId,
                'message' => 'Emergency trip ' . $booking->booking_reference . ' was cancelled by requester: ' . $reason,
                'timestamp' => now()->toIso8601String(),
            ];

            $channels = [
                'private-booking.'.$booking->id,
                'private-booking.'.$booking->booking_reference,
                'private-bookings.'.$booking->id,
                'private-bookings.'.$booking->booking_reference,
                'emergency-broadcasts',
            ];

        if ($driverId) {
            $channels[] = 'private-driver.'.$driverId;
        }

        WebSocketBroadcaster::broadcast($channels, 'BookingCancelled', $cancelPayload);
        WebSocketBroadcaster::broadcast($channels, 'TripCancelled', $cancelPayload);
        WebSocketBroadcaster::broadcast($channels, 'TripStatusUpdated', $cancelPayload);

        return response()->json([
            'status' => 'success',
            'message' => 'Booking cancelled successfully.',
        ]);
    }

    public static function findBookingByKey(string $key): ?Booking
    {
        $query = Booking::with('driver');
        if (ctype_digit($key)) {
            return $query->where('id', (int) $key)->first();
        }

        return $query->where('booking_reference', $key)
            ->orWhere('reference_number', $key)
            ->orWhere('reference_number', '#LR-'.$key)
            ->first();
    }
}
