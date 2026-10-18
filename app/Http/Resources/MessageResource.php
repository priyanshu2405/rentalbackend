<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'sender_id' => $this->sender_id,
            'sender' => $this->sender_id
                ? new UserSummaryResource($this->whenLoaded('sender'))
                : ['id' => null, 'name' => 'Neighbours Rental Support', 'avatar_url' => null],
            'is_mine' => $request->user()?->id === $this->sender_id,
            'body' => $this->body,
            'attachment_url' => $this->attachment_url,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
