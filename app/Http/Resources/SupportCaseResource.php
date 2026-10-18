<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupportCaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'type' => $this->type,
            'subject' => $this->subject,
            'description' => $this->description,
            'photos' => $this->photoUrls(),
            'status' => $this->status,
            'resolution' => $this->resolution,
            'refund_amount' => $this->refund_amount,
            'refund_status' => $this->refund_status,
            'booking' => $this->whenLoaded('booking', fn () => $this->booking ? [
                'id' => $this->booking->id,
                'code' => $this->booking->code,
                'item_title' => $this->booking->item?->title,
                'start_date' => $this->booking->start_date->toDateString(),
                'end_date' => $this->booking->end_date->toDateString(),
                'renter_name' => $this->booking->renter?->name,
            ] : null),
            'user' => new UserSummaryResource($this->whenLoaded('user')),
            'conversation_id' => $this->whenLoaded('conversation', fn () => $this->conversation?->id),
            'timeline' => array_values(array_filter([
                ['step' => 'submitted', 'at' => $this->created_at?->toIso8601String()],
                $this->reviewing_at ? ['step' => 'reviewing', 'at' => $this->reviewing_at->toIso8601String()] : null,
                $this->resolved_at ? ['step' => 'resolved', 'at' => $this->resolved_at->toIso8601String()] : null,
            ])),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
