<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\AppNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Notifications screen – tabs: All | Bookings | Reviews | Updates. */
class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'category' => ['nullable', 'in:all,bookings,reviews,updates,messages'],
            'unread' => ['nullable', 'boolean'],
        ]);
        $user = $request->user();

        $items = $user->appNotifications()
            ->when(($data['category'] ?? 'all') !== 'all', fn ($q) => $q->where('category', $data['category']))
            ->when($request->boolean('unread'), fn ($q) => $q->whereNull('read_at'))
            ->latest()
            ->paginate((int) $request->input('per_page', 30));

        return NotificationResource::collection($items)->additional([
            'meta' => ['unread_count' => $user->appNotifications()->whereNull('read_at')->count()],
        ]);
    }

    public function markRead(Request $request, AppNotification $notification): JsonResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 404);
        $notification->update(['read_at' => $notification->read_at ?? now()]);

        return response()->json(['data' => new NotificationResource($notification)]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()->appNotifications()->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json(['message' => 'All caught up.']);
    }

    public function destroy(Request $request, AppNotification $notification): JsonResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 404);
        $notification->delete();

        return response()->json(['message' => 'Deleted.']);
    }
}
