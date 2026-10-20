<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminUserResource;
use App\Http\Resources\BookingResource;
use App\Http\Resources\ItemResource;
use App\Models\Booking;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class UserAdminController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'filter' => ['nullable', 'in:all,suspended,admins,unverified'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $users = User::query()
            ->withCount(['items', 'rentals', 'ownerBookings'])
            ->when($data['search'] ?? null, function ($q, $s) {
                $term = "%{$s}%";
                $q->where(fn ($w) => $w->where('name', 'like', $term)->orWhere('email', 'like', $term)->orWhere('phone', 'like', $term)->orWhere('id', $s));
            })
            ->when(($data['filter'] ?? null) === 'suspended', fn ($q) => $q->whereNotNull('suspended_at'))
            ->when(($data['filter'] ?? null) === 'admins', fn ($q) => $q->where('is_admin', true))
            ->when(($data['filter'] ?? null) === 'unverified', fn ($q) => $q->whereNull('email_verified_at'))
            ->latest()
            ->paginate($data['per_page'] ?? 25);

        return AdminUserResource::collection($users);
    }

    public function show(User $user): JsonResponse
    {
        $user->loadCount(['items', 'rentals', 'ownerBookings']);

        return response()->json([
            'data' => new AdminUserResource($user),
            'listings' => ItemResource::collection($user->items()->with(['photos', 'category'])->latest()->limit(20)->get()),
            'bookings' => BookingResource::collection(
                Booking::forUser($user)->with(['item.photos', 'renter', 'owner'])->latest()->limit(20)->get()
            ),
        ]);
    }

    public function suspend(Request $request, User $user, NotificationService $notifications): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        if ($user->id === $request->user()->id) {
            throw ValidationException::withMessages(['user' => "You can't suspend your own account."]);
        }
        if ($user->is_admin) {
            throw ValidationException::withMessages(['user' => 'Remove admin access before suspending this account.']);
        }

        $user->forceFill(['suspended_at' => now(), 'suspension_reason' => $data['reason']])->save();
        $user->tokens()->delete(); // signs them out everywhere
        $user->items()->where('status', 'active')->update(['status' => 'paused']);

        return response()->json(['data' => new AdminUserResource($user->loadCount(['items', 'rentals', 'ownerBookings']))]);
    }

    public function unsuspend(User $user): JsonResponse
    {
        $user->forceFill(['suspended_at' => null, 'suspension_reason' => null])->save();

        return response()->json(['data' => new AdminUserResource($user->loadCount(['items', 'rentals', 'ownerBookings']))]);
    }

    public function setAdmin(Request $request, User $user): JsonResponse
    {
        $data = $request->validate(['is_admin' => ['required', 'boolean']]);

        if ($user->id === $request->user()->id && ! $data['is_admin']) {
            throw ValidationException::withMessages(['user' => "You can't remove your own admin access."]);
        }

        $user->forceFill(['is_admin' => $data['is_admin']])->save();

        return response()->json(['data' => new AdminUserResource($user->loadCount(['items', 'rentals', 'ownerBookings']))]);
    }
}
