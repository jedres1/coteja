<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillingSetting extends Model
{
    protected $fillable = ['key', 'customer_id', 'company_id', 'value'];

    public static function get(string $key, mixed $default = null, ?int $companyId = null): mixed
    {
        $record = static::query()
            ->where('key', $key)
            ->when($companyId !== null, fn ($q) => $q->where('company_id', $companyId))
            ->when($companyId === null, fn ($q) => $q->whereNull('company_id'))
            ->first();

        // Fallback a settings globales si la empresa no tiene configuración propia
        if (! $record && $companyId !== null) {
            $record = static::query()->where('key', $key)->whereNull('company_id')->first();
        }

        if (! $record) {
            return $default;
        }

        $decoded = json_decode((string) $record->value, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : $record->value;
    }

    public static function put(string $key, mixed $value, ?int $companyId = null): void
    {
        static::updateOrCreate(
            ['key' => $key, 'company_id' => $companyId],
            ['value' => is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE)]
        );
    }

    public static function allAsArray(?int $companyId = null): array
    {
        // Settings globales (company_id = null) como base
        $global = static::query()->whereNull('company_id')->pluck('value', 'key');

        // Settings de la empresa sobreescriben los globales
        if ($companyId !== null) {
            $specific = static::query()->where('company_id', $companyId)->pluck('value', 'key');
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
