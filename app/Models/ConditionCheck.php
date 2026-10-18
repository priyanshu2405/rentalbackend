<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ConditionCheck extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'works' => 'boolean',
            'accessories_included' => 'boolean',
            'scratches_noted' => 'boolean',
            'accessories_checklist' => 'array',
            'photos' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function photoUrls(): array
    {
        return array_map(fn ($p) => Storage::disk('public')->url($p), $this->photos ?? []);
    }
}
