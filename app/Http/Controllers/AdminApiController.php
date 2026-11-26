<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\Ambulance;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Driver;
use App\Models\DriverDocument;
use App\Models\DriverLocationHistory;
use App\Models\Hospital;
use App\Models\User;
use App\Services\PhoneHelper;
use App\Services\WebSocketBroadcaster;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class AdminApiController extends Controller
{
    /**
     * Resolve authenticated admin from bearer token or header
     */
    public static function resolveAdmin(Request $request): ?Admin
    {
        $cachedAttr = $request->attributes->get('auth_admin');
        if ($cachedAttr instanceof Admin) {
            return $cachedAttr;
        }

        $token = $request->bearerToken()
            ?: (string) ($request->header('X-Admin-Token') ?: $request->input('admin_token') ?: '');
        if (!$token) {
            return null;
        }

        $cachedId = Cache::get('admin_token_' . hash('sha256', $token));
        if ($cachedId) {
            $admin = Admin::find($cachedId);
            if ($admin) {
                return $admin;
            }
        }

        return Admin::where('api_token', $token)
            ->where(function ($q) {
                $q->whereNull('last_login_at')
                    ->orWhere('last_login_at', '>=', now()->subDays(7));
            })
            ->first();
    }

    /**
     * Admin Authentication
     */
    public function login(Request $request): JsonResponse
    {
        $email = strtolower(trim((string) $request->input('email', '')));
        $password = (string) $request->input('password', '');
        $mfaCode = (string) $request->input('mfa_code', '');

        if (empty($email) || empty($password)) {
            return response()->json(['success' => false, 'message' => 'Email and password are required.'], 400);
        }

        // 1. Authenticate against MySQL `admins` table with bcrypt hash
        $admin = Admin::where('email', $email)->first();

        if (!$admin || !Hash::check($password, $admin->password)) {
            return response()->json(['success' => false, 'message' => 'Invalid email or password.'], 401);
        }

        // 2. Multi-Factor Authentication check
        if ($admin->mfa_enabled && empty($mfaCode)) {
            return response()->json([
                'success' => true,
                'mfa_required' => true,
                'message' => 'Two-Factor TOTP code required.',
            ]);
        }

        if ($admin->mfa_enabled && !empty($mfaCode)) {
            $validMfa = !empty($admin->mfa_secret) && hash_equals($admin->mfa_secret, $mfaCode);
            if (!$validMfa) {
                return response()->json(['success' => false, 'message' => 'Invalid Two-Factor authentication code.'], 401);
            }
        }

        // 3. Cryptographically secure session token (stored in cache for multi-session support + DB audit)
        $token = 'admin_' . bin2hex(random_bytes(32));
        Cache::put('admin_token_' . hash('sha256', $token), $admin->id, now()->addDays(7));
        $admin->update([
            'api_token' => $token,
            'last_login_at' => now(),
        ]);

        try {
            AuditLog::create([
                'admin_id' => $admin->id,
                'admin_name' => $admin->name,
                'action' => 'ADMIN_LOGIN',
                'entity_type' => 'Admin',
                'entity_id' => (string) $admin->id,
                'details' => ['email' => $admin->email, 'role' => $admin->role],
                'ip_address' => $request->ip(),
            ]);
        } catch (\Throwable $e) {}

        return response()->json([
            'success' => true,
            'token' => $token,
            'user' => $admin->toAuthPayload(),
            'message' => 'Administrative session initialized successfully.',
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->bearerToken()
            ?: (string) ($request->header('X-Admin-Token') ?: $request->input('admin_token') ?: '');
        if ($token !== '') {
            Cache::forget('admin_token_' . hash('sha256', $token));
            Admin::where('api_token', $token)->update(['api_token' => null]);
        }

        return response()->json(['success' => true, 'message' => 'Session terminated.']);
    }

    /**
     * Real-time Administrative Dashboard Statistics
     */
    public function dashboardStats(): JsonResponse
    {
        $totalUsers = User::count();
        $totalDrivers = Driver::count();
        $verifiedDrivers = Driver::where('verification_status', 'approved')->count();
        $pendingDrivers = Driver::where('verification_status', 'pending')->count();
        $onlineDrivers = Driver::where('is_online', true)->count();
        $offlineDrivers = max(0, $totalDrivers - $onlineDrivers);

        $busyDrivers = Booking::whereNotIn('status', ['trip_completed', 'completed', 'cancelled'])
            ->whereNotNull('driver_id')
            ->distinct('driver_id')
            ->count('driver_id');
        $availableDrivers = max(0, $onlineDrivers - $busyDrivers);

        $activeBookings = Booking::whereNotIn('status', ['trip_completed', 'completed', 'cancelled'])->count();
        $completedTrips = Booking::whereIn('status', ['trip_completed', 'completed'])->count();
        $cancelledTrips = Booking::where('status', 'cancelled')->count();

        // Real-time Revenue metrics directly computed from DB
        $totalRevenue = (float) Booking::whereIn('status', ['trip_completed', 'completed'])->sum('earnings');
        $todayRevenue = (float) Booking::whereIn('status', ['trip_completed', 'completed'])
            ->whereDate('created_at', today())
            ->sum('earnings');
        $todayCompleted = Booking::whereIn('status', ['trip_completed', 'completed'])
            ->whereDate('created_at', today())
            ->count();

        // Total ambulances based on registered drivers + any standalone ambulance records
        $registeredAmbulances = Ambulance::count();
        $totalAmbulances = max($registeredAmbulances, $totalDrivers);
        $busyAmbulances = $busyDrivers;
        $availableAmbulances = $availableDrivers;
        $maintenanceAmbulances = Ambulance::where('status', 'maintenance')->count();

        // Fetch recent active bookings for dashboard activity
        $recentBookings = Booking::with('driver')
            ->orderByDesc('id')
            ->take(8)
            ->get()
            ->map(function (Booking $b) {
                $hospName = is_array($b->destination_hospital) ? ($b->destination_hospital['name'] ?? '') : ($b->destination_address ?: '');
                $driverName = $b->driver ? $b->driver->name : 'Unassigned';
                $vehiclePlate = $b->driver ? ($b->driver->vehicle_plate ?: ($b->driver->vehicle_number ?: '')) : '';

                return [
                    'id' => (string) $b->id,
                    'reference' => $b->booking_reference ?: '#BK-' . $b->id,
                    'booking_reference' => $b->booking_reference ?: '#BK-' . $b->id,
                    'patient_name' => $b->patient_name ?: '',
                    'requester_name' => $b->patient_name ?: '',
                    'patient_phone' => $b->callback_phone ?: '',
                    'requester_phone' => $b->callback_phone ?: '',
                    'pickup_address' => $b->pickup_address ?: '',
                    'destination' => $hospName,
                    'hospital_name' => $hospName,
                    'hospital' => [
                        'name' => $hospName,
                        'address' => $b->destination_address ?: '',
                    ],
                    'driver_name' => $driverName,
                    'vehicle_plate' => $vehiclePlate,
                    'driver' => $b->driver ? [
                        'id' => (int) $b->driver->id,
                        'name' => $b->driver->name,
                        'full_name' => $b->driver->name,
                        'phone' => $b->driver->phone,
                    ] : null,
                    'ambulance' => $b->driver ? [
                        'registration_number' => $vehiclePlate,
                        'vehicle_type' => strtoupper((string) ($b->driver->vehicle_type ?: 'ALS')),
                    ] : null,
                    'status' => $this->normalizeBookingStatusForAdmin($b->status),
                    'severity' => strtoupper((string) ($b->emergency_category ?: 'CRITICAL')),
                    'distance_km' => (float) ($b->distance_km ?: 0.0),
                    'eta_minutes' => (int) ($b->eta_minutes ?: 0),
                    'timestamp' => ($b->created_at ?: now())->toIso8601String(),
                    'requested_at' => ($b->created_at ?: now())->toIso8601String(),
                    'created_at' => ($b->created_at ?: now())->toIso8601String(),
                    'accepted_at' => $b->accepted_at?->toIso8601String(),
                    'completed_at' => $b->completed_at?->toIso8601String(),
                    'status_time' => ($b->completed_at ?: ($b->accepted_at ?: ($b->created_at ?: now())))->toIso8601String(),
                ];
            });

        // Compute genuine hourly trends from DB bookings created today
        $todayBookings = Booking::whereDate('created_at', today())->get();
        $buckets = [];
        foreach (['00:00', '04:00', '08:00', '12:00', '16:00', '20:00'] as $slot) {
            $buckets[$slot] = ['hour' => $slot, 'critical' => 0, 'severe' => 0, 'moderate' => 0];
        }
        foreach ($todayBookings as $tb) {
            $h = $tb->created_at ? (int) $tb->created_at->format('G') : 0;
            $slotIdx = (int) (floor($h / 4) * 4);
            $slotKey = str_pad((string) $slotIdx, 2, '0', STR_PAD_LEFT) . ':00';
            $catLower = strtolower((string) $tb->emergency_category);
            if (str_contains($catLower, 'cardiac') || str_contains($catLower, 'stroke') || str_contains($catLower, 'critical')) {
                $buckets[$slotKey]['critical']++;
            } elseif (str_contains($catLower, 'trauma') || str_contains($catLower, 'accident') || str_contains($catLower, 'breath') || str_contains($catLower, 'burn')) {
                $buckets[$slotKey]['severe']++;
            } else {
                $buckets[$slotKey]['moderate']++;
            }
        }
        $hourlyTrends = array_values($buckets);

        // Compute genuine average response & assignment times from real timestamps
        $assignSeconds = [];
        $responseSeconds = [];
        foreach (Booking::whereNotNull('created_at')->whereNotNull('accepted_at')->get() as $tb) {
            $assignSec = max(0, $tb->created_at->diffInSeconds($tb->accepted_at, false));
            if ($assignSec >= 0 && $assignSec < 86400) {
                $assignSeconds[] = $assignSec;
            }
            $arrivalRef = $tb->arrived_pickup_at ?: $tb->completed_at;
            if ($arrivalRef) {
                $respSec = max(0, $tb->created_at->diffInSeconds($arrivalRef, false));
                if ($respSec >= 0 && $respSec < 86400) {
                    $responseSeconds[] = $respSec;
                }
            }
        }
        $avgAssignSec = !empty($assignSeconds) ? (int) round(array_sum($assignSeconds) / count($assignSeconds)) : null;
        $avgResponseSec = !empty($responseSeconds) ? (int) round(array_sum($responseSeconds) / count($responseSeconds)) : null;
        $failedDispatches = Booking::where('status', 'failed')->count();

        return response()->json([
            'success' => true,
            'data' => [
                'metrics' => [
                    'total_revenue' => ['value' => $totalRevenue, 'formatted' => '₹' . number_format($totalRevenue, 2), 'available' => true],
                    'today_revenue' => ['value' => $todayRevenue, 'formatted' => '₹' . number_format($todayRevenue, 2), 'available' => true],
                    'total_users' => ['value' => $totalUsers, 'available' => true],
                    'total_verified_drivers' => ['value' => $verifiedDrivers, 'available' => true],
                    'pending_driver_verifications' => ['value' => $pendingDrivers, 'available' => true],
                    'offline_drivers' => ['value' => $offlineDrivers, 'available' => true],
                    'online_drivers' => ['value' => $onlineDrivers, 'available' => true],
                    'total_registered_ambulances' => ['value' => $totalAmbulances, 'available' => true],
                    'available_ambulances' => ['value' => $availableAmbulances, 'available' => true],
                    'busy_ambulances' => ['value' => $busyAmbulances, 'available' => true],
                    'maintenance_ambulances' => ['value' => $maintenanceAmbulances, 'available' => true],
                    'active_bookings' => ['value' => $activeBookings, 'available' => true],
                    'completed_trips' => ['value' => $completedTrips, 'available' => true],
                    'failed_dispatches' => ['value' => $failedDispatches, 'available' => true],
                    'cancelled_trips' => ['value' => $cancelledTrips, 'available' => true],
                    'avg_response_time_seconds' => ['value' => $avgResponseSec, 'available' => $avgResponseSec !== null],
                    'avg_assignment_time_seconds' => ['value' => $avgAssignSec, 'available' => $avgAssignSec !== null],
                ],
                'hourly_trends' => $hourlyTrends,
                'recent_activity' => $recentBookings,
                'critical_alerts' => [],
            ],
        ]);
    }

    /**
     * Real-time Ambulance Fleet & Driver GPS tracking for Live Map
     */
    public function liveTracking(): JsonResponse
    {
        $ambulances = $this->formatAmbulancesList();

        return response()->json([
            'success' => true,
            'data' => $ambulances,
        ]);
    }

    /**
     * All registered Ambulances / Fleet
     */
    public function ambulances(): JsonResponse
    {
        $ambulances = $this->formatAmbulancesList();

        return response()->json([
            'success' => true,
            'data' => $ambulances,
        ]);
    }

    public function updateAmbulanceStatus(Request $request, string $id): JsonResponse
    {
        $driver = Driver::find((int) $id);
        if (!$driver) {
            return response()->json(['success' => false, 'message' => 'Ambulance unit not found.'], 404);
        }

        $status = strtoupper((string) $request->input('status', 'AVAILABLE'));
        if ($status === 'MAINTENANCE') {
            $driver->update(['is_online' => false]);
        } elseif ($status === 'AVAILABLE') {
            $driver->update(['is_online' => true]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Ambulance operational status updated successfully.',
            'status' => $status,
        ]);
    }

    public function addAmbulance(Request $request): JsonResponse
    {
        $regNumber = strtoupper(trim((string) $request->input('registration_number', '')));
        if ($regNumber === '') {
            return response()->json(['success' => false, 'message' => 'Ambulance registration number is required.'], 422);
        }
        $vehicleType = strtoupper(trim((string) $request->input('vehicle_type', 'ALS')));
        $operator = (string) $request->input('operator_name', 'Emergency Medical Services');

        $driver = null;
        if ($request->has('driver_id') && is_numeric($request->input('driver_id'))) {
            $driver = Driver::find((int) $request->input('driver_id'));
        }
        if (!$driver) {
            $driver = Driver::where('vehicle_plate', $regNumber)->orWhere('vehicle_number', $regNumber)->first();
        }
        $driverId = $driver ? (int) $driver->id : null;

        try {
            $ambulance = Ambulance::firstOrCreate(
                ['registration_number' => $regNumber],
                [
                    'vehicle_type' => strtolower($vehicleType),
                    'vehicle_type_display' => $vehicleType === 'ALS' ? 'Advanced Life Support (ALS)' : 'Basic Life Support (BLS)',
                    'model' => (string) $request->input('model', $driver?->vehicle_model ?? ''),
                    'year' => (int) $request->input('year', (int) date('Y')),
                    'status' => 'available',
                    'assigned_driver_id' => $driverId,
                    'hospital_affiliation' => $operator,
                    'equipment_list' => (array) $request->input('equipment_capabilities', []),
                    'lat' => $request->has('latitude') ? (float) $request->input('latitude') : null,
                    'lng' => $request->has('longitude') ? (float) $request->input('longitude') : null,
                    'speed' => 0.0,
                    'heading' => 0.0,
                ]
            );
            $ambId = (int) $ambulance->id;
        } catch (\Throwable $e) {
            $ambId = $driverId;
        }

        $amb = [
            'id' => $ambId,
            'registration_number' => $regNumber,
            'vehicle_type' => $vehicleType,
            'operator_name' => $operator,
            'primary_driver_id' => $driverId,
            'equipment_capabilities' => (array) $request->input('equipment_capabilities', []),
            'verification_status' => 'APPROVED',
            'fitness_expiry' => (string) $request->input('fitness_expiry', ''),
            'insurance_expiry' => (string) $request->input('insurance_expiry', ''),
            'permit_expiry' => (string) $request->input('permit_expiry', ''),
            'operational_status' => 'AVAILABLE',
            'latitude' => $request->input('latitude') !== null ? (float) $request->input('latitude') : null,
            'longitude' => $request->input('longitude') !== null ? (float) $request->input('longitude') : null,
            'speed_kmh' => 0,
            'heading' => 0,
            'battery_fuel_percentage' => null,
            'service_zone' => (string) $request->input('service_zone', ''),
        ];

        return response()->json([
            'success' => true,
            'ambulance' => $amb,
        ]);
    }

    /**
     * All registered Drivers
     */
    public function drivers(): JsonResponse
    {
        $drivers = Driver::all()->map(function (Driver $d) {
            $hasActiveTrip = Booking::where('driver_id', $d->id)
                ->whereNotIn('status', ['trip_completed', 'completed', 'cancelled'])
                ->exists();

            $completedTrips = (int) ($d->total_trips ?? Booking::where('driver_id', $d->id)->whereIn('status', ['trip_completed', 'completed'])->count());
            $totalRevenue = (float) Booking::where('driver_id', $d->id)->whereIn('status', ['trip_completed', 'completed'])->sum('earnings');

            $vehiclePlate = $d->vehicle_plate ?: ($d->vehicle_number ?: '');
            $vehicleType = strtoupper((string) ($d->vehicle_type ?: 'ALS'));
            $permitNumber = $d->permit_number ?: '';
            $insurancePolicy = $d->insurance_policy_number ?: '';
            $licenceNumber = $d->license_number ?: '';
            $status = strtoupper((string) ($d->verification_status ?: 'PENDING'));

            $dbDocs = DriverDocument::where('driver_id', $d->id)->get()->map(static function (DriverDocument $doc) use ($status) {
                return [
                    'id' => (int) $doc->id,
                    'driver_id' => (int) $doc->driver_id,
                    'document_type' => strtoupper((string) $doc->document_type),
                    'document_name' => $doc->original_filename ?: str_replace('_', ' ', strtoupper((string) $doc->document_type)),
                    'document_number' => '',
                    'specification_match' => '',
                    'file_path' => $doc->file_path ?: '',
                    'verification_status' => $status,
                ];
            })->values()->all();

            return [
                'id' => (int) $d->id,
                'user_id' => (int) $d->id,
                'full_name' => $d->name,
                'phone' => $d->phone,
                'email' => $d->email ?: '',
                'profile_photo_url' => null,
                'licence_number' => $licenceNumber,
                'licence_expiry' => $d->license_expiry_date ?: '',
                'qualification_level' => $d->operator_affiliation ?: '',
                'verification_status' => $status,
                'online_status' => $d->is_online ? 'ONLINE' : 'OFFLINE',
                'operational_status' => $hasActiveTrip ? 'ON_TRIP' : ($d->is_online ? 'AVAILABLE' : 'OFFLINE'),
                'has_active_trip' => $hasActiveTrip,
                'assigned_ambulance_id' => (int) $d->id,
                'assigned_ambulance' => [
                    'id' => (int) $d->id,
                    'registration_number' => $vehiclePlate,
                    'vehicle_type' => $vehicleType,
                    'vehicle_model' => $d->vehicle_model ?: '',
                    'vehicle_year' => $d->vehicle_year ? (int) $d->vehicle_year : null,
                    'operational_status' => $hasActiveTrip ? 'BUSY' : ($d->is_online ? 'AVAILABLE' : 'OFFLINE'),
                    'equipment_list' => is_array($d->equipment_list) ? $d->equipment_list : [],
                    'permit_number' => $permitNumber,
                    'insurance_policy' => $insurancePolicy,
                    'insurance_expiry' => $d->insurance_expiry_date ?: '',
                ],
                'completed_trips_count' => $completedTrips,
                'total_revenue' => $totalRevenue,
                'average_rating' => (float) ($d->rating ?? 0.0),
                'cancellation_rate' => 0.0,
                'current_latitude' => $d->lat !== null && (float) $d->lat != 0.0 ? (float) $d->lat : null,
                'current_longitude' => $d->lng !== null && (float) $d->lng != 0.0 ? (float) $d->lng : null,
                'last_active_at' => $d->updated_at?->toIso8601String(),
                'suspension_reason' => null,
                'documents' => $dbDocs,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $drivers,
        ]);
    }

    /**
     * Update Driver Verification Status
     */
    public function verifyDriver(Request $request, string $id): JsonResponse
    {
        $driver = Driver::find($id);
        if (!$driver) {
            return response()->json(['success' => false, 'message' => 'Driver not found.'], 404);
        }

        $action = strtoupper((string) $request->input('action', 'APPROVE'));
        $statusMap = [
            'APPROVE' => 'approved',
            'REJECT' => 'rejected',
            'REQUEST_DOCUMENTS' => 'pending',
        ];

        $driver->update([
            'verification_status' => $statusMap[$action] ?? 'approved',
        ]);

        return response()->json([
            'success' => true,
            'message' => "Driver verification status updated to {$driver->verification_status}.",
            'driver' => $driver,
        ]);
    }

    /**
     * Suspend / Reactivate Driver
     */
    public function toggleSuspension(Request $request, string $id): JsonResponse
    {
        $driver = Driver::find($id);
        if (!$driver) {
            return response()->json(['success' => false, 'message' => 'Driver not found.'], 404);
        }

        $action = strtoupper((string) $request->input('action', 'SUSPEND'));
        if ($action === 'SUSPEND') {
            $driver->update([
                'verification_status' => 'suspended',
                'is_online' => false,
            ]);
        } else {
            $driver->update([
                'verification_status' => 'approved',
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => "Driver {$driver->name} suspension toggled.",
            'driver' => $driver,
        ]);
    }

    /**
     * All Live Bookings Directory
     */
    public function bookings(Request $request): JsonResponse
    {
        $bookings = Booking::with('driver')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Booking $b) => $this->formatBookingForAdmin($b));

        return response()->json([
            'success' => true,
            'data' => $bookings,
        ]);
    }

    /**
     * Specific Booking Details
     */
    public function showBooking(string $id): JsonResponse
    {
        $booking = BookingController::findBookingByKey($id);
        if (!$booking) {
            return response()->json(['success' => false, 'message' => 'Booking not found.'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatBookingForAdmin($booking),
        ]);
    }

    /**
     * Admin Manual Driver Assignment
     */
    public function assignDriver(Request $request, string $id): JsonResponse
    {
        $booking = BookingController::findBookingByKey($id);
        if (!$booking) {
            return response()->json(['success' => false, 'message' => 'Booking not found.'], 404);
        }

        $driverId = (int) $request->input('driver_id');
        $driver = Driver::find($driverId);
        if (!$driver) {
            return response()->json(['success' => false, 'message' => 'Selected driver not found.'], 404);
        }

        $booking->update([
            'driver_id' => $driver->id,
            'status' => 'driver_assigned',
            'accepted_at' => now(),
        ]);
        $booking->refresh();

        // Broadcast real-time update to User App and Driver App
        $payload = [
            'booking_id' => $booking->id,
            'booking_reference' => $booking->booking_reference,
            'driver_id' => $driver->id,
            'driver' => $driver->toAssignedDriverPayload(),
            'status' => 'driver_assigned',
            'timestamp' => now()->toIso8601String(),
        ];

        WebSocketBroadcaster::broadcast([
            'private-bookings.' . $booking->id,
            'private-bookings.' . $booking->booking_reference,
            'private-booking.' . $booking->id,
            'private-driver.' . $driver->id,
            'emergency-broadcasts',
        ], 'BookingAccepted', $payload);

        return response()->json([
            'success' => true,
            'message' => "Ambulance {$driver->name} dispatched to booking {$booking->booking_reference}.",
            'data' => $this->formatBookingForAdmin($booking),
        ]);
    }

    /**
     * Admin Cancel Booking
     */
    public function cancelBooking(Request $request, string $id): JsonResponse
    {
        $booking = BookingController::findBookingByKey($id);
        if (!$booking) {
            return response()->json(['success' => false, 'message' => 'Booking not found.'], 404);
        }

        $reason = (string) ($request->input('reason') ?: 'Cancelled by Central Administrative Command');
        $driverId = $booking->driver_id;

        $booking->update([
            'status' => 'cancelled',
            'cancel_reason' => $reason,
        ]);
        $booking->refresh();

        if ($driverId) {
            Driver::where('id', $driverId)->update([
                'is_online' => true,
            ]);
        }

        $payload = [
            'booking_id' => $booking->id,
            'booking_reference' => $booking->booking_reference,
            'status' => 'cancelled',
            'reason' => $reason,
            'driver_id' => $driverId,
            'message' => 'Emergency trip ' . $booking->booking_reference . ' was cancelled by Admin Command: ' . $reason,
            'timestamp' => now()->toIso8601String(),
        ];

        $channels = [
            'private-bookings.' . $booking->id,
            'private-bookings.' . $booking->booking_reference,
            'private-booking.' . $booking->id,
            'emergency-broadcasts',
        ];

        if ($driverId) {
            $channels[] = 'private-driver.' . $driverId;
        }

        WebSocketBroadcaster::broadcast($channels, 'TripCancelled', $payload);
        WebSocketBroadcaster::broadcast($channels, 'BookingCancelled', $payload);
        WebSocketBroadcaster::broadcast($channels, 'TripStatusUpdated', $payload);

        return response()->json([
            'success' => true,
            'message' => 'Booking cancelled.',
            'data' => $this->formatBookingForAdmin($booking),
        ]);
    }

    public function addBookingNote(Request $request, string $id): JsonResponse
    {
        $booking = BookingController::findBookingByKey($id);
        if (!$booking) {
            return response()->json(['success' => false, 'message' => 'Booking not found.'], 404);
        }

        $note = (string) $request->input('note', '');
        $author = (string) $request->input('author', 'Dispatcher');
        $timestamp = now()->toDateTimeString();

        $entry = "[{$timestamp} | {$author}]: {$note}\n";
        $existing = (string) ($booking->handover_notes ?: '');
        $updated = $existing ? ($existing . "\n" . $entry) : $entry;

        $booking->update(['handover_notes' => $updated]);

        return response()->json([
            'success' => true,
            'operational_notes' => $updated,
        ]);
    }

    /**
     * Verified Hospital Network
     */
    public function hospitals(): JsonResponse
    {
        $hospitals = Hospital::all()->map(function (Hospital $h) {
            return [
                'id' => (int) $h->id,
                'name' => $h->name,
                'category' => $h->trauma_level ?: '',
                'address' => $h->address ?? '',
                'latitude' => $h->lat !== null ? (float) $h->lat : null,
                'longitude' => $h->lng !== null ? (float) $h->lng : null,
                'phone' => $h->emergency_phone ?: '',
                'emergency_contact' => $h->emergency_phone ?: '',
                'verified_services' => is_array($h->emergency_services) ? $h->emergency_services : [],
                'specialties' => is_array($h->emergency_services) ? $h->emergency_services : [],
                'verification_status' => $h->verified ? 'VERIFIED' : 'PENDING',
                'service_zone' => '',
                'has_live_integration' => (bool) $h->verified,
            ];
        })->values()->all();

        return response()->json([
            'success' => true,
            'data' => $hospitals,
        ]);
    }

    public function addHospital(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|min:2',
            'address' => 'required|string|min:3',
        ]);

        $name = (string) $request->input('name');
        $slug = 'hosp-' . strtolower(preg_replace('/[^a-zA-Z0-9]/', '-', $name)) . '-' . time();
        $category = (string) $request->input('category', '');
        $address = (string) $request->input('address');
        $lat = $request->input('latitude') !== null && $request->input('latitude') !== '' ? (float) $request->input('latitude') : null;
        $lng = $request->input('longitude') !== null && $request->input('longitude') !== '' ? (float) $request->input('longitude') : null;
        $phone = (string) $request->input('phone', '');
        $emergencyContact = (string) $request->input('emergency_contact', $phone);
        $services = (array) $request->input('verified_services', []);

        $hospital = Hospital::create([
            'slug' => $slug,
            'name' => $name,
            'address' => $address,
            'lat' => $lat,
            'lng' => $lng,
            'emergency_phone' => $emergencyContact,
            'emergency_services' => $services,
            'trauma_level' => $category,
            'has_trauma_center' => true,
            'has_icu_bed' => false,
            'total_icu_beds' => (int) $request->input('total_icu_beds', 0),
            'available_icu_beds' => (int) $request->input('available_icu_beds', 0),
            'total_general_beds' => (int) $request->input('total_general_beds', 0),
            'available_general_beds' => (int) $request->input('available_general_beds', 0),
            'has_cardiac_care' => false,
            'has_stroke_unit' => false,
            'has_blood_bank' => false,
            'operating_hours' => (string) $request->input('operating_hours', ''),
            'verified' => true,
        ]);

        $h = [
            'id' => (int) $hospital->id,
            'name' => $hospital->name,
            'category' => $hospital->trauma_level,
            'address' => $hospital->address,
            'latitude' => $hospital->lat !== null ? (float) $hospital->lat : null,
            'longitude' => $hospital->lng !== null ? (float) $hospital->lng : null,
            'phone' => $hospital->emergency_phone,
            'emergency_contact' => $hospital->emergency_phone,
            'verified_services' => $hospital->emergency_services,
            'specialties' => (array) $request->input('specialties', $services),
            'verification_status' => 'VERIFIED',
            'service_zone' => (string) $request->input('service_zone', ''),
            'has_live_integration' => true,
        ];

        return response()->json([
            'success' => true,
            'hospital' => $h,
        ]);
    }

    /**
     * Registered App Users
     */
    public function users(): JsonResponse
    {
        $allBookings = Booking::select('callback_phone', 'status')->get();

        $activePhoneMap = [];
        $phoneCountMap = [];

        $activeStatuses = [
            'requested', 'searching', 'assigned', 'accepted',
            'en_route_to_pickup', 'arrived_at_pickup', 'patient_on_board',
            'en_route_to_hospital', 'arrived_at_hospital', 'finding_ambulance',
            'driver_assigned', 'driver_approaching', 'driver_arrived',
            'patient_picked_up', 'patient_onboard', 'in_transit_to_hospital', 'traveling_to_hospital'
        ];

        foreach ($allBookings as $b) {
            $rawPhone = preg_replace('/[^0-9]/', '', (string) $b->callback_phone);
            $key = strlen($rawPhone) >= 10 ? substr($rawPhone, -10) : $rawPhone;
            if ($key === '') continue;

            $phoneCountMap[$key] = ($phoneCountMap[$key] ?? 0) + 1;

            if (in_array(strtolower((string) $b->status), $activeStatuses, true)) {
                $activePhoneMap[$key] = true;
            }
        }

        $users = User::all()->map(function (User $u) use ($activePhoneMap, $phoneCountMap) {
            $rawPhone = preg_replace('/[^0-9]/', '', (string) $u->phone);
            $key = strlen($rawPhone) >= 10 ? substr($rawPhone, -10) : $rawPhone;

            return [
                'id' => (int) $u->id,
                'name' => $u->name ?: '',
                'email' => $u->email ?: '',
                'phone' => $u->phone ?: '',
                'blood_group' => $u->blood_group ?: '',
                'status' => strtoupper((string) ($u->status ?: 'ACTIVE')),
                'bookings_count' => (int) ($phoneCountMap[$key] ?? 0),
                'has_active_emergency' => !empty($activePhoneMap[$key]),
                'created_at' => ($u->created_at ?: now())->toIso8601String(),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $users,
        ]);
    }

    public function toggleUserStatus(Request $request, string $id): JsonResponse
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User not found.'], 404);
        }

        $current = strtoupper((string) ($user->status ?: 'ACTIVE'));
        $nextStatus = strtoupper((string) ($request->input('status') ?: ($current === 'ACTIVE' ? 'SUSPENDED' : 'ACTIVE')));

        if (Schema::hasColumn('users', 'status')) {
            $user->update(['status' => $nextStatus]);
        }

        try {
            AuditLog::create([
                'admin_id' => self::resolveAdmin($request)?->id,
                'admin_name' => self::resolveAdmin($request)?->name ?: 'Administrator',
                'action' => 'USER_STATUS_' . $nextStatus,
                'entity_type' => 'User',
                'entity_id' => (string) $user->id,
                'details' => ['phone' => $user->phone, 'status' => $nextStatus],
                'ip_address' => $request->ip(),
            ]);
        } catch (\Throwable $e) {}

        return response()->json([
            'success' => true,
            'status' => $nextStatus,
            'message' => "User status updated to {$nextStatus}.",
        ]);
    }

    /**
     * Operational Alerts Feed
     */
    public function alerts(): JsonResponse
    {
        $states = [];
        if (Schema::hasTable('admin_alert_states')) {
            foreach (DB::table('admin_alert_states')->get() as $row) {
                $states[$row->alert_key] = $row;
            }
        }

        $alerts = [];
        $activeEmergency = Booking::whereIn('status', ['searching', 'pending', 'requested', 'finding_ambulance', 'failed'])->get();
        foreach ($activeEmergency as $b) {
            $alertKey = 'booking:' . $b->id;
            $st = $states[$alertKey] ?? null;
            if ($st && !empty($st->is_resolved)) {
                continue;
            }
            $alerts[] = [
                'id' => (int) $b->id,
                'alert_key' => $alertKey,
                'alert_code' => 'EMERGENCY_DISPATCH_QUEUE',
                'severity' => 'CRITICAL',
                'title' => "Pending Dispatch: " . ($b->booking_reference ?: '#BK-' . $b->id),
                'message' => "Emergency request by " . ($b->patient_name ?: 'Requester') . " (" . ($b->callback_phone ?: 'No phone') . ") awaiting paramedic acceptance at " . ($b->pickup_address ?: 'GPS Location'),
                'reference_type' => 'Booking',
                'reference_id' => $b->id,
                'is_acknowledged' => $st ? (bool) $st->is_acknowledged : false,
                'is_resolved' => false,
                'created_at' => ($b->created_at ?: now())->toIso8601String(),
            ];
        }

        $offlineDrivers = Driver::where('is_online', false)->where('verification_status', 'approved')->count();
        if ($offlineDrivers > 0) {
            $alertKey = 'fleet:offline';
            $st = $states[$alertKey] ?? null;
            if (!$st || empty($st->is_resolved)) {
                $alerts[] = [
                    'id' => 999999,
                    'alert_key' => $alertKey,
                    'alert_code' => 'FLEET_CAPACITY_NOTICE',
                    'severity' => 'WARNING',
                    'title' => "Paramedic Availability Advisory",
                    'message' => "{$offlineDrivers} approved paramedics are currently off duty.",
                    'reference_type' => 'Fleet',
                    'reference_id' => 0,
                    'is_acknowledged' => $st ? (bool) $st->is_acknowledged : false,
                    'is_resolved' => false,
                    'created_at' => now()->toIso8601String(),
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data' => $alerts,
        ]);
    }

    public function acknowledgeAlert(Request $request, string $id): JsonResponse
    {
        $alertKey = ($id === '999999' || $id === '999') ? 'fleet:offline' : ('booking:' . $id);
        if (Schema::hasTable('admin_alert_states')) {
            DB::table('admin_alert_states')->updateOrInsert(
                ['alert_key' => $alertKey],
                [
                    'is_acknowledged' => true,
                    'updated_by' => self::resolveAdmin($request)?->name ?: 'Administrator',
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        return response()->json(['success' => true, 'alert_key' => $alertKey, 'is_acknowledged' => true]);
    }

    public function resolveAlert(Request $request, string $id): JsonResponse
    {
        $alertKey = ($id === '999999' || $id === '999') ? 'fleet:offline' : ('booking:' . $id);
        if (Schema::hasTable('admin_alert_states')) {
            DB::table('admin_alert_states')->updateOrInsert(
                ['alert_key' => $alertKey],
                [
                    'is_acknowledged' => true,
                    'is_resolved' => true,
                    'updated_by' => self::resolveAdmin($request)?->name ?: 'Administrator',
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        return response()->json(['success' => true, 'alert_key' => $alertKey, 'is_resolved' => true]);
    }

    public function broadcastAlert(Request $request): JsonResponse
    {
        $title = (string) $request->input('title', 'Central Broadcast');
        $message = (string) $request->input('message', '');
        $severity = strtoupper((string) $request->input('severity', 'CRITICAL'));

        $alert = [
            'id' => (int) round(microtime(true) * 1000),
            'alert_code' => 'BROADCAST_ANNOUNCEMENT',
            'severity' => $severity,
            'title' => '[Announcement] ' . $title,
            'message' => $message,
            'is_acknowledged' => false,
            'is_resolved' => false,
            'created_at' => now()->toIso8601String(),
        ];

        WebSocketBroadcaster::broadcast(['emergency-broadcasts'], 'BroadcastAnnouncement', $alert);

        return response()->json([
            'success' => true,
            'alert' => $alert,
        ]);
    }

    /**
     * System Audit Trail & Ledger
     */
    public function auditLogs(): JsonResponse
    {
        $dbLogs = AuditLog::orderByDesc('created_at')->take(50)->get();
        if ($dbLogs->isNotEmpty()) {
            $logs = $dbLogs->map(function (AuditLog $l) {
                return [
                    'id' => (int) $l->id,
                    'user_name' => $l->admin_name ?: 'System Administrator',
                    'user_role' => 'Administrator',
                    'action' => strtoupper($l->action),
                    'target_type' => $l->entity_type,
                    'target_id' => $l->entity_id ?: (string) $l->id,
                    'ip_address' => $l->ip_address ?: '127.0.0.1',
                    'reason' => is_array($l->details) ? json_encode($l->details) : (string) ($l->details ?: 'System Action Recorded'),
                    'created_at' => ($l->created_at ?: now())->toIso8601String(),
                ];
            })->toArray();
        } else {
            $logs = [];
            $recentBookings = Booking::with('driver')->latest()->take(20)->get();
            foreach ($recentBookings as $idx => $b) {
                $logs[] = [
                    'id' => (int) $b->id,
                    'user_name' => $b->patient_name ?: 'Emergency Requester',
                    'user_role' => 'Citizen Patient',
                    'action' => strtoupper($b->status ?: 'REQUESTED'),
                    'target_type' => 'Booking',
                    'target_id' => $b->id,
                    'ip_address' => '127.0.0.1',
                    'reason' => "Emergency intake for " . ($b->pickup_address ?: 'Tamil Nadu Region'),
                    'created_at' => ($b->created_at ?: now())->toIso8601String(),
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data' => $logs,
        ]);
    }

    /**
     * Internal formatting helpers
     */
    private function formatAmbulancesList(): array
    {
        $items = Driver::all()->map(function (Driver $d) {
            $activeBooking = Booking::where('driver_id', $d->id)
                ->whereNotIn('status', ['trip_completed', 'completed', 'cancelled'])
                ->orderByDesc('accepted_at')
                ->first();

            $lat = $d->lat !== null && (float) $d->lat != 0.0 ? (float) $d->lat : null;
            $lng = $d->lng !== null && (float) $d->lng != 0.0 ? (float) $d->lng : null;
            $speed = (float) ($d->speed ?: 0.0);
            $heading = (float) ($d->heading ?: 0.0);

            $operationalStatus = 'AVAILABLE';
            if ($activeBooking) {
                $operationalStatus = 'BUSY';
            } elseif (!$d->is_online) {
                $operationalStatus = 'OFFLINE';
            }

            $secSincePing = $d->updated_at ? max(0, now()->diffInSeconds($d->updated_at)) : null;

            return [
                'id' => (int) $d->id,
                'registration_number' => $d->vehicle_plate ?: ($d->vehicle_number ?: ''),
                'vehicle_type' => strtoupper((string) ($d->vehicle_type ?: 'ALS')),
                'operator_name' => $d->operator_affiliation ?: '',
                'primary_driver_id' => (int) $d->id,
                'equipment_capabilities' => is_array($d->equipment_list) ? $d->equipment_list : [],
                'verification_status' => strtoupper((string) ($d->verification_status ?: 'PENDING')),
                'fitness_expiry' => '',
                'insurance_expiry' => $d->insurance_expiry_date ?: '',
                'permit_expiry' => $d->permit_expiry_date ?: '',
                'operational_status' => $operationalStatus,
                'latitude' => $lat,
                'longitude' => $lng,
                'speed_kmh' => $speed,
                'heading' => $heading,
                'last_gps_ping_at' => $d->updated_at?->toIso8601String(),
                'seconds_since_ping' => $secSincePing,
                'is_gps_stale' => $lat === null || $lng === null || ($secSincePing !== null && $secSincePing > 120),
                'battery_fuel_percentage' => null,
                'service_zone' => '',
                'primary_driver' => [
                    'id' => (int) $d->id,
                    'name' => $d->name,
                    'phone' => $d->phone,
                    'online_status' => $d->is_online ? 'ONLINE' : 'OFFLINE',
                    'rating' => (float) ($d->rating ?? 0.0),
                ],
                'active_trip' => $activeBooking ? [
                    'booking_id' => (int) $activeBooking->id,
                    'booking_reference' => $activeBooking->booking_reference ?: '#BK-' . $activeBooking->id,
                    'user_name' => $activeBooking->patient_name ?: '',
                    'user_phone' => $activeBooking->callback_phone ?: '',
                    'driver_name' => $d->name,
                    'driver_phone' => $d->phone,
                    'severity' => strtoupper((string) ($activeBooking->emergency_category ?: 'CRITICAL')),
                    'status' => $this->normalizeBookingStatusForAdmin($activeBooking->status),
                    'pickup_address' => $activeBooking->pickup_address ?: '',
                    'pickup_lat' => (float) $activeBooking->pickup_lat,
                    'pickup_lng' => (float) $activeBooking->pickup_lng,
                    'hospital_name' => is_array($activeBooking->destination_hospital) ? ($activeBooking->destination_hospital['name'] ?? ($activeBooking->destination_address ?: '')) : ($activeBooking->destination_address ?: ''),
                    'hospital_lat' => abs((float) $activeBooking->destination_latitude) > 0.0001 ? (float) $activeBooking->destination_latitude : null,
                    'hospital_lng' => abs((float) $activeBooking->destination_longitude) > 0.0001 ? (float) $activeBooking->destination_longitude : null,
                ] : null,
            ];
        })->values()->all();

        $knownPlates = [];
        foreach ($items as $it) {
            if (!empty($it['registration_number'])) {
                $knownPlates[strtoupper(trim($it['registration_number']))] = true;
            }
        }

        foreach (Ambulance::with('assignedDriver')->get() as $a) {
            $plate = strtoupper(trim((string) $a->registration_number));
            if ($plate !== '' && isset($knownPlates[$plate])) {
                continue;
            }
            $driver = $a->assignedDriver;
            if ($driver && Driver::where('id', $driver->id)->exists()) {
                continue;
            }

            $lat = $a->lat !== null && (float) $a->lat != 0.0 ? (float) $a->lat : null;
            $lng = $a->lng !== null && (float) $a->lng != 0.0 ? (float) $a->lng : null;
            $pingAt = $a->last_telemetry_at ?? $a->updated_at;
            $secSincePing = $pingAt ? max(0, now()->diffInSeconds($pingAt)) : null;

            $items[] = [
                'id' => 10000 + (int) $a->id,
                'registration_number' => $a->registration_number,
                'vehicle_type' => strtoupper($a->vehicle_type ?: 'ALS'),
                'operator_name' => $a->hospital_affiliation ?: '',
                'primary_driver_id' => null,
                'equipment_capabilities' => is_array($a->equipment_list) ? $a->equipment_list : [],
                'verification_status' => 'APPROVED',
                'fitness_expiry' => '',
                'insurance_expiry' => '',
                'permit_expiry' => '',
                'operational_status' => strtoupper($a->status ?: 'AVAILABLE'),
                'latitude' => $lat,
                'longitude' => $lng,
                'speed_kmh' => (float) ($a->speed ?: 0.0),
                'heading' => (float) ($a->heading ?: 0.0),
                'last_gps_ping_at' => $pingAt?->toIso8601String(),
                'seconds_since_ping' => $secSincePing,
                'is_gps_stale' => $lat === null || $lng === null || ($secSincePing !== null && $secSincePing > 120),
                'battery_fuel_percentage' => null,
                'service_zone' => '',
                'primary_driver' => null,
                'active_trip' => null,
            ];
        }

        return $items;
    }

    private function formatBookingForAdmin(Booking $b): array
    {
        $hosp = is_array($b->destination_hospital) ? $b->destination_hospital : [];
        $hospName = $hosp['name'] ?? ($b->destination_address ?: '');
        $rawHospLat = (float) ($hosp['lat'] ?? $b->destination_latitude ?: 0.0);
        $rawHospLng = (float) ($hosp['lng'] ?? $b->destination_longitude ?: 0.0);
        $hospLat = abs($rawHospLat) > 0.0001 ? $rawHospLat : null;
        $hospLng = abs($rawHospLng) > 0.0001 ? $rawHospLng : null;

        $driver = $b->relationLoaded('driver') ? $b->driver : ($b->driver_id ? Driver::find($b->driver_id) : null);

        static $userPhoneMap = null;
        if ($userPhoneMap === null) {
            $userPhoneMap = [];
            foreach (User::select('id', 'phone')->get() as $u) {
                $p = preg_replace('/[^0-9]/', '', (string) $u->phone);
                $k = strlen($p) >= 10 ? substr($p, -10) : $p;
                if ($k !== '') $userPhoneMap[$k] = (int) $u->id;
            }
        }
        $rawPhone = preg_replace('/[^0-9]/', '', (string) $b->callback_phone);
        $phone10 = strlen($rawPhone) >= 10 ? substr($rawPhone, -10) : $rawPhone;
        $matchedUserId = $userPhoneMap[$phone10] ?? null;

        return [
            'id' => (int) $b->id,
            'booking_reference' => $b->booking_reference ?: '#BK-' . $b->id,
            'user_id' => $matchedUserId,
            'requester_name' => $b->patient_name ?: '',
            'requester_phone' => $b->callback_phone ?: '',
            'patient_name' => $b->patient_name ?: '',
            'patient_age' => null,
            'patient_gender' => '',
            'severity' => strtoupper((string) ($b->emergency_category ?: 'CRITICAL')),
            'chief_complaint' => $b->notes ?: ($b->essential_instructions ?: ''),
            'pickup_address' => $b->pickup_address ?: '',
            'pickup_latitude' => (float) $b->pickup_lat,
            'pickup_longitude' => (float) $b->pickup_lng,
            'hospital_id' => null,
            'hospital' => [
                'id' => $hosp['id'] ?? null,
                'name' => $hospName,
                'address' => $b->destination_address ?: '',
                'latitude' => $hospLat,
                'longitude' => $hospLng,
                'phone' => '',
                'emergency_contact' => '',
                'verified_services' => [],
                'verification_status' => 'VERIFIED',
                'service_zone' => '',
                'has_live_integration' => true,
            ],
            'driver_id' => $driver ? (int) $driver->id : null,
            'driver' => $driver ? [
                'id' => (int) $driver->id,
                'full_name' => $driver->name,
                'phone' => $driver->phone,
                'licence_number' => $driver->license_number ?: '',
                'verification_status' => strtoupper((string) ($driver->verification_status ?: 'PENDING')),
                'online_status' => $driver->is_online ? 'ONLINE' : 'OFFLINE',
                'operational_status' => $this->normalizeBookingStatusForAdmin($b->status),
                'completed_trips_count' => (int) ($driver->total_trips ?? 0),
                'average_rating' => (float) ($driver->rating ?? 0.0),
                'current_latitude' => $driver->lat !== null && (float) $driver->lat != 0.0 ? (float) $driver->lat : null,
                'current_longitude' => $driver->lng !== null && (float) $driver->lng != 0.0 ? (float) $driver->lng : null,
            ] : null,
            'ambulance_id' => $driver ? (int) $driver->id : null,
            'ambulance' => $driver ? [
                'id' => (int) $driver->id,
                'registration_number' => $driver->vehicle_plate ?: ($driver->vehicle_number ?: ''),
                'vehicle_type' => strtoupper((string) ($driver->vehicle_type ?: 'ALS')),
                'operational_status' => 'BUSY',
                'latitude' => $driver->lat !== null && (float) $driver->lat != 0.0 ? (float) $driver->lat : null,
                'longitude' => $driver->lng !== null && (float) $driver->lng != 0.0 ? (float) $driver->lng : null,
                'speed_kmh' => (float) ($driver->speed ?: 0.0),
                'heading' => (float) ($driver->heading ?: 0.0),
            ] : null,
            'status' => $this->normalizeBookingStatusForAdmin($b->status),
            'distance_km' => (float) ($b->distance_km ?: 0.0),
            'eta_minutes' => (int) ($b->eta_minutes ?: 0),
            'duration_minutes' => (int) ($b->duration_minutes ?: 0),
            'total_fare' => (float) ($b->earnings ?: 0.0),
            'requested_at' => $b->created_at?->toIso8601String(),
            'dispatched_at' => $b->accepted_at?->toIso8601String(),
            'accepted_at' => $b->accepted_at?->toIso8601String(),
            'arrived_pickup_at' => $b->arrived_pickup_at?->toIso8601String(),
            'patient_onboard_at' => $b->patient_picked_up_at?->toIso8601String(),
            'arrived_hospital_at' => in_array($b->status, ['arrived_at_hospital', 'trip_completed', 'completed']) ? ($b->completed_at ?: $b->updated_at)?->toIso8601String() : null,
            'completed_at' => $b->completed_at?->toIso8601String(),
            'cancelled_at' => $b->status === 'cancelled' ? $b->updated_at?->toIso8601String() : null,
            'cancellation_reason' => $b->cancel_reason,
            'cancelled_by_type' => stripos((string) $b->cancel_reason, 'admin') !== false ? 'ADMIN' : ($b->status === 'cancelled' ? 'USER' : null),
            'operational_notes' => $b->handover_notes ?: '',
            'events' => $this->generateBookingTimelineEvents($b),
        ];
    }

    private function normalizeBookingStatusForAdmin(string $status): string
    {
        return match (strtolower(trim($status))) {
            'finding_ambulance', 'pending', 'requested' => 'REQUESTED',
            'driver_assigned', 'dispatched', 'assigned' => 'DISPATCHED',
            'accepted' => 'ACCEPTED',
            'en_route_to_pickup', 'driver_approaching', 'en_route_pickup' => 'EN_ROUTE_TO_PICKUP',
            'driver_arrived', 'arrived_at_pickup', 'arrived_pickup' => 'ARRIVED_AT_PICKUP',
            'patient_picked_up', 'patient_onboard', 'picked_up' => 'PATIENT_ON_BOARD',
            'traveling_to_hospital', 'in_transit_to_hospital', 'in_transit_hospital' => 'EN_ROUTE_TO_HOSPITAL',
            'arrived_at_hospital' => 'ARRIVED_AT_HOSPITAL',
            'trip_completed', 'completed' => 'COMPLETED',
            'cancelled' => 'CANCELLED',
            default => 'REQUESTED',
        };
    }

    private function generateBookingTimelineEvents(Booking $b): array
    {
        $events = [];

        if ($b->created_at) {
            $events[] = [
                'id' => 1,
                'booking_id' => (int) $b->id,
                'event_type' => 'REQUESTED',
                'description' => "Emergency request placed by " . ($b->patient_name ?: 'Requester') . ($b->callback_phone ? " ({$b->callback_phone})" : '') . ". Pickup: {$b->pickup_address}.",
                'actor_type' => 'CITIZEN',
                'created_at' => $b->created_at->toIso8601String(),
            ];
        }

        if ($b->driver_id && $b->accepted_at) {
            $events[] = [
                'id' => 2,
                'booking_id' => (int) $b->id,
                'event_type' => 'DISPATCHED',
                'description' => "Assigned to Driver #{$b->driver_id} ({$b->driver?->name}).",
                'actor_type' => 'DISPATCHER',
                'created_at' => $b->accepted_at->toIso8601String(),
            ];
        }

        if ($b->arrived_pickup_at || in_array($b->status, ['driver_arrived', 'arrived_at_pickup', 'patient_picked_up', 'patient_onboard', 'traveling_to_hospital', 'in_transit_to_hospital', 'arrived_at_hospital', 'trip_completed', 'completed'], true)) {
            $ts = $b->arrived_pickup_at ?? $b->patient_picked_up_at ?? $b->updated_at ?? $b->created_at;
            if ($ts) {
                $events[] = [
                    'id' => 3,
                    'booking_id' => (int) $b->id,
                    'event_type' => 'ARRIVED_AT_PICKUP',
                    'description' => 'Ambulance arrived at pickup location.',
                    'actor_type' => 'PARAMEDIC',
                    'created_at' => $ts->toIso8601String(),
                ];
            }
        }

        if ($b->patient_picked_up_at || in_array($b->status, ['patient_picked_up', 'patient_onboard', 'traveling_to_hospital', 'in_transit_to_hospital', 'arrived_at_hospital', 'trip_completed', 'completed'], true)) {
            $ts = $b->patient_picked_up_at ?? $b->updated_at ?? $b->created_at;
            if ($ts) {
                $events[] = [
                    'id' => 4,
                    'booking_id' => (int) $b->id,
                    'event_type' => 'PATIENT_ON_BOARD',
                    'description' => 'Patient loaded onboard ambulance.',
                    'actor_type' => 'PARAMEDIC',
                    'created_at' => $ts->toIso8601String(),
                ];
            }
        }

        if ($b->completed_at || in_array($b->status, ['trip_completed', 'completed'], true)) {
            $ts = $b->completed_at ?? $b->updated_at;
            if ($ts) {
                $events[] = [
                    'id' => 5,
                    'booking_id' => (int) $b->id,
                    'event_type' => 'COMPLETED',
                    'description' => 'Patient handover completed at receiving hospital.',
                    'actor_type' => 'HOSPITAL',
                    'created_at' => $ts->toIso8601String(),
                ];
            }
        }

        if ($b->status === 'cancelled') {
            $ts = $b->updated_at ?? $b->created_at;
            if ($ts) {
                $events[] = [
                    'id' => 6,
                    'booking_id' => (int) $b->id,
                    'event_type' => 'CANCELLED',
                    'description' => 'Emergency run cancelled' . ($b->cancel_reason ? ': ' . $b->cancel_reason : '.'),
                    'actor_type' => 'DISPATCHER',
                    'created_at' => $ts->toIso8601String(),
                ];
            }
        }

        return $events;
    }
}

