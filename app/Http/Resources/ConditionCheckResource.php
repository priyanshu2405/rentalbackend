<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConditionCheckResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'submitted_by' => $this->user_id,
            'works' => $this->works,
            'accessories_included' => $this->accessories_included,
            'scratches_noted' => $this->scratches_noted,
            'accessories_checklist' => $this->accessories_checklist ?? [],
            'notes' => $this->notes,
            'photos' => $this->photoUrls(),
            'occurred_at' => $this->occurred_at?->toIso8601String(),
        ];
    }
}
