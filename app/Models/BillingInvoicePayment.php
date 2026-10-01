<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillingInvoicePayment extends Model {
    protected $fillable = [
        'billing_invoice_id', 'bank_account_id', 'amount', 'method',
        'reference', 'notes', 'registered_at', 'reconciliation_id', 'is_reconciled',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'registered_at' => 'datetime',
        'is_reconciled' => 'boolean',
    ];

    public function invoice() {
        return $this->belongsTo(BillingInvoice::class, 'billing_invoice_id');
    }

    public function bankAccount() {
        return $this->belongsTo(BankAccount::class);
    }

    public function reconciliation() {
        return $this->belongsTo(BankReconciliation::class, 'reconciliation_id');
    }
}
