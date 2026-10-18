<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'status' => $this->status,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'description' => $this->description,
            'failure_reason' => $this->failure_reason,
            'payment_method' => $this->whenLoaded('paymentMethod', fn () => $this->paymentMethod ? new PaymentMethodResource($this->paymentMethod) : null),
            'booking' => $this->whenLoaded('booking', fn () => $this->booking ? [
                'id' => $this->booking->id,
                'code' => $this->booking->code,
                'status' => $this->booking->status,
                'item_title' => $this->booking->item?->title,
                'owner_name' => $this->booking->owner?->name,
                'start_date' => $this->booking->start_date->toDateString(),
                'end_date' => $this->booking->end_date->toDateString(),
            ] : null),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
