<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Booking extends Model
{
    public const REQUESTED = 'requested';
    public const ACCEPTED = 'accepted';   // awaiting payment
    public const CONFIRMED = 'confirmed'; // paid, waiting for pickup
    public const ACTIVE = 'active';       // item picked up
    public const RETURNED = 'returned';   // renter says returned, owner to confirm
    public const COMPLETED = 'completed';
    public const DECLINED = 'declined';
    public const CANCELLED = 'cancelled';
    public const EXPIRED = 'expired';

    /** Statuses that hold the item's dates. */
    public const BLOCKING = [self::ACCEPTED, self::CONFIRMED, self::ACTIVE, self::RETURNED];

    /** Statuses after which the exact pickup address is revealed to the renter. */
    public const PAID = [self::CONFIRMED, self::ACTIVE, self::RETURNED, self::COMPLETED];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'item_id' => 'integer',
            'renter_id' => 'integer',
            'owner_id' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
            'delivery' => 'boolean',
            'daily_rate' => 'float',
            'rental_subtotal' => 'float',
            'service_fee' => 'float',
            'delivery_fee' => 'float',
            'taxes' => 'float',
            'security_deposit' => 'float',
            'total' => 'float',
            'refund_amount' => 'float',
            'payment_due_at' => 'datetime',
            'accepted_at' => 'datetime',
            'declined_at' => 'datetime',
            'paid_at' => 'datetime',
            'picked_up_at' => 'datetime',
            'returned_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class)->withTrashed();
    }

    public function renter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'renter_id')->withTrashed();
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id')->withTrashed();
    }

    public function extensions(): HasMany
    {
        return $this->hasMany(BookingExtension::class)->latest();
    }

    public function conditionChecks(): HasMany
    {
        return $this->hasMany(ConditionCheck::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function payout(): HasOne
    {
        return $this->hasOne(Payout::class);
    }

    public function conversation(): HasOne
    {
        return $this->hasOne(Conversation::class)->where('type', Conversation::TYPE_BOOKING);
    }

    public function scopeOverlapping(Builder $q, CarbonInterface $start, CarbonInterface $end): Builder
    {
        return $q->whereIn('status', self::BLOCKING)
            ->whereDate('start_date', '<=', $end->toDateString())
            ->whereDate('end_date', '>=', $start->toDateString());
    }

    public function scopeForUser(Builder $q, User $user): Builder
    {
        return $q->where(fn ($w) => $w->where('renter_id', $user->id)->orWhere('owner_id', $user->id));
    }

    public function isParticipant(User $user): bool
    {
        return $user->id === $this->renter_id || $user->id === $this->owner_id;
    }

    public function roleOf(User $user): ?string
    {
        return match ($user->id) {
            $this->renter_id => 'renter',
            $this->owner_id => 'owner',
            default => null,
        };
    }

    /**
     * Owner earnings = rental subtotal + delivery fee (service fee and taxes stay with the platform).
     * Approved extensions are rolled into rental_subtotal, so they are included automatically.
     */
    public function ownerEarnings(): float
    {
        return round($this->rental_subtotal + $this->delivery_fee, 2);
    }

    public function startsAt(): CarbonInterface
    {
        return $this->start_date->copy()->setTimeFromTimeString($this->pickup_time ?? '00:00:00');
    }

    public function returnDueAt(): CarbonInterface
    {
        return $this->end_date->copy()->setTimeFromTimeString($this->return_time ?? '23:59:00');
    }
}
