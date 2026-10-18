<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ItemResource;
use App\Http\Resources\ReviewResource;
use App\Http\Resources\UserSummaryResource;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Public profile ("Maya" screen). */
class UserController extends Controller
{
    public function show(Request $request, User $user): JsonResponse
    {
        $listings = $user->items()->active()->with(['photos', 'category'])->latest()->limit(20)->get();
        $reviews = $user->reviewsReceived()->with('reviewer')->latest()->limit(10)->get();

        $requests = Booking::where('owner_id', $user->id)->where('created_at', '>=', now()->subMonths(6));
        $total = (clone $requests)->count();
        $answered = (clone $requests)->where(fn ($q) => $q->whereNotNull('accepted_at')->orWhereNotNull('declined_at'))->get(['created_at', 'accepted_at', 'declined_at']);

        $avgMinutes = $answered->avg(fn ($b) => $b->created_at->diffInMinutes($b->accepted_at ?? $b->declined_at));

        return response()->json([
            'data' => new UserSummaryResource($user),
            'bio' => $user->bio,
            'stats' => [
                'listings' => $user->items()->active()->count(),
                'completed_rentals' => Booking::where('owner_id', $user->id)->where('status', Booking::COMPLETED)->count(),
                'response_rate' => $total ? (int) round($answered->count() / $total * 100) : null,
                'typical_reply_minutes' => $avgMinutes ? (int) round($avgMinutes) : null,
            ],
            'listings' => ItemResource::collection($listings),
            'reviews' => ReviewResource::collection($reviews),
        ]);
    }
}
