<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class AccountingAccount extends Model
{
    protected $fillable = [
        'customer_id', 'code', 'name', 'type', 'nature',
        'parent_id', 'level', 'is_active', 'notes',
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

    public function scopeForCustomer(Builder $query, int $customerId): Builder
    {
        return $query->where('customer_id', $customerId);
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            'activo'     => 'Activo',
            'pasivo'     => 'Pasivo',
            'patrimonio' => 'Patrimonio',
            'gasto'      => 'Gasto',
            'ingreso'    => 'Ingreso',
            default      => $this->type,
        };
    }

    public static function typeFromCode(string $code): string
    {
        return match ($code[0] ?? '1') {
            '1'     => 'activo',
            '2'     => 'pasivo',
            '3'     => 'patrimonio',
            '4'     => 'gasto',
            '5'     => 'ingreso',
            default => 'activo',
        };
    }

    public static function natureFromType(string $type): string
    {
        return in_array($type, ['activo', 'gasto']) ? 'deudora' : 'acreedora';
    }

    /**
     * Copia el catálogo plantilla (customer_id=NULL) para un nuevo inquilino.
     * Preserva la jerarquía parent_id mapeando los IDs originales a los nuevos.
     */
    public static function copyDefaultsFor(int $customerId): void
    {
        $templates = static::whereNull('customer_id')
            ->orderBy('level')
            ->orderBy('code')
            ->get();

        if ($templates->isEmpty()) return;

        $idMap = [];

        DB::transaction(function () use ($templates, $customerId, &$idMap) {
            foreach ($templates as $tpl) {
                $new = static::create([
                    'customer_id' => $customerId,
                    'code'        => $tpl->code,
                    'name'        => $tpl->name,
                    'type'        => $tpl->type,
                    'nature'      => $tpl->nature,
                    'parent_id'   => $tpl->parent_id ? ($idMap[$tpl->parent_id] ?? null) : null,
                    'level'       => $tpl->level,
                    'is_active'   => $tpl->is_active,
                    'notes'       => $tpl->notes,
                ]);
                $idMap[$tpl->id] = $new->id;
            }
        });
    }

    /** True si el cliente ya tiene su propio catálogo. */
    public static function customerHasCatalog(int $customerId): bool
    {
        return static::where('customer_id', $customerId)->exists();
    }
}
