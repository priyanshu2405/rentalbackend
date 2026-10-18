<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Booking;
use App\Models\Item;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    /** "Set up your profile" + "Edit profile". */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'bio' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'city' => ['sometimes', 'nullable', 'string', 'max:100'],
            'province' => ['sometimes', 'nullable', 'string', 'max:50'],
            'postal_code' => ['sometimes', 'nullable', 'string', 'max:10', 'regex:/^[A-Za-z]\d[A-Za-z][ -]?\d[A-Za-z]\d$/'],
        ]);

        if (isset($data['postal_code'])) {
            $data['postal_code'] = strtoupper(preg_replace('/^(\w{3})[ -]?(\w{3})$/', '$1 $2', $data['postal_code']));
        }
        if (! empty($data['city'])) {
            $province = $data['province'] ?? $user->province;
            $data['location_label'] = $province ? "{$data['city']}, {$province}" : $data['city'];
        }

        $user->update($data);

        return response()->json(['user' => new UserResource($user)]);
    }

    public function uploadAvatar(Request $request): JsonResponse
    {
        $request->validate(['avatar' => ['required', 'image', 'max:5120']]);
        $user = $request->user();

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }
        $user->update(['avatar_path' => $request->file('avatar')->store('avatars', 'public')]);

        return response()->json(['user' => new UserResource($user)]);
    }

    /** "Location and notifications" onboarding step + Location permissions setting. */
    public function updateLocation(Request $request): JsonResponse
    {
        $data = $request->validate([
            'latitude' => ['required_with:longitude', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['required_with:latitude', 'nullable', 'numeric', 'between:-180,180'],
            'label' => ['nullable', 'string', 'max:150'],
            'city' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:50'],
            'permission' => ['nullable', 'in:while_using,always,never'],
        ]);

        $request->user()->update(array_filter([
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'location_label' => $data['label'] ?? null,
            'city' => $data['city'] ?? null,
            'province' => $data['province'] ?? null,
            'location_permission' => $data['permission'] ?? null,
        ], fn ($v) => ! is_null($v)));

        return response()->json(['user' => new UserResource($request->user())]);
    }

    public function updateNotificationSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'new_items' => ['sometimes', 'boolean'],
            'messages' => ['sometimes', 'boolean'],
            'saved_items' => ['sometimes', 'boolean'],
            'rental_updates' => ['sometimes', 'boolean'],
        ]);

        $map = ['new_items' => 'notify_new_items', 'messages' => 'notify_messages', 'saved_items' => 'notify_saved_items', 'rental_updates' => 'notify_rental_updates'];
        $request->user()->update(collect($data)->mapWithKeys(fn ($v, $k) => [$map[$k] => $v])->all());

        return response()->json(['user' => new UserResource($request->user())]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'current_password' => [$user->password ? 'required' : 'nullable', 'string'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->mixedCase()->numbers()],
        ]);

        if ($user->password && ! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages(['current_password' => 'Your current password is incorrect.']);
        }

        $user->update(['password' => $data['password']]);
        $user->tokens()->where('id', '!=', $user->currentAccessToken()?->id)->delete();

        return response()->json(['message' => 'Password updated.']);
    }

    /** "Delete account": blocked while rentals are in progress; otherwise anonymise + soft delete. */
    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();
        $request->validate(['confirm' => ['required', 'in:DELETE']]);

        $inProgress = Booking::forUser($user)->whereIn('status', [Booking::ACCEPTED, Booking::CONFIRMED, Booking::ACTIVE, Booking::RETURNED])->exists();
        if ($inProgress) {
            throw ValidationException::withMessages(['account' => 'Finish or cancel your current rentals before deleting your account.']);
        }

        Booking::forUser($user)->where('status', Booking::REQUESTED)->update(['status' => Booking::CANCELLED, 'cancelled_at' => now(), 'cancel_reason' => 'Account deleted']);
        $user->items()->update(['status' => Item::STATUS_PAUSED]);
        $user->items()->delete();
        $user->tokens()->delete();
        $user->paymentMethods()->delete();

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        $user->forceFill([
            'name' => 'Deleted user',
            'email' => 'deleted+'.$user->id.'@neighboursrental.invalid',
            'phone' => null, 'bio' => null, 'avatar_path' => null, 'google_id' => null, 'password' => null,
            'latitude' => null, 'longitude' => null, 'postal_code' => null,
        ])->save();
        $user->delete();

        return response()->json(['message' => 'Your account has been deleted.']);
    }
}
