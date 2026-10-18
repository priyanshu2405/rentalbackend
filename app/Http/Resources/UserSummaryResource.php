<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Public view of a user (shown on cards, chats, public profile). */
class UserSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if ($this->trashed()) {
            return ['id' => $this->id, 'name' => 'Deleted user', 'avatar_url' => null, 'deleted' => true];
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'avatar_url' => $this->avatar_url,
            'location_label' => $this->location_label,
            'rating' => $this->rating_avg,
            'reviews_count' => $this->reviews_count,
            'email_verified' => ! is_null($this->email_verified_at),
            'member_since' => $this->created_at?->year,
        ];
    }
}
