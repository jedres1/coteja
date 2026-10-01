<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankReconciliation extends Model {
    protected $fillable = ['bank_account_id', 'period_year', 'period_month', 'statement_file_path', 'statement_balance', 'status', 'completed_at', 'notes'];
    protected $casts = ['statement_balance' => 'decimal:2', 'completed_at' => 'datetime', 'period_year' => 'integer', 'period_month' => 'integer'];

    public function bankAccount() {
        return $this->belongsTo(BankAccount::class);
    }

    public function bankTransactions() {
        return $this->hasMany(BankTransaction::class, 'reconciliation_id');
    }

    public function billingPayments() {
        return $this->hasMany(BillingInvoicePayment::class, 'reconciliation_id');
    }

    public function periodLabel(): string {
        $months = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
        return ($months[$this->period_month - 1] ?? '') . ' ' . $this->period_year;
    }
}
