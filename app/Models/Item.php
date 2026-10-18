<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Item extends Model
{
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'category_id', 'title', 'brand', 'model', 'condition', 'description', 'accessories', 'tags', 'rental_rules',
        'daily_rate', 'min_days', 'max_days', 'security_deposit', 'cancellation_policy',
        'pickup_window_start', 'pickup_window_end', 'advance_notice_hours',
        'pickup_address', 'area', 'city', 'province', 'postal_code', 'latitude', 'longitude',
        'offers_delivery', 'delivery_radius_km', 'delivery_fee',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'category_id' => 'integer',
            'accessories' => 'array',
            'tags' => 'array',
            'daily_rate' => 'float',
            'security_deposit' => 'float',
            'delivery_fee' => 'float',
            'latitude' => 'float',
            'longitude' => 'float',
            'rating_avg' => 'float',
            'offers_delivery' => 'boolean',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(ItemPhoto::class)->orderBy('sort_order');
    }

    public function blockedDates(): HasMany
    {
        return $this->hasMany(ItemBlockedDate::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class)->where('role', Review::ROLE_RENTER);
    }

    public function favoritedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'favorites')->withTimestamps();
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_ACTIVE);
    }

    /** Adds a `distance_km` column (Haversine) and optionally limits to a radius. */
    public function scopeWithDistance(Builder $q, float $lat, float $lng, ?float $radiusKm = null): Builder
    {
        $formula = '(6371 * acos(least(1, cos(radians(?)) * cos(radians(items.latitude)) * cos(radians(items.longitude) - radians(?)) + sin(radians(?)) * sin(radians(items.latitude)))))';

        if (is_null($q->getQuery()->columns)) {
            $q->select('items.*');
        }
        $q->selectRaw("$formula as distance_km", [$lat, $lng, $lat]);

        if ($radiusKm) {
            $q->whereNotNull('items.latitude')->whereRaw("$formula <= ?", [$lat, $lng, $lat, $radiusKm]);
        }

        return $q;
    }

    /** Items free for every day in [start, end]: no blocked dates and no overlapping live booking. */
    public function scopeAvailableBetween(Builder $q, CarbonInterface $start, CarbonInterface $end): Builder
    {
        return $q
            ->whereDoesntHave('blockedDates', fn ($b) => $b->whereBetween('date', [$start->toDateString(), $end->toDateString()]))
            ->whereDoesntHave('bookings', fn ($b) => $b->overlapping($start, $end));
    }

    public function isAvailable(CarbonInterface $start, CarbonInterface $end, ?int $ignoreBookingId = null): bool
    {
        $blocked = $this->blockedDates()->whereBetween('date', [$start->toDateString(), $end->toDateString()])->exists();
        if ($blocked) {
            return false;
        }

        return ! $this->bookings()
            ->overlapping($start, $end)
            ->when($ignoreBookingId, fn ($q) => $q->where('id', '!=', $ignoreBookingId))
            ->exists();
    }

    /** Fields required before a listing can be submitted for review. */
    public function missingFieldsForSubmission(): array
    {
        $required = ['title', 'category_id', 'condition', 'description', 'daily_rate', 'latitude', 'longitude', 'area', 'city'];
        $missing = array_values(array_filter($required, fn ($f) => blank($this->{$f})));
        if ($this->photos()->count() === 0) {
            $missing[] = 'photos';
        }

        return $missing;
    }

    public function recalculateRating(): void
    {
        $agg = $this->reviews()->selectRaw('COUNT(*) c, AVG(rating) a')->first();
        $this->forceFill(['reviews_count' => (int) $agg->c, 'rating_avg' => round((float) $agg->a, 2)])->save();
    }
}
