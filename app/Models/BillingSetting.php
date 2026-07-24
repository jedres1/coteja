<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillingSetting extends Model
{
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    public static function put(string $key, mixed $value): void
    {
        static::updateOrCreate(
            ['key' => $key],
            ['value' => is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE)]
        );
    }

    public static function allAsArray(): array
    {
        return static::query()
            ->pluck('value', 'key')
            ->map(function (?string $value) {
                $decoded = json_decode((string) $value, true);
                return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
            })
            ->all();
    }
}
