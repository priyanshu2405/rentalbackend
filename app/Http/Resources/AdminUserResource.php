<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Full user record for the admin panel. */
class AdminUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'avatar_url' => $this->avatar_url,
            'location_label' => $this->location_label,
            'email_verified' => ! is_null($this->email_verified_at),
            'is_admin' => (bool) $this->is_admin,
            'suspended' => ! is_null($this->suspended_at),
            'suspended_at' => $this->suspended_at?->toIso8601String(),
            'suspension_reason' => $this->suspension_reason,
            'rating' => (float) $this->rating_avg,
            'reviews_count' => $this->reviews_count,
            'items_count' => $this->whenCounted('items'),
            'rentals_count' => $this->whenCounted('rentals'),
            'owner_bookings_count' => $this->whenCounted('ownerBookings'),
            'deleted' => $this->trashed(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
