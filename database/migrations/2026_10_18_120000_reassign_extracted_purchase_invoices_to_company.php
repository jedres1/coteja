<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // The mailbox importer previously stored the active company ID in customer_id
        // and left company_id null. The generated note identifies those imported rows.
        DB::table('purchase_invoices')
            ->join('companies', 'companies.id', '=', 'purchase_invoices.customer_id')
            ->whereNull('purchase_invoices.company_id')
            ->where('purchase_invoices.status', 'extracted')
            ->where('purchase_invoices.notes', 'like', 'Extraida de correo %')
            ->update(['purchase_invoices.company_id' => DB::raw('companies.id')]);
    }

    public function down(): void
    {
        // Keep the recovered company assignments if this migration is rolled back.
    }
};
