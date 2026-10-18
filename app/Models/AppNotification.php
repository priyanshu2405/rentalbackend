<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppNotification extends Model
{
    protected $fillable = ['user_id', 'type', 'category', 'title', 'body', 'data', 'read_at'];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'data' => 'array', 'read_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
