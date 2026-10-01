<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankTransaction extends Model {
    protected $fillable = ['bank_account_id', 'type', 'transaction_date', 'bank_account', 'amount', 'reference', 'notes', 'reconciliation_id', 'is_reconciled'];
    protected $casts = ['transaction_date' => 'date', 'amount' => 'decimal:2', 'is_reconciled' => 'boolean'];

    public function purchaseInvoices() {
        return $this->belongsToMany(PurchaseInvoice::class, 'bank_transaction_purchase_invoice')
            ->withPivot('amount_applied')
            ->withTimestamps();
    }

    public function bankAccount() {
        return $this->belongsTo(BankAccount::class);
    }

    public function reconciliation() {
        return $this->belongsTo(BankReconciliation::class, 'reconciliation_id');
    }
}
