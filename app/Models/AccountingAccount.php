<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountingAccount extends Model
{
    protected $fillable = [
        'code', 'name', 'type', 'nature', 'parent_id', 'level', 'is_active', 'notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'level' => 'integer',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('code');
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
        $first = $code[0] ?? '1';
        return match ($first) {
            '1' => 'activo',
            '2' => 'pasivo',
            '3' => 'patrimonio',
            '4' => 'gasto',
            '5' => 'ingreso',
            default => 'activo',
        };
    }

    public static function natureFromType(string $type): string
    {
        return in_array($type, ['activo', 'gasto']) ? 'deudora' : 'acreedora';
    }
}
