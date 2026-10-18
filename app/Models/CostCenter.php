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
        'customer_id', 'company_id', 'code', 'name', 'description', 'is_active',
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
        return $query->where('company_id', $customerId);
    }

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    public static function companyHasCostCenters(int $companyId): bool
    {
        return static::where('company_id', $companyId)->exists();
    }

    /** @deprecated Use companyHasCostCenters */
    public static function customerHasCostCenters(int $customerId): bool
    {
        return static::companyHasCostCenters($customerId);
    }

    public static function copyDefaultsFor(int $companyId): void
    {
        $templates = static::whereNull('company_id')->whereNull('customer_id')->get();
        if ($templates->isEmpty()) return;

        DB::transaction(function () use ($templates, $companyId) {
            foreach ($templates as $tpl) {
                static::create([
                    'company_id'  => $companyId,
                    'code'        => $tpl->code,
                    'name'        => $tpl->name,
                    'description' => $tpl->description,
                    'is_active'   => $tpl->is_active,
                ]);
            }
        });
    }
}
