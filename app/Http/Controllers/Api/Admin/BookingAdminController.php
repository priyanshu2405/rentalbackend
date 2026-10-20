<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource;
use App\Http\Resources\PaymentResource;
use App\Http\Resources\SupportCaseResource;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\SupportCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingAdminController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'status' => ['nullable', 'string'],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $bookings = Booking::with(['item.photos', 'renter', 'owner'])
            ->when($data['status'] ?? null, fn ($q, $s) => $q->whereIn('status', explode(',', $s)))
            ->when($data['search'] ?? null, function ($q, $s) {
                $term = "%{$s}%";
                $q->where(fn ($w) => $w->where('code', 'like', $term)
                    ->orWhereHas('item', fn ($i) => $i->where('title', 'like', $term))
                    ->orWhereHas('renter', fn ($u) => $u->where('name', 'like', $term)->orWhere('email', 'like', $term))
                    ->orWhereHas('owner', fn ($u) => $u->where('name', 'like', $term)->orWhere('email', 'like', $term)));
            })
            ->latest()
            ->paginate($data['per_page'] ?? 25);

        return BookingResource::collection($bookings);
    }

    public function show(Booking $booking): JsonResponse
    {
        $booking->load(['item.photos', 'item.category', 'renter', 'owner', 'extensions', 'conditionChecks', 'reviews', 'conversation']);

        return response()->json([
            'data' => new BookingResource($booking),
            'payments' => PaymentResource::collection(Payment::where('booking_id', $booking->id)->with('paymentMethod')->latest()->get()),
            'support_cases' => SupportCaseResource::collection(SupportCase::where('booking_id', $booking->id)->latest()->get()),
            'payout' => $booking->payout ? [
                'amount' => $booking->payout->amount,
                'status' => $booking->payout->status,
                'available_at' => $booking->payout->available_at?->toIso8601String(),
                'paid_at' => $booking->payout->paid_at?->toIso8601String(),
            ] : null,
        ]);
    }
}
