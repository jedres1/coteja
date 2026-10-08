<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillingSetting extends Model
{
    protected $fillable = ['key', 'customer_id', 'value'];

    public static function get(string $key, mixed $default = null, ?int $customerId = null): mixed
    {
        $record = static::query()
            ->where('key', $key)
            ->when($customerId !== null, fn ($q) => $q->where('customer_id', $customerId))
            ->when($customerId === null, fn ($q) => $q->whereNull('customer_id'))
            ->first();

        // Fallback a settings globales si la empresa no tiene configuración propia
        if (! $record && $customerId !== null) {
            $record = static::query()->where('key', $key)->whereNull('customer_id')->first();
        }

        if (! $record) {
            return $default;
        }

        $decoded = json_decode((string) $record->value, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : $record->value;
    }

    public static function put(string $key, mixed $value, ?int $customerId = null): void
    {
        static::updateOrCreate(
            ['key' => $key, 'customer_id' => $customerId],
            ['value' => is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE)]
        );
    }

    public static function allAsArray(?int $customerId = null): array
    {
        // Settings globales (customer_id = null) como base
        $global = static::query()->whereNull('customer_id')->pluck('value', 'key');

        // Settings de la empresa sobreescriben los globales
        if ($customerId !== null) {
            $specific = static::query()->where('customer_id', $customerId)->pluck('value', 'key');
            $merged = $global->merge($specific);
        } else {
            $merged = $global;
        }

        return $merged->map(function (?string $value) {
            $decoded = json_decode((string) $value, true);
            return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
        })->all();
    }
}
