<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\OtpCode;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class LifeRouteApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_health_and_unauthenticated_driver_endpoints(): void
    {
        $this->getJson('/health')->assertOk()->assertJson(['status' => 'ok']);
        $this->getJson('/api/v1/health')->assertOk()->assertJson(['status' => 'ok']);

        // Database seed should contain no hardcoded personal default names
        $this->assertDatabaseMissing('drivers', ['name' => 'Muruganandam']);
        $this->assertDatabaseMissing('users', ['name' => 'LifeRoute User']);

        // Protected driver profile requires a valid bearer token
        $this->getJson('/api/v1/driver/profile')->assertStatus(401);
    }

    public function test_driver_registration_and_strict_otp_verification(): void
    {
        $reg = $this->postJson('/api/v1/driver/auth/register', [
            'name' => 'Karthik Driver',
            'phone' => '9123456789',
            'email' => 'karthik@liferoute.in',
            'license_number' => 'TN-02-2025-108',
            'ambulance' => [
                'registration_number' => 'TN-37-AB-9108',
                'vehicle_type' => 'als',
                'make_model' => 'Tata Winger ALS',
                'year' => 2025,
            ],
        ])->assertOk();

        $reg->assertJsonPath('status', 'success');
        $reg->assertJsonPath('driver.name', 'Karthik Driver');
        $reg->assertJsonPath('driver.assigned_vehicle.registration_number', 'TN-37-AB-9108');
        $token = $reg->json('token');

        $this->withToken($token)
            ->getJson('/api/v1/driver/profile')
            ->assertOk()
            ->assertJsonPath('driver.name', 'Karthik Driver');

        $this->postJson('/api/v1/driver/auth/check-phone', ['phone' => '9123456789'])
            ->assertOk()
            ->assertJson(['registered' => true, 'driver_name' => 'Karthik Driver']);

        OtpCode::create([
            'scope' => 'driver',
            'phone_key' => '9123456789',
            'e164_phone' => '+919123456789',
            'otp_code' => '739201',
            'expires_at' => now()->addMinutes(5),
        ]);

        // Unregistered phone OTP cannot hijack any default driver
        OtpCode::create([
            'scope' => 'driver',
            'phone_key' => '9888877777',
            'e164_phone' => '+919888877777',
            'otp_code' => '654321',
            'expires_at' => now()->addMinutes(5),
        ]);
        $this->postJson('/api/v1/driver/auth/verify-otp', [
            'phone' => '+919888877777',
            'otp' => '654321',
        ])->assertStatus(404)->assertJsonPath('registration_required', true);

        // Static bypass must fail
        $this->postJson('/api/v1/driver/auth/verify-otp', [
            'phone' => '+919123456789',
            'otp' => '123456',
        ])->assertStatus(401);

        // Exact OTP succeeds
        $this->postJson('/api/v1/driver/auth/verify-otp', [
            'phone' => '+919123456789',
            'otp' => '739201',
        ])->assertOk()->assertJsonPath('status', 'success');
    }

    public function test_full_booking_driver_dispatch_chat_and_handover_lifecycle_with_dynamic_km(): void
    {
        $reg = $this->postJson('/api/v1/driver/auth/register', [
            'name' => 'Raghavender Rao',
            'phone' => '9000011111',
            'ambulance' => [
                'registration_number' => 'KA-01-EM-5001',
                'vehicle_type' => 'als',
            ],
        ])->assertOk();
        $driverToken = $reg->json('token');

        $userOtp = $this->postJson('/api/auth/otp/send', ['phone' => '9840010801'])->assertOk();
        $code = $userOtp->json('dev_otp');

        $uVerify = $this->postJson('/api/auth/otp/verify', ['phone' => '9840010801', 'otp' => $code])
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('user.name', '');
        $userToken = $uVerify->json('token');

        $this->withToken($userToken)
            ->putJson('/api/user/profile', ['phone' => '9840010801', 'name' => 'Anil Kumar'])
            ->assertOk()
            ->assertJsonPath('user.name', 'Anil Kumar');

        $this->getJson('/api/hospitals/nearby?lat=12.9784&lng=77.6408')
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $created = $this->withToken($userToken)->postJson('/api/bookings', [
            'patient_name' => 'Anil Kumar',
            'patient_phone' => '+91 98765 12345',
            'pickup_address' => '100 Feet Rd, Indiranagar',
            'pickup_lat' => 12.9784,
            'pickup_lng' => 77.6408,
            'emergency_category' => 'Cardiac Arrest',
            'destination_hospital' => [
                'name' => 'Manipal Hospital ICU',
                'lat' => 12.9592,
                'lng' => 77.6534,
            ],
        ])->assertCreated();

        $bookingId = $created->json('booking.id');
        $this->assertNotNull($bookingId);
        $this->assertGreaterThan(0, (float) $created->json('booking.distance_km'));

        $this->withToken($driverToken)
            ->postJson('/api/v1/driver/location/telemetry', [
                'latitude' => 12.9784,
                'longitude' => 77.6408,
            ])->assertOk();

        $this->withToken($driverToken)
            ->getJson('/api/v1/driver/requests/pending')
            ->assertOk()
            ->assertJsonCount(1, 'requests');

        $this->withToken($driverToken)
            ->postJson("/api/v1/driver/requests/{$bookingId}/accept")
            ->assertOk()
            ->assertJsonPath('trip.id', $bookingId);

        // Second claim returns 409 conflict
        $this->withToken($driverToken)
            ->postJson("/api/v1/driver/requests/{$bookingId}/accept")
            ->assertStatus(409);

        // Upload compliance document
        $this->withToken($driverToken)
            ->post('/api/v1/driver/documents/upload', [
                'document_type' => 'driving_license_front',
                'file' => UploadedFile::fake()->create('license.jpg', 50, 'image/jpeg'),
            ])->assertOk()->assertJsonPath('documents_uploaded.driving_license_front', true);

        // Telemetry updates dynamic KM & ETA along driver -> pickup
        $telPickup = $this->withToken($driverToken)
            ->postJson('/api/v1/driver/location/telemetry', [
                'booking_reference' => "BK-{$bookingId}",
                'latitude' => 12.9750,
                'longitude' => 77.6350,
                'heading' => 95.5,
                'speed' => 48.2,
            ])->assertOk();
        $this->assertGreaterThan(0, (float) $telPickup->json('distance_km'));

        foreach (['en_route_to_pickup', 'arrived_at_pickup', 'patient_onboard', 'in_transit_to_hospital', 'arrived_at_hospital'] as $step) {
            $res = $this->withToken($driverToken)
                ->patchJson("/api/v1/driver/trips/{$bookingId}/status", [
                    'status' => $step,
                    'latitude' => 12.96,
                    'longitude' => 77.65,
                ])->assertOk();
            if ($step === 'arrived_at_pickup' || $step === 'arrived_at_hospital') {
                $this->assertSame(0.0, (float) $res->json('trip.distance_remaining_meters'));
            }
        }

        $this->withToken($driverToken)->postJson("/api/bookings/{$bookingId}/chat", [
            'message' => 'Arriving at Gate 2 now',
            'sender' => 'driver',
        ])->assertOk();

        $this->withToken($userToken)->getJson("/api/bookings/{$bookingId}/chat")
            ->assertOk()
            ->assertJsonCount(1, 'messages');

        $this->withToken($driverToken)
            ->patchJson("/api/v1/driver/trips/{$bookingId}/status", [
                'status' => 'completed',
                'handover_notes' => 'Transferred to ICU Bay #3',
            ])->assertOk();

        $this->withToken($userToken)->getJson('/api/bookings/history')->assertOk()->assertJsonCount(1, 'bookings');
        $this->withToken($driverToken)->getJson('/api/v1/driver/trips/history')->assertOk()->assertJsonCount(1, 'trips');
        $this->assertDatabaseHas('bookings', ['id' => $bookingId, 'status' => 'trip_completed']);
    }

    public function test_booking_creation_rejects_missing_or_zero_gps(): void
    {
        $uToken = $this->postJson('/api/user/auth/login', [
            'phone' => '9876500001',
            'name' => 'Ramesh Test',
        ])->assertOk()->json('token');

        // 0.0, 0.0 coordinates must be rejected with 422
        $this->withToken($uToken)->postJson('/api/bookings', [
            'patient_name' => 'Ramesh Test',
            'patient_phone' => '+91 98765 00001',
            'pickup_address' => 'Empty GPS Location',
            'pickup_lat' => 0.0,
            'pickup_lng' => 0.0,
        ])->assertStatus(422)->assertJsonValidationErrors(['pickup_lat', 'pickup_lng']);

        // Missing coordinates must be rejected with 422
        $this->withToken($uToken)->postJson('/api/bookings', [
            'patient_name' => 'Ramesh Test',
            'patient_phone' => '+91 98765 00001',
            'pickup_address' => 'Null GPS Location',
        ])->assertStatus(422)->assertJsonValidationErrors(['pickup_lat', 'pickup_lng']);
    }

    public function test_trip_longer_than_fifteen_minutes_is_not_auto_completed(): void
    {
        $reg = $this->postJson('/api/v1/driver/auth/register', [
            'name' => 'Long Journey Pilot',
            'phone' => '9444455555',
            'ambulance' => [
                'registration_number' => 'TN-01-XX-9999',
                'vehicle_type' => 'icu',
            ],
        ])->assertOk();
        $driverToken = $reg->json('token');
        $driverId = $reg->json('driver.id');

        $uToken = $this->postJson('/api/user/auth/login', [
            'phone' => '9111122222',
            'name' => 'Critical Patient',
        ])->assertOk()->json('token');

        $created = $this->withToken($uToken)->postJson('/api/bookings', [
            'patient_name' => 'Critical Patient',
            'patient_phone' => '+91 91111 22222',
            'pickup_address' => 'Highways Junction',
            'pickup_lat' => 11.0168,
            'pickup_lng' => 76.9558,
            'status' => 'en_route_to_pickup',
        ])->assertCreated();

        $bookingId = $created->json('booking.id');

        // Simulate that trip was created 25 minutes ago
        \App\Models\Booking::where('id', $bookingId)->update([
            'driver_id' => $driverId,
            'status' => 'in_transit_to_hospital',
            'created_at' => now()->subMinutes(25),
            'updated_at' => now()->subMinutes(20),
        ]);

        // Calling pendingRequests must NEVER auto-complete active trips
        $this->withToken($driverToken)
            ->getJson('/api/v1/driver/requests/pending')
            ->assertOk();

        $booking = \App\Models\Booking::find($bookingId);
        $this->assertSame('in_transit_to_hospital', $booking->status);
        $this->assertNotEquals('trip_completed', $booking->status);
    }

    public function test_driver_cannot_access_or_update_another_drivers_trip(): void
    {
        $driver1 = $this->postJson('/api/v1/driver/auth/register', [
            'name' => 'Driver One',
            'phone' => '9777711111',
            'ambulance' => ['registration_number' => 'TN-11-AA-1111', 'vehicle_type' => 'als'],
        ])->assertOk();
        $token1 = $driver1->json('token');

        $driver2 = $this->postJson('/api/v1/driver/auth/register', [
            'name' => 'Driver Two',
            'phone' => '9777722222',
            'ambulance' => ['registration_number' => 'TN-22-BB-2222', 'vehicle_type' => 'bls'],
        ])->assertOk();
        $token2 = $driver2->json('token');

        $uToken = $this->postJson('/api/user/auth/login', [
            'phone' => '9777733333',
            'name' => 'Protected Patient',
        ])->assertOk()->json('token');

        $booking = $this->withToken($uToken)->postJson('/api/bookings', [
            'patient_name' => 'Protected Patient',
            'patient_phone' => '+91 97777 33333',
            'pickup_address' => 'Driver One Sector',
            'pickup_lat' => 11.02,
            'pickup_lng' => 76.96,
        ])->assertCreated();
        $bookingId = $booking->json('booking.id');

        // Driver 1 accepts booking
        $this->withToken($token1)
            ->postJson("/api/v1/driver/requests/{$bookingId}/accept")
            ->assertOk();

        // Driver 2 attempts to inspect Driver 1's trip -> 403 Forbidden
        $this->withToken($token2)
            ->getJson("/api/v1/driver/trips/{$bookingId}")
            ->assertStatus(403);

        // Driver 2 attempts to change Driver 1's trip status -> 403 Forbidden
        $this->withToken($token2)
            ->patchJson("/api/v1/driver/trips/{$bookingId}/status", [
                'status' => 'trip_completed',
            ])->assertStatus(403);
    }

    public function test_user_cannot_cancel_another_users_booking(): void
    {
        // User 1 signs up and books ambulance
        $user1Otp = $this->postJson('/api/auth/otp/send', ['phone' => '9888811111'])->assertOk();
        $u1Token = $this->postJson('/api/auth/otp/verify', [
            'phone' => '9888811111',
            'otp' => $user1Otp->json('dev_otp'),
        ])->assertOk()->json('token');

        $booking = $this->withToken($u1Token)->postJson('/api/bookings', [
            'patient_name' => 'User One Patient',
            'patient_phone' => '+91 98888 11111',
            'pickup_address' => 'User One Residence',
            'pickup_lat' => 11.05,
            'pickup_lng' => 76.98,
        ])->assertCreated();
        $bookingId = $booking->json('booking.id');

        // User 2 signs up
        $user2Otp = $this->postJson('/api/auth/otp/send', ['phone' => '9888822222'])->assertOk();
        $u2Token = $this->postJson('/api/auth/otp/verify', [
            'phone' => '9888822222',
            'otp' => $user2Otp->json('dev_otp'),
        ])->assertOk()->json('token');

        // User 2 tries to cancel User 1's booking -> 403 Forbidden
        $this->withToken($u2Token)
            ->postJson("/api/bookings/{$bookingId}/cancel", [
                'reason' => 'Malicious unauthorized cancellation attempt',
            ])->assertStatus(403);

        // User 1 can cancel their own booking
        $this->withToken($u1Token)
            ->postJson("/api/bookings/{$bookingId}/cancel", [
                'reason' => 'Patient stabilized by family doctor',
            ])->assertOk()->assertJsonPath('status', 'success');

        $this->assertSame('cancelled', \App\Models\Booking::find($bookingId)->status);
    }

    public function test_unauthenticated_admin_user_booking_and_db_export_are_rejected(): void
    {
        $this->getJson('/api/v1/dashboard/stats')->assertStatus(401);
        $this->getJson('/api/v1/users')->assertStatus(401);
        $this->getJson('/api/v1/tracking/live')->assertStatus(401);
        $this->getJson('/api/v1/database/export')->assertStatus(401);
        $this->getJson('/api/user/profile')->assertStatus(401);
        $this->postJson('/api/bookings', ['pickup_lat' => 13.0, 'pickup_lng' => 80.0])->assertStatus(401);
        $this->getJson('/api/bookings/12345')->assertStatus(401);

        // Valid admin bearer token succeeds and logout revokes it
        $adminToken = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@gmail.com',
            'password' => 'Admin@1234',
        ])->assertOk()->json('token');

        $this->withToken($adminToken)->getJson('/api/v1/dashboard/stats')->assertOk();
        $this->withToken($adminToken)->postJson('/api/v1/auth/logout')->assertOk();
        $this->withToken($adminToken)->getJson('/api/v1/dashboard/stats')->assertStatus(401);
    }

    public function test_user_sim_mismatch_and_broadcasting_auth_guards(): void
    {
        // Mobile client without active SIM must fail
        $this->postJson('/api/user/auth/login', [
            'phone' => '9840011999',
            'name' => 'No Sim User',
            'platform' => 'android',
            'has_sim' => false,
        ])->assertStatus(403);

        // Entered phone mismatched with device SIM must fail
        $this->postJson('/api/user/auth/login', [
            'phone' => '9840011223',
            'name' => 'Sim Mismatch User',
            'platform' => 'android',
            'has_sim' => true,
            'has_permission' => true,
            'sim_number' => '+919998887776',
        ])->assertStatus(403);

        // Broadcasting auth denies unauthenticated and cross-driver requests
        $this->postJson('/api/broadcasting/auth', [
            'channel_name' => 'private-driver.1',
            'socket_id' => '1234.5678',
        ])->assertStatus(401);

        $d1 = $this->postJson('/api/v1/driver/auth/register', [
            'name' => 'Auth Driver',
            'phone' => '9666611111',
            'ambulance' => ['registration_number' => 'TN-55-XX-1001', 'vehicle_type' => 'als'],
        ])->assertOk();
        $dToken = $d1->json('token');
        $dId = $d1->json('driver.id');

        $this->withToken($dToken)->postJson('/api/broadcasting/auth', [
            'channel_name' => 'private-driver.' . ($dId + 100),
            'socket_id' => '1234.5678',
        ])->assertStatus(403);

        $this->withToken($dToken)->postJson('/api/broadcasting/auth', [
            'channel_name' => "private-driver.{$dId}",
            'socket_id' => '1234.5678',
        ])->assertOk()->assertJsonStructure(['auth']);
    }
}
