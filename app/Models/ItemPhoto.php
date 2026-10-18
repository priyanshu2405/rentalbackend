<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ItemPhoto extends Model
{
    protected function casts(): array
    {
        return ['item_id' => 'integer'];
    }

    protected $fillable = ['item_id', 'path', 'sort_order'];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function getUrlAttribute(): string
    {
        return str_starts_with($this->path, 'http') ? $this->path : Storage::disk('public')->url($this->path);
    }
}
