<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class AccountingPackage extends Model
{
    protected $fillable = [
        'customer_id', 'code', 'name', 'description', 'type',
        'debit_account_id', 'credit_account_id',
        'secondary_debit_account_id', 'secondary_credit_account_id',
        'cost_center_id',
        'last_correlative', 'is_active',
    ];

    protected $casts = [
        'is_active'        => 'boolean',
        'last_correlative' => 'integer',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function debitAccount(): BelongsTo
    {
        return $this->belongsTo(AccountingAccount::class, 'debit_account_id');
    }

    public function creditAccount(): BelongsTo
    {
        return $this->belongsTo(AccountingAccount::class, 'credit_account_id');
    }

    public function secondaryDebitAccount(): BelongsTo
    {
        return $this->belongsTo(AccountingAccount::class, 'secondary_debit_account_id');
    }

    public function secondaryCreditAccount(): BelongsTo
    {
        return $this->belongsTo(AccountingAccount::class, 'secondary_credit_account_id');
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class);
    }

    public function journalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class);
    }

    public function scopeForCustomer(Builder $query, int $customerId): Builder
    {
        return $query->where('customer_id', $customerId);
    }

    public static function customerHasPackages(int $customerId): bool
    {
        return static::where('customer_id', $customerId)->exists();
    }

    // Must be called inside a DB::transaction()
    public function reserveNextNumber(): string
    {
        $pkg = static::where('id', $this->id)->lockForUpdate()->first();
        $pkg->increment('last_correlative');
        $this->last_correlative = $pkg->last_correlative;
        return $this->code . str_pad($pkg->last_correlative, 9, '0', STR_PAD_LEFT);
    }

    /**
     * Copia los paquetes plantilla (customer_id=NULL) para un nuevo inquilino.
     * Mapea debit/credit account IDs usando el código de cuenta del cliente.
     * Debe llamarse después de CostCenter::copyDefaultsFor().
     */
    public static function copyDefaultsFor(int $customerId): void
    {
        $templates = static::whereNull('customer_id')->get();
        if ($templates->isEmpty()) return;

        // Mapa: código cuenta → id cuenta del cliente
        $customerAccounts = AccountingAccount::where('customer_id', $customerId)->pluck('id', 'code');
        // Mapa: id cuenta plantilla → código cuenta plantilla
        $templateAccountCodes = AccountingAccount::whereNull('customer_id')->pluck('code', 'id');

        // Mapa: código centro → id centro del cliente
        $customerCenters = CostCenter::where('customer_id', $customerId)->pluck('id', 'code');
        // Mapa: id centro plantilla → código centro plantilla
        $templateCenterCodes = CostCenter::whereNull('customer_id')->pluck('code', 'id');

        $resolveAccount = fn (?int $tplId) => $tplId
            ? ($customerAccounts[$templateAccountCodes[$tplId] ?? ''] ?? null)
            : null;

        $resolveCenter = fn (?int $tplId) => $tplId
            ? ($customerCenters[$templateCenterCodes[$tplId] ?? ''] ?? null)
            : null;

        DB::transaction(function () use ($templates, $customerId, $resolveAccount, $resolveCenter) {
            foreach ($templates as $tpl) {
                static::create([
                    'customer_id'                 => $customerId,
                    'code'                        => $tpl->code,
                    'name'                        => $tpl->name,
                    'description'                 => $tpl->description,
                    'type'                        => $tpl->type,
                    'debit_account_id'            => $resolveAccount($tpl->debit_account_id),
                    'credit_account_id'           => $resolveAccount($tpl->credit_account_id),
                    'secondary_debit_account_id'  => $resolveAccount($tpl->secondary_debit_account_id),
                    'secondary_credit_account_id' => $resolveAccount($tpl->secondary_credit_account_id),
                    'cost_center_id'              => $resolveCenter($tpl->cost_center_id),
                    'last_correlative'            => 0,
                    'is_active'                   => $tpl->is_active,
                ]);
            }
        });
    }
}
