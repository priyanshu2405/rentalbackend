<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayoutResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'amount' => $this->amount,
            'status' => $this->status,
            'available_at' => $this->available_at?->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'booking' => $this->whenLoaded('booking', fn () => [
                'id' => $this->booking->id,
                'code' => $this->booking->code,
                'item_title' => $this->booking->item?->title,
                'start_date' => $this->booking->start_date->toDateString(),
                'end_date' => $this->booking->end_date->toDateString(),
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
