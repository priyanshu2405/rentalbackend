<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Conversation extends Model
{
    public const TYPE_DIRECT = 'direct';
    public const TYPE_BOOKING = 'booking';
    public const TYPE_SUPPORT = 'support';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['last_message_at' => 'datetime'];
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_participants')
            ->withPivot('last_read_at')->withTimestamps()->withTrashed();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class)->withTrashed();
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function supportCase(): BelongsTo
    {
        return $this->belongsTo(SupportCase::class);
    }

    public function canBeAccessedBy(User $user): bool
    {
        if ($this->type === self::TYPE_SUPPORT && $user->is_admin) {
            return true;
        }

        return $this->participants()->where('users.id', $user->id)->exists();
    }

    public function unreadCountFor(User $user): int
    {
        $lastRead = $this->participants->firstWhere('id', $user->id)?->pivot?->last_read_at;

        return $this->messages()
            ->where(fn ($q) => $q->whereNull('sender_id')->orWhere('sender_id', '!=', $user->id))
            ->when($lastRead, fn ($q) => $q->where('created_at', '>', $lastRead))
            ->count();
    }
}
