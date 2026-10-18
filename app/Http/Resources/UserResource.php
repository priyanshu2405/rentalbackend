<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** The signed-in user's own profile. */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'email_verified' => ! is_null($this->email_verified_at),
            'avatar_url' => $this->avatar_url,
            'bio' => $this->bio,
            'location' => [
                'city' => $this->city,
                'province' => $this->province,
                'postal_code' => $this->postal_code,
                'label' => $this->location_label,
                'latitude' => $this->latitude,
                'longitude' => $this->longitude,
                'permission' => $this->location_permission,
            ],
            'notifications' => [
                'new_items' => $this->notify_new_items,
                'messages' => $this->notify_messages,
                'saved_items' => $this->notify_saved_items,
                'rental_updates' => $this->notify_rental_updates,
            ],
            'rating' => $this->rating_avg,
            'reviews_count' => $this->reviews_count,
            'has_password' => ! is_null($this->password),
            'google_linked' => ! is_null($this->google_id),
            'is_admin' => $this->is_admin,
            'member_since' => $this->created_at?->year,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
