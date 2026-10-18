<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingExtension extends Model
{
    public const PENDING = 'pending';
    public const APPROVED = 'approved';
    public const DECLINED = 'declined';
    public const PAYMENT_FAILED = 'payment_failed';
    public const CANCELLED = 'cancelled';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'booking_id' => 'integer',
            'previous_end_date' => 'date',
            'new_end_date' => 'date',
            'rental_amount' => 'float',
            'service_fee' => 'float',
            'taxes' => 'float',
            'total' => 'float',
            'responded_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
