<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class JournalEntry extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'customer_id', 'company_id', 'entry_number', 'entry_date', 'description', 'reference',
        'status', 'created_by', 'updated_by', 'approved_by', 'approved_at', 'notes',
        'accounting_package_id', 'source_type', 'source_id', 'source_document',
    ];

    protected $casts = [
        'entry_date'  => 'date',
        'approved_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class)->orderBy('sort_order')->orderBy('id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function accountingPackage(): BelongsTo
    {
        return $this->belongsTo(AccountingPackage::class);
    }

    public function getTotalDebitAttribute(): float
    {
        return (float) $this->lines->sum('debit');
    }

    public function getTotalCreditAttribute(): float
    {
        return (float) $this->lines->sum('credit');
    }

    public function isEditable(): bool
    {
        return $this->status === 'borrador';
    }

    public function scopeForCustomer(Builder $query, int $customerId): Builder
    {
        return $query->where('company_id', $customerId);
    }

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    // Debe llamarse dentro de un DB::transaction().
    public static function nextNumber(?AccountingPackage $package, ?int $companyId = null): string
    {
        if ($package) {
            return $package->reserveNextNumber();
        }

        $year  = now()->year;
        $query = static::where('entry_number', 'like', 'AS-%')->whereYear('created_at', $year);
        if ($companyId) {
            $query->where('company_id', $companyId);
        }
        $max = $query->lockForUpdate()->max('entry_number');
        $seq = $max ? ((int) substr($max, -5)) + 1 : 1;
        return 'AS-' . $year . '-' . str_pad($seq, 5, '0', STR_PAD_LEFT);
    }
}
