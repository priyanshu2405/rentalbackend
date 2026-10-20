<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\Item;
use App\Models\Payment;
use App\Models\SupportCase;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $since = now()->subDays(30);

        $bookingsByStatus = Booking::selectRaw('status, COUNT(*) c')->groupBy('status')->pluck('c', 'status');

        $daily = Booking::where('created_at', '>=', now()->subDays(13)->startOfDay())
            ->selectRaw('DATE(created_at) d, COUNT(*) c')
            ->groupBy('d')
            ->pluck('c', 'd');
        $series = [];
        for ($i = 13; $i >= 0; $i--) {
            $day = now()->subDays($i)->toDateString();
            $series[] = ['date' => $day, 'bookings' => (int) ($daily[$day] ?? 0)];
        }

        $charges = Payment::where('type', Payment::CHARGE)->where('status', Payment::SUCCEEDED)->where('created_at', '>=', $since);
        $refunds = Payment::where('type', Payment::REFUND)->where('status', Payment::SUCCEEDED)->where('created_at', '>=', $since);

        return response()->json([
            'users' => [
                'total' => User::count(),
                'new_30d' => User::where('created_at', '>=', $since)->count(),
                'suspended' => User::whereNotNull('suspended_at')->count(),
            ],
            'listings' => [
                'active' => Item::where('status', Item::STATUS_ACTIVE)->count(),
                'pending' => Item::where('status', Item::STATUS_PENDING)->count(),
                'paused' => Item::where('status', Item::STATUS_PAUSED)->count(),
                'draft' => Item::where('status', Item::STATUS_DRAFT)->count(),
            ],
            'bookings' => [
                'by_status' => $bookingsByStatus,
                'in_progress' => Booking::whereIn('status', Booking::BLOCKING)->count(),
                'requested' => (int) ($bookingsByStatus['requested'] ?? 0),
                'daily' => $series,
            ],
            'support' => [
                'open' => SupportCase::whereIn('status', [SupportCase::SUBMITTED, SupportCase::REVIEWING])->count(),
                'submitted' => SupportCase::where('status', SupportCase::SUBMITTED)->count(),
            ],
            'money_30d' => [
                'currency' => config('neighbours.currency'),
                'gross_charges' => round((float) (clone $charges)->sum('amount'), 2),
                'platform_fees' => round((float) Booking::whereNotNull('paid_at')->where('paid_at', '>=', $since)->whereNotIn('status', [Booking::CANCELLED])->sum('service_fee'), 2),
                'refunds' => round((float) (clone $refunds)->sum('amount'), 2),
                'failed_payments' => Payment::where('type', Payment::CHARGE)->where('status', Payment::FAILED)->where('created_at', '>=', $since)->count(),
            ],
            'recent_bookings' => BookingResource::collection(
                Booking::with(['item.photos', 'renter', 'owner'])->latest()->limit(8)->get()
            ),
        ]);
    }
}
