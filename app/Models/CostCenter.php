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
        'customer_id', 'company_id', 'parent_id', 'level',
        'code', 'name', 'description', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'level'     => 'integer',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('code');
    }

    public function accountingPackages(): HasMany
    {
        return $this->hasMany(AccountingPackage::class);
    }

    public function journalEntryLines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class);
    }

    public function isLeaf(): bool
    {
        return ! $this->children()->exists();
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

    public function scopeLeafCenters(Builder $query): Builder
    {
        return $query->whereNotExists(fn ($q) => $q->select(DB::raw(1))
            ->from('cost_centers as ch')
            ->whereColumn('ch.parent_id', 'cost_centers.id'));
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
        $templates = static::whereNull('company_id')->whereNull('customer_id')
            ->orderBy('level')->orderBy('code')->get();
        if ($templates->isEmpty()) return;

        $idMap = [];
        DB::transaction(function () use ($templates, $companyId, &$idMap) {
            foreach ($templates as $tpl) {
                $new = static::create([
                    'company_id'  => $companyId,
                    'parent_id'   => $tpl->parent_id ? ($idMap[$tpl->parent_id] ?? null) : null,
                    'level'       => $tpl->level,
                    'code'        => $tpl->code,
                    'name'        => $tpl->name,
                    'description' => $tpl->description,
                    'is_active'   => $tpl->is_active,
                ]);
                $idMap[$tpl->id] = $new->id;
            }
        });
    }
}
