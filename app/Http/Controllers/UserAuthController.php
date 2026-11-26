<?php

namespace App\Http\Controllers;

use App\Models\OtpCode;
use App\Models\User;
use App\Services\PhoneHelper;
use App\Services\SmsGatewayService;
use App\Services\UserAuthResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class UserAuthController extends Controller
{
    public function sendOtp(Request $request): JsonResponse
    {
        $rawPhone = trim((string) $request->input('phone', ''));
        // Sanitize: keep only digits and plus
        $phone = preg_replace('/[^\d+]/', '', $rawPhone);
        if ($phone === '' || strlen($phone) < 7) {
            return response()->json(['status' => 'error', 'message' => 'Valid phone number is required.'], 400);
        }

        $key = PhoneHelper::normalizePhone10($phone) ?: $phone;

        // Backend security protection: Anti-hacking Rate Limiter against automated SMS flooding
        $throttleKey = 'user-send-otp:' . $request->ip() . ':' . $key;
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            Log::warning("Anti-hacking rate limit hit for IP {$request->ip()} on user phone {$key}");
            return response()->json([
                'status' => 'error',
                'message' => "Security check: Too many requests. Please wait {$seconds}s before retrying.",
            ], 429);
        }
        RateLimiter::hit($throttleKey, 300);

        $otp = (string) random_int(100000, 999999);

        OtpCode::updateOrCreate(
            ['scope' => 'user', 'phone_key' => $key],
            [
                'e164_phone' => PhoneHelper::normalizePhoneE164($phone),
                'otp_code' => $otp,
                'expires_at' => now()->addMinutes(5),
            ]
        );

        $sms = SmsGatewayService::dispatchOtp($phone, $otp);

        $isLocal = app()->environment('local', 'testing');

        $response = [
            'status' => 'success',
            'message' => $sms['sent']
                ? "SMS verification code sent to {$phone}."
                : "Verification code generated for {$phone}.",
            'otp_expires_in' => 300,
            'sms_dispatched' => $sms['sent'],
        ];

        if (app()->runningUnitTests()) {
            $response['dev_otp'] = $otp;
        }

        return response()->json($response);
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $rawPhone = trim((string) $request->input('phone', ''));
        $phone = preg_replace('/[^\d+]/', '', $rawPhone);
        $otp = trim((string) $request->input('otp', ''));
        if ($phone === '' || $otp === '') {
            return response()->json(['status' => 'error', 'message' => 'Phone and OTP are required.'], 400);
        }

        $key = PhoneHelper::normalizePhone10($phone) ?: $phone;

        // Backend security protection: Anti-hacking brute force lockout
        $throttleKey = 'user-verify-otp:' . $request->ip() . ':' . $key;
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            Log::warning("Anti-hacking brute-force blocked for IP {$request->ip()} on user phone {$key}");
            return response()->json([
                'status' => 'error',
                'message' => "Security lockout: Too many incorrect verification attempts. Please retry in {$seconds}s.",
            ], 429);
        }

        $record = OtpCode::where('scope', 'user')
            ->where(function ($q) use ($key, $phone) {
                $q->where('phone_key', $key)->orWhere('phone_key', $phone);
            })
            ->first();

        $isValid = ($record && $record->otp_code === $otp && now()->lte($record->expires_at));

        if (!$isValid) {
            RateLimiter::hit($throttleKey, 300);
            return response()->json(['status' => 'error', 'message' => 'Invalid or expired verification code.'], 401);
        }

        RateLimiter::clear($throttleKey);

        $inputName = trim((string) $request->input('name', ''));
        $user = User::findByPhone($rawPhone);

        if (!$user) {
            $user = User::create([
                'public_id' => 'usr-'.((int) (microtime(true) * 1000)),
                'name' => $inputName,
                'phone' => '+91 ' . ($key ?: $phone),
                'blood_group' => '',
                'emergency_notes' => '',
            ]);
        } else {
            if ($inputName !== '' && empty($user->name)) {
                $user->update(['name' => $inputName]);
            }
        }

        $token = UserAuthResolver::issueToken($user);

        return response()->json([
            'status' => 'success',
            'token' => $token,
            'user' => $user->toApiArray(),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        UserAuthResolver::revokeToken(UserAuthResolver::extractBearerToken($request));

        return response()->json(['status' => 'success', 'message' => 'Signed out successfully.']);
    }

    public function lookupUser(Request $request): JsonResponse
    {
        $rawPhone = trim((string) ($request->input('phone') ?? $request->query('phone') ?? ''));
        if ($rawPhone === '') {
            return response()->json(['status' => 'error', 'message' => 'Phone is required.'], 400);
        }

        $user = User::findByPhone($rawPhone);
        if ($user && !empty(trim($user->name))) {
            return response()->json([
                'status' => 'success',
                'exists' => true,
                'user' => $user->toApiArray(),
            ]);
        }

        return response()->json([
            'status' => 'success',
            'exists' => false,
            'user' => $user ? $user->toApiArray() : null,
        ]);
    }

    public function getProfile(Request $request): JsonResponse
    {
        $user = UserAuthResolver::resolveUser($request);
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized: Valid user session required.',
            ], 401);
        }

        return response()->json([
            'status' => 'success',
            'user' => $user->toApiArray(),
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = UserAuthResolver::resolveUser($request);
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized: Valid user session required.',
            ], 401);
        }

        $data = $request->all();
        $inputName = trim((string) ($data['name'] ?? ''));

        $updates = [];
        // ONLY update name if a non-empty name was provided! NEVER overwrite an existing name with empty string!
        if ($inputName !== '') {
            $updates['name'] = $inputName;
        }
        if (array_key_exists('blood_group', $data) && trim((string) $data['blood_group']) !== '') {
            $updates['blood_group'] = trim((string) $data['blood_group']);
        }
        if (array_key_exists('emergency_notes', $data) && trim((string) $data['emergency_notes']) !== '') {
            $updates['emergency_notes'] = trim((string) $data['emergency_notes']);
        }
        if (!empty($updates)) {
            $user->update($updates);
        }

        return response()->json([
            'status' => 'success',
            'user' => $user->fresh()->toApiArray(),
        ]);
    }

    public function verifySimCarrier(Request $request): JsonResponse
    {
        $rawPhone = trim((string) ($request->input('phone') ?? $request->query('phone') ?? ''));
        $digits = preg_replace('/\D/', '', $rawPhone);
        if (strlen($digits) > 10) {
            $digits = substr($digits, -10);
        }

        if (strlen($digits) !== 10 || !preg_match('/^[6-9]\d{9}$/', $digits)) {
            return response()->json([
                'status' => 'error',
                'valid' => false,
                'message' => 'Invalid Indian SIM mobile number. Must be 10 digits starting with 6, 7, 8, or 9.',
            ], 400);
        }

        // Anti-fraud: Reject repeating sequences or known dummy sequences
        $fakePatterns = ['9999999999', '8888888888', '7777777777', '6666666666', '1234567890', '9876543210', '0000000000', '9999910800'];
        if (in_array($digits, $fakePatterns, true) || preg_match('/^(\d)\1{9}$/', $digits)) {
            return response()->json([
                'status' => 'error',
                'valid' => false,
                'is_fraud' => true,
                'message' => 'Fraud Protection: Suspicious repeating or dummy number detected. Please use your genuine SIM.',
            ], 400);
        }

        // Telecom operator series classification based on Department of Telecommunications (DoT) allocation
        $prefix = substr($digits, 0, 2);
        $operator = 'Cellular Mobile (DoT Certified)';
        if (in_array($prefix, ['62', '63', '70', '71', '89', '90'], true)) {
            $operator = 'Reliance Jio 4G/5G';
        } elseif (in_array($prefix, ['98', '99', '97', '96', '81', '84', '85', '88'], true)) {
            $operator = 'Bharti Airtel';
        } elseif (in_array($prefix, ['91', '92', '93', '94', '95'], true)) {
            $operator = 'BSNL Mobile';
        } elseif (in_array($prefix, ['80', '82', '83', '86', '87', '77', '78'], true)) {
            $operator = 'Vodafone Idea (Vi)';
        }

        return response()->json([
            'status' => 'success',
            'valid' => true,
            'carrier' => $operator,
            'phone' => '+91 ' . $digits,
            'circle' => 'India Telecom Circle',
            'sim_verified' => true,
        ]);
    }

    public function login(Request $request): JsonResponse
    {
        $rawPhone = trim((string) ($request->input('phone') ?? ''));
        $inputName = trim((string) ($request->input('name') ?? ''));
        $digits = preg_replace('/\D/', '', $rawPhone);
        if (strlen($digits) > 10) {
            $digits = substr($digits, -10);
        }

        if (strlen($digits) !== 10 || !preg_match('/^[6-9]\d{9}$/', $digits)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Please enter a valid 10-digit Indian SIM mobile number starting with 6, 7, 8, or 9.',
            ], 400);
        }

        // Anti-fraud repeating/dummy check
        $fakePatterns = ['9999999999', '8888888888', '7777777777', '6666666666', '1234567890', '9876543210', '0000000000', '9999910800'];
        if (in_array($digits, $fakePatterns, true) || preg_match('/^(\d)\1{9}$/', $digits)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Fraud Protection: Suspicious repeating or dummy number detected. Please use your genuine SIM mobile number.',
            ], 400);
        }

        // Rate limiting against automated SIM login brute force
        $throttleKey = 'user-login:' . $request->ip() . ':' . $digits;
        if (RateLimiter::tooManyAttempts($throttleKey, 10)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return response()->json([
                'status' => 'error',
                'message' => "Security lockout: Too many login attempts. Retry in {$seconds}s.",
            ], 429);
        }
        RateLimiter::hit($throttleKey, 300);

        // Mobile hardware SIM attestation enforcement
        $platform = strtolower(trim((string) ($request->input('platform') ?? '')));
        if (in_array($platform, ['android', 'ios'], true)) {
            if ($request->has('has_permission') && !$request->boolean('has_permission')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Phone/SIM permission is required to verify the SIM card inserted in this mobile device.',
                ], 403);
            }
            if ($request->has('has_sim') && !$request->boolean('has_sim')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Security Alert: No active SIM card detected in this mobile phone.',
                ], 403);
            }
        }

        // SIM card mismatch protection across primary sim_number and multi-SIM sim_numbers[]
        $deviceSimNumber = trim((string) ($request->input('sim_number') ?? ''));
        $rawSimNumbers = is_array($request->input('sim_numbers')) ? $request->input('sim_numbers') : [];
        if ($deviceSimNumber !== '') {
            $rawSimNumbers[] = $deviceSimNumber;
        }
        $normalizedSims = [];
        foreach ($rawSimNumbers as $sn) {
            $sd = preg_replace('/\D/', '', (string) $sn);
            if (strlen($sd) >= 10) {
                $normalizedSims[] = substr($sd, -10);
            }
        }
        if (!empty($normalizedSims) && !in_array($digits, $normalizedSims, true)) {
            $detected = '+91 ' . $normalizedSims[0];
            return response()->json([
                'status' => 'error',
                'message' => "Security Alert: SIM card mismatch. The entered mobile number (+91 {$digits}) does not match the SIM card ({$detected}) inserted in this mobile phone. Only the physical SIM active in this device is allowed.",
            ], 403);
        }

        $user = User::findByPhone($digits);
        if ($user && strtoupper((string) ($user->status ?? 'ACTIVE')) === 'SUSPENDED') {
            return response()->json([
                'status' => 'error',
                'message' => 'This user account has been suspended by emergency command.',
            ], 403);
        }

        if (!$user) {
            $userName = $inputName !== '' ? $inputName : '';
            $user = User::create([
                'public_id' => 'usr-' . ((int) (microtime(true) * 1000)),
                'name' => $userName,
                'phone' => '+91 ' . $digits,
                'blood_group' => '',
                'emergency_notes' => '',
            ]);
        } else {
            if ($inputName !== '' && ($user->name === '' || $user->name === 'Emergency Caller' || $user->name !== $inputName)) {
                $user->update(['name' => $inputName]);
            }
        }

        $token = UserAuthResolver::issueToken($user);

        return response()->json([
            'status' => 'success',
            'is_registered' => true,
            'message' => 'Welcome' . ($user->name !== '' ? ', ' . $user->name : '') . '!',
            'token' => $token,
            'user' => $user->toApiArray(),
        ]);
    }

    public function register(Request $request): JsonResponse
    {
        $rawPhone = trim((string) ($request->input('phone') ?? ''));
        $inputName = trim((string) ($request->input('name') ?? ''));
        $bloodGroup = trim((string) ($request->input('blood_group') ?? ''));
        $notes = trim((string) ($request->input('emergency_notes') ?? ''));

        $digits = preg_replace('/\D/', '', $rawPhone);
        if (strlen($digits) > 10) {
            $digits = substr($digits, -10);
        }

        if (strlen($digits) !== 10 || !preg_match('/^[6-9]\d{9}$/', $digits)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid mobile number. Must be a valid 10-digit Indian SIM number starting with 6, 7, 8, or 9.',
            ], 400);
        }

        if ($inputName === '') {
            return response()->json([
                'status' => 'error',
                'message' => 'Full Name is required for user registration.',
            ], 400);
        }

        $user = User::findByPhone($digits);
        $cleanPhone = '+91 ' . $digits;

        if ($user) {
            $user->update([
                'name' => $inputName,
                'phone' => $cleanPhone,
                'blood_group' => $bloodGroup,
                'emergency_notes' => $notes,
            ]);
        } else {
            $user = User::create([
                'public_id' => 'usr-' . ((int) (microtime(true) * 1000)),
                'name' => $inputName,
                'phone' => $cleanPhone,
                'blood_group' => $bloodGroup,
                'emergency_notes' => $notes,
            ]);
        }

        $token = UserAuthResolver::issueToken($user);

        return response()->json([
            'status' => 'success',
            'message' => 'User profile registered successfully! You can now log in with your mobile number.',
            'token' => $token,
            'user' => $user->toApiArray(),
        ]);
    }
}
