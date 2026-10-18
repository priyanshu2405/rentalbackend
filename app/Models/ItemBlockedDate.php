<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItemBlockedDate extends Model
{
    public $timestamps = false;

    protected $fillable = ['item_id', 'date'];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }
}
