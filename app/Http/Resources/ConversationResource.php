<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $me = $request->user();
        $other = $this->participants->firstWhere('id', '!=', $me->id);

        // Title / subtitle shaped like the Messages list: "Maya" / "Portable Dryer • NR1042"
        [$title, $subtitle] = match ($this->type) {
            'support' => ['Neighbours Rental Support', 'Support Case #'.($this->supportCase?->code ?? $this->support_case_id)],
            'booking' => [$other?->name ?? 'Neighbour', trim(($this->item?->title ?? '').' • '.($this->booking?->code ?? ''), ' •')],
            default => [$other?->name ?? 'Neighbour', $this->item?->title],
        };

        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $title,
            'subtitle' => $subtitle,
            'other_user' => $other ? new UserSummaryResource($other) : null,
            'item_id' => $this->item_id,
            'item_photo_url' => $this->item?->photos?->first()?->url,
            'booking_id' => $this->booking_id,
            'support_case_id' => $this->support_case_id,
            'last_message' => $this->latestMessage ? new MessageResource($this->latestMessage) : null,
            'unread_count' => $this->unreadCountFor($me),
            'last_message_at' => $this->last_message_at?->toIso8601String(),
        ];
    }
}
