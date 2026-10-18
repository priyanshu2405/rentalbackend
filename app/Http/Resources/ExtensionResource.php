<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExtensionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'booking_id' => $this->booking_id,
            'previous_end_date' => $this->previous_end_date->toDateString(),
            'new_end_date' => $this->new_end_date->toDateString(),
            'extra_days' => $this->extra_days,
            'rental_amount' => $this->rental_amount,
            'service_fee' => $this->service_fee,
            'taxes' => $this->taxes,
            'total' => $this->total,
            'message' => $this->message,
            'status' => $this->status,
            'responded_at' => $this->responded_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
