<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class CostCenter extends Model
{
    protected $fillable = [
        'customer_id', 'code', 'name', 'description', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function accountingPackages(): HasMany
    {
        return $this->hasMany(AccountingPackage::class);
    }

    public function journalEntryLines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class);
    }

    public function label(): string
    {
        return $this->code . ' - ' . $this->name;
    }

    public function scopeForCustomer(Builder $query, int $customerId): Builder
    {
        return $query->where('customer_id', $customerId);
    }

    public static function customerHasCostCenters(int $customerId): bool
    {
        return static::where('customer_id', $customerId)->exists();
    }

    public static function copyDefaultsFor(int $customerId): void
    {
        $templates = static::whereNull('customer_id')->get();
        if ($templates->isEmpty()) return;

        DB::transaction(function () use ($templates, $customerId) {
            foreach ($templates as $tpl) {
                static::create([
                    'customer_id' => $customerId,
                    'code'        => $tpl->code,
                    'name'        => $tpl->name,
                    'description' => $tpl->description,
                    'is_active'   => $tpl->is_active,
                ]);
            }
        });
    }
}
