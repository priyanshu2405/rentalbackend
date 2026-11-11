<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Key-value store for admin-configurable platform settings and legal copy. */
class SiteSetting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    public const KEY_TERMS = 'terms_of_service';

    public const KEY_PRIVACY = 'privacy_policy';

    public static function setValue(string $key, ?string $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }

    /**
     * Returns active Terms of Service and Privacy Policy texts,
     * falling back to config defaults when not yet customized in the database.
     *
     * @return array{terms_of_service: string, privacy_policy: string}
     */
    public static function legalDocuments(): array
    {
        $rows = static::query()
            ->whereIn('key', [self::KEY_TERMS, self::KEY_PRIVACY])
            ->pluck('value', 'key');

        $pick = function (string $key) use ($rows): string {
            $val = $rows->get($key);
            if (is_string($val) && trim($val) !== '') {
                return $val;
            }

            return (string) config("neighbours.{$key}", '');
        };

        return [
            self::KEY_TERMS => $pick(self::KEY_TERMS),
            self::KEY_PRIVACY => $pick(self::KEY_PRIVACY),
        ];
    }
}

