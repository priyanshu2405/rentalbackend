<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    public const CHARGE = 'charge';
    public const REFUND = 'refund';

    public const SUCCEEDED = 'succeeded';
    public const FAILED = 'failed';
    public const PENDING = 'pending';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['amount' => 'float'];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class)->withTrashed();
    }
}
