<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankAccount extends Model {
    protected $fillable = ['name', 'bank_name', 'account_number', 'account_type', 'currency', 'opening_balance', 'is_active', 'notes'];
    protected $casts = ['opening_balance' => 'decimal:2', 'is_active' => 'boolean'];

    public function bankTransactions() {
        return $this->hasMany(BankTransaction::class);
    }

    public function billingPayments() {
        return $this->hasMany(BillingInvoicePayment::class);
    }

    public function reconciliations() {
        return $this->hasMany(BankReconciliation::class);
    }
}
