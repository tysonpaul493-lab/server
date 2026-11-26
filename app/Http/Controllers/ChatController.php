<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\BookingChatMessage;
use App\Services\DriverAuthResolver;
use App\Services\PhoneHelper;
use App\Services\UserAuthResolver;
use App\Services\WebSocketBroadcaster;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    private function authorizeBookingParticipant(Request $request, ?Booking $booking): ?JsonResponse
    {
        $admin = AdminApiController::resolveAdmin($request);
        $driver = DriverAuthResolver::resolveDriver($request);
        $user = UserAuthResolver::resolveUser($request);

        if (!$admin && !$driver && !$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized: Authentication required for emergency chat.',
            ], 401);
        }

        if ($booking && !$admin) {
            $isDriver = $driver && (int) $booking->driver_id === (int) $driver->id;
            $isUser = false;
            if ($user) {
                if ($booking->user_id && (int) $booking->user_id === (int) $user->id) {
                    $isUser = true;
                } elseif (!$booking->user_id) {
                    $uPhone = PhoneHelper::normalizePhone10((string) $user->phone);
                    $bPhone = PhoneHelper::normalizePhone10((string) $booking->callback_phone);
                    $isUser = $uPhone !== '' && $uPhone === $bPhone;
                }
            }
            if (!$isDriver && !$isUser) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Forbidden: You are not a participant of this booking.',
                ], 403);
            }
        }

        return null;
    }

    public function index(Request $request, string $id): JsonResponse
    {
        $booking = BookingController::findBookingByKey($id);
        if ($denied = $this->authorizeBookingParticipant($request, $booking)) {
            return $denied;
        }
        $msgs = BookingChatMessage::query()
            ->where(function ($q) use ($id, $booking) {
                if (ctype_digit($id)) {
                    $q->where('booking_id', (int) $id);
                } else {
                    $q->where('booking_reference', $id);
                }
                if ($booking) {
                    $q->orWhere('booking_id', $booking->id);
                }
            })
            ->orderBy('id')
            ->get()
            ->map(static fn (BookingChatMessage $m) => $m->toApiArray())
            ->values();

        return response()->json([
            'status' => 'success',
            'messages' => $msgs,
        ]);
    }

    public function store(Request $request, string $id): JsonResponse
    {
        $booking = BookingController::findBookingByKey($id);
        if ($denied = $this->authorizeBookingParticipant($request, $booking)) {
            return $denied;
        }

        $message = trim((string) $request->input('message', ''));
        $sender = trim((string) ($request->input('sender') ?: 'user'));

        if ($message === '') {
            return response()->json([
                'status' => 'error',
                'message' => 'Message text required.',
            ], 400);
        }

        $bookingId = $booking?->id ?? (ctype_digit($id) ? (int) $id : 0);
        $bookingRef = $booking?->booking_reference ?? (str_starts_with($id, 'BK-') ? $id : 'BK-'.$id);

        $chat = BookingChatMessage::create([
            'booking_id' => $bookingId,
            'booking_reference' => $bookingRef,
            'sender' => $sender,
            'message' => $message,
        ]);

        $entry = $chat->toApiArray();
        $channels = [
            'private-bookings.'.$id,
            'private-booking.'.$id,
        ];
        if ($booking) {
            $channels[] = 'private-bookings.'.$booking->booking_reference;
            $channels[] = 'private-booking.'.$booking->booking_reference;
            if ($booking->driver_id) {
                $channels[] = 'private-driver.'.$booking->driver_id;
            }
        }

        WebSocketBroadcaster::broadcast(array_values(array_unique($channels)), 'TripChatMessage', $entry);

        return response()->json([
            'status' => 'success',
            'entry' => $entry,
        ]);
    }
}
