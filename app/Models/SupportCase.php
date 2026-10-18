<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

class SupportCase extends Model
{
    public const TYPES = ['damage', 'not_working', 'late_return', 'refund_request', 'other'];

    public const SUBMITTED = 'submitted';
    public const REVIEWING = 'reviewing';
    public const RESOLVED = 'resolved';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'photos' => 'array',
            'refund_amount' => 'float',
            'reviewing_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function conversation(): HasOne
    {
        return $this->hasOne(Conversation::class);
    }

    public function photoUrls(): array
    {
        return array_map(fn ($p) => Storage::disk('public')->url($p), $this->photos ?? []);
    }
}
