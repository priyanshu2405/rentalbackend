<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, SoftDeletes;

    protected $fillable = [
        'name', 'email', 'phone', 'password', 'google_id', 'avatar_path', 'bio',
        'city', 'province', 'postal_code', 'location_label', 'latitude', 'longitude', 'location_permission',
        'notify_new_items', 'notify_messages', 'notify_saved_items', 'notify_rental_updates',
        'email_verified_at',
    ];

    protected $hidden = ['password', 'remember_token', 'google_id'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'latitude' => 'float',
            'longitude' => 'float',
            'rating_avg' => 'float',
            'is_admin' => 'boolean',
            'notify_new_items' => 'boolean',
            'notify_messages' => 'boolean',
            'notify_saved_items' => 'boolean',
            'notify_rental_updates' => 'boolean',
        ];
    }

    public function getAvatarUrlAttribute(): ?string
    {
        return $this->avatar_path ? Storage::disk('public')->url($this->avatar_path) : null;
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    public function rentals(): HasMany
    {
        return $this->hasMany(Booking::class, 'renter_id');
    }

    public function ownerBookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'owner_id');
    }

    public function favoriteItems(): BelongsToMany
    {
        return $this->belongsToMany(Item::class, 'favorites')->withTimestamps();
    }

    public function paymentMethods(): HasMany
    {
        return $this->hasMany(PaymentMethod::class);
    }

    public function payoutAccount(): HasOne
    {
        return $this->hasOne(PayoutAccount::class);
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(Payout::class, 'owner_id');
    }

    public function reviewsReceived(): HasMany
    {
        return $this->hasMany(Review::class, 'reviewee_id');
    }

    public function appNotifications(): HasMany
    {
        return $this->hasMany(AppNotification::class);
    }

    public function defaultPaymentMethod(): ?PaymentMethod
    {
        return $this->paymentMethods()->orderByDesc('is_default')->latest()->first();
    }

    public function recalculateRating(): void
    {
        $agg = $this->reviewsReceived()->selectRaw('COUNT(*) c, AVG(rating) a')->first();
        $this->forceFill(['reviews_count' => (int) $agg->c, 'rating_avg' => round((float) $agg->a, 2)])->save();
    }
}
