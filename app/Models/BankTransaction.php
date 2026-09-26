<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankTransaction extends Model {
    protected $fillable = ['transaction_date', 'bank_account', 'amount', 'reference', 'notes'];
    protected $casts = ['transaction_date' => 'date', 'amount' => 'decimal:2'];

    public function purchaseInvoices() {
        return $this->belongsToMany(PurchaseInvoice::class, 'bank_transaction_purchase_invoice')
            ->withPivot('amount_applied')
            ->withTimestamps();
    }
}
