<?php

namespace App\Services\Accounting;

use App\Models\AccountingPackage;
use App\Models\BillingInvoice;
use App\Models\InventoryMovement;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\PurchaseInvoice;
use Illuminate\Support\Facades\DB;

class AccountingEntryService
{
    public function createFromBilling(BillingInvoice $invoice, ?int $userId = null): ?JournalEntry
    {
        $package = AccountingPackage::where('code', 'FA')->where('is_active', true)->first();

        if (! $package || ! $package->debit_account_id || ! $package->credit_account_id) {
            return null;
        }

        if (JournalEntry::where('source_type', 'billing')->where('source_id', $invoice->id)->exists()) {
            return null;
        }

        return DB::transaction(function () use ($invoice, $package, $userId) {
            $number = $package->reserveNextNumber();
            $total  = round((float) $invoice->total, 2);

            $entry = JournalEntry::create([
                'entry_number'          => $number,
                'entry_date'            => $invoice->issued_at ?? now()->toDateString(),
                'description'           => 'Venta: ' . ($invoice->customer_name ?? 'Cliente') . ' — ' . $invoice->number_control,
                'reference'             => $invoice->number_control,
                'status'                => 'aprobado',
                'created_by'            => $userId,
                'approved_by'           => $userId,
                'approved_at'           => now(),
                'accounting_package_id' => $package->id,
                'source_type'           => 'billing',
                'source_id'             => $invoice->id,
                'source_document'       => $invoice->number_control,
            ]);

            $sort  = 0;
            $lines = [
                [
                    'journal_entry_id' => $entry->id,
                    'account_id'       => $package->debit_account_id,
                    'description'      => 'Factura ' . $invoice->number_control . ' — ' . $invoice->customer_name,
                    'debit'            => $total,
                    'credit'           => 0,
                    'sort_order'       => $sort++,
                ],
                [
                    'journal_entry_id' => $entry->id,
                    'account_id'       => $package->credit_account_id,
                    'description'      => 'Ingresos venta: ' . $invoice->number_control,
                    'debit'            => 0,
                    'credit'           => $total,
                    'sort_order'       => $sort++,
                ],
            ];

            // COGS + Inventory lines si se configuraron cuentas secundarias
            if ($package->secondary_debit_account_id && $package->secondary_credit_account_id) {
                $costValue = $this->billingInventoryValue($invoice);
                if ($costValue > 0) {
                    $lines[] = [
                        'journal_entry_id' => $entry->id,
                        'account_id'       => $package->secondary_debit_account_id,
                        'description'      => 'Costo de ventas — ' . $invoice->number_control,
                        'debit'            => $costValue,
                        'credit'           => 0,
                        'sort_order'       => $sort++,
                    ];
                    $lines[] = [
                        'journal_entry_id' => $entry->id,
                        'account_id'       => $package->secondary_credit_account_id,
                        'description'      => 'Salida inventario — ' . $invoice->number_control,
                        'debit'            => 0,
                        'credit'           => $costValue,
                        'sort_order'       => $sort++,
                    ];
                }
            }

            JournalEntryLine::insert($lines);

            return $entry;
        });
    }

    public function createFromPurchase(PurchaseInvoice $invoice, ?int $userId = null): ?JournalEntry
    {
        $package = AccountingPackage::where('code', 'CP')->where('is_active', true)->first();

        if (! $package || ! $package->debit_account_id || ! $package->credit_account_id) {
            return null;
        }

        if (JournalEntry::where('source_type', 'purchase')->where('source_id', $invoice->id)->exists()) {
            return null;
        }

        return DB::transaction(function () use ($invoice, $package, $userId) {
            $number       = $package->reserveNextNumber();
            $total        = round((float) $invoice->total, 2);
            $supplierName = $invoice->supplier?->name ?? 'Proveedor';

            $entry = JournalEntry::create([
                'entry_number'          => $number,
                'entry_date'            => $invoice->purchase_date ?? now()->toDateString(),
                'description'           => 'Compra: ' . $supplierName . ' — ' . $invoice->invoice_number,
                'reference'             => $invoice->invoice_number,
                'status'                => 'aprobado',
                'created_by'            => $userId,
                'approved_by'           => $userId,
                'approved_at'           => now(),
                'accounting_package_id' => $package->id,
                'source_type'           => 'purchase',
                'source_id'             => $invoice->id,
                'source_document'       => $invoice->invoice_number,
            ]);

            $sort  = 0;
            $lines = [
                [
                    'journal_entry_id' => $entry->id,
                    'account_id'       => $package->debit_account_id,
                    'description'      => 'Gasto compra: ' . $invoice->invoice_number . ' — ' . $supplierName,
                    'debit'            => $total,
                    'credit'           => 0,
                    'sort_order'       => $sort++,
                ],
                [
                    'journal_entry_id' => $entry->id,
                    'account_id'       => $package->credit_account_id,
                    'description'      => 'CxP: ' . $supplierName . ' — ' . $invoice->invoice_number,
                    'debit'            => 0,
                    'credit'           => $total,
                    'sort_order'       => $sort++,
                ],
            ];

            // Líneas secundarias de inventario si están configuradas
            if ($package->secondary_debit_account_id && $package->secondary_credit_account_id) {
                $inventoryValue = $this->purchaseInventoryValue($invoice);
                if ($inventoryValue > 0) {
                    $lines[] = [
                        'journal_entry_id' => $entry->id,
                        'account_id'       => $package->secondary_debit_account_id,
                        'description'      => 'Ingreso inventario — ' . $invoice->invoice_number,
                        'debit'            => $inventoryValue,
                        'credit'           => 0,
                        'sort_order'       => $sort++,
                    ];
                    $lines[] = [
                        'journal_entry_id' => $entry->id,
                        'account_id'       => $package->secondary_credit_account_id,
                        'description'      => 'Ajuste compra inventario — ' . $invoice->invoice_number,
                        'debit'            => 0,
                        'credit'           => $inventoryValue,
                        'sort_order'       => $sort++,
                    ];
                }
            }

            JournalEntryLine::insert($lines);

            return $entry;
        });
    }

    public function createFromInventory(InventoryMovement $movement, ?int $userId = null): ?JournalEntry
    {
        // Movimientos originados desde facturación o compras ya tienen su partida en FA/CP
        if ($movement->billing_invoice_id || $movement->purchase_invoice_id) {
            return null;
        }

        $package = AccountingPackage::where('code', 'IN')->where('is_active', true)->first();

        if (! $package || ! $package->debit_account_id || ! $package->credit_account_id) {
            return null;
        }

        if (JournalEntry::where('source_type', 'inventory')->where('source_id', $movement->id)->exists()) {
            return null;
        }

        $product = $movement->product;
        $amount  = round((float) ($product?->price ?? 0) * (float) $movement->quantity, 2);

        if ($amount <= 0) {
            return null;
        }

        return DB::transaction(function () use ($movement, $package, $userId, $amount, $product) {
            $number      = $package->reserveNextNumber();
            $productName = $product?->description ?? 'Producto';
            $typeLabel   = $movement->type === 'entry' ? 'Entrada' : 'Salida';
            $docRef      = $movement->document_number ?? ('MOV-' . $movement->id);

            $entry = JournalEntry::create([
                'entry_number'          => $number,
                'entry_date'            => $movement->created_at->toDateString(),
                'description'           => $typeLabel . ' inventario: ' . $productName . ($movement->document_number ? ' — ' . $movement->document_number : ''),
                'reference'             => $docRef,
                'status'                => 'aprobado',
                'created_by'            => $userId,
                'approved_by'           => $userId,
                'approved_at'           => now(),
                'notes'                 => $movement->notes,
                'accounting_package_id' => $package->id,
                'source_type'           => 'inventory',
                'source_id'             => $movement->id,
                'source_document'       => $docRef,
            ]);

            // Entrada: Debe=Inventario  Haber=Costo/Proveedor
            // Salida:  Debe=Costo  Haber=Inventario
            if ($movement->type === 'entry') {
                $debitDesc  = 'Entrada inventario: ' . $productName;
                $creditDesc = 'Costo entrada: ' . $productName;
            } else {
                $debitDesc  = 'Costo salida: ' . $productName;
                $creditDesc = 'Salida inventario: ' . $productName;
            }

            JournalEntryLine::insert([
                ['journal_entry_id' => $entry->id, 'account_id' => $package->debit_account_id,  'description' => $debitDesc,  'debit' => $amount, 'credit' => 0,       'sort_order' => 0],
                ['journal_entry_id' => $entry->id, 'account_id' => $package->credit_account_id, 'description' => $creditDesc, 'debit' => 0,       'credit' => $amount, 'sort_order' => 1],
            ]);

            return $entry;
        });
    }

    private function billingInventoryValue(BillingInvoice $invoice): float
    {
        $movements = InventoryMovement::with('product')
            ->where('billing_invoice_id', $invoice->id)
            ->where('type', 'exit')
            ->get();

        return round($movements->sum(fn ($m) => (float) ($m->product?->price ?? 0) * (float) $m->quantity), 2);
    }

    private function purchaseInventoryValue(PurchaseInvoice $invoice): float
    {
        $movements = InventoryMovement::with('product')
            ->where('purchase_invoice_id', $invoice->id)
            ->where('type', 'entry')
            ->get();

        return round($movements->sum(fn ($m) => (float) ($m->product?->price ?? 0) * (float) $m->quantity), 2);
    }
}
