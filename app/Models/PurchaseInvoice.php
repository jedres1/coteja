<?php

namespace App\Models;

use Database\Factories\PurchaseInvoiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseInvoice extends Model
{
    /** @use HasFactory<PurchaseInvoiceFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'created_by',
        'updated_by',
        'supplier_id',
        'customer_id',
        'document_type',
        'invoice_number',
        'purchase_date',
        'due_date',
        'subtotal',
        'iva',
        'total',
        'payment_method',
        'payment_status',
        'status',
        'notes',
        'extracted_document_body',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'due_date' => 'date',
        'subtotal' => 'decimal:2',
        'iva' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function bankTransactions()
    {
        return $this->belongsToMany(BankTransaction::class, 'bank_transaction_purchase_invoice')
            ->withPivot('amount_applied')
            ->withTimestamps();
    }

    public function journalEntry()
    {
        return $this->hasOne(JournalEntry::class, 'source_id')
            ->where('source_type', 'purchase');
    }
}
