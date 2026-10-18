<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;

class CodeGenerator
{
    /** Generates unique human-friendly codes like NR1042 or SUP203. */
    public static function unique(string $modelClass, string $prefix, int $digits = 4): string
    {
        /** @var Model $modelClass */
        do {
            $code = $prefix.random_int(10 ** ($digits - 1), (10 ** $digits) - 1);
        } while ($modelClass::where('code', $code)->exists());

        return $code;
    }
}
