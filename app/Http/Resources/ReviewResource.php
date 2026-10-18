<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'booking_id' => $this->booking_id,
            'item_id' => $this->item_id,
            'role' => $this->role,
            'rating' => $this->rating,
            'comment' => $this->comment,
            'reviewer' => new UserSummaryResource($this->whenLoaded('reviewer')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
