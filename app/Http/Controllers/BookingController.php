<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Driver;
use App\Models\DriverNotification;
use App\Models\Hospital;
use App\Models\User;
use App\Services\PhoneHelper;
use App\Services\WebSocketBroadcaster;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

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
                    'pickup_lat' => ['The pickup latitude must be non-zero.'],
                    'pickup_lng' => ['The pickup longitude must be non-zero.'],
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

        $hospId = $isDestObj ? ($destInput['id'] ?? null) : null;
        if (!$hospId && !empty($destHospitalName)) {
            $matchedHosp = Hospital::where('name', 'like', "%{$destHospitalName}%")->first();
            if ($matchedHosp) {
                $hospId = (string) ($matchedHosp->slug ?: $matchedHosp->id);
            }
        }

        $hospitalObj = [
            'id' => $hospId,
            'name' => $destHospitalName,
            'address' => $destAddress,
            'lat' => $destLat,
            'lng' => $destLng,
            'distance_km' => $hospitalDistanceKm > 0 ? round($hospitalDistanceKm, 1) : null,
            'estimated_drive_time' => $hospitalDistanceKm > 0 ? $hospitalEtaMin.' min' : null,
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

        $bearerToken = $request->bearerToken();
        $userId = null;
        if ($bearerToken) {
            $cachedId = Cache::get('token_user_' . $bearerToken);
            if ($cachedId) {
                $userId = $cachedId;
            }
        }
        if (!$userId && $request->user() instanceof User) {
            $userId = $request->user()->id;
        }
        if (!$userId && !empty($patientPhone)) {
            $norm = PhoneHelper::normalizePhone10($patientPhone);
            if ($norm) {
                $matchedUser = User::where('phone_key', $norm)->orWhere('phone', 'like', "%{$norm}%")->first();
                if ($matchedUser) {
                    $userId = $matchedUser->id;
                }
            }
        }

        $booking = Booking::create([
            'id' => $numericId,
            'user_id' => $userId,
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

        // Ownership authorization check: If token user exists, verify they own the booking
        $bearerToken = $request->bearerToken();
        $authUserId = null;
        if ($bearerToken) {
            $authUserId = Cache::get('token_user_' . $bearerToken);
        }
        if (!$authUserId && $request->user()) {
            $authUserId = $request->user()->id;
        }

        if ($authUserId) {
            $authUser = User::find($authUserId);
            if ($booking->user_id && (int) $booking->user_id !== (int) $authUserId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized: You are only permitted to cancel your own emergency booking.',
                ], 403);
            }
            if ($authUser) {
                $userPhoneNorm = PhoneHelper::normalizePhone10((string) $authUser->phone);
                $bookingPhoneNorm = PhoneHelper::normalizePhone10((string) $booking->callback_phone);
                if ($userPhoneNorm && $bookingPhoneNorm && $userPhoneNorm !== $bookingPhoneNorm) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Unauthorized: You are only permitted to cancel your own emergency booking.',
                    ], 403);
                }
            }
        }

        // Ownership authorization check: If requester provides caller phone, ensure it matches
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
