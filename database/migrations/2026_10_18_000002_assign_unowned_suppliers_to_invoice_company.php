<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $supplierCompanies = DB::table('purchase_invoices')
            ->select('supplier_id', DB::raw('MIN(company_id) as company_id'))
            ->whereNotNull('company_id')
            ->whereNotNull('supplier_id')
            ->groupBy('supplier_id')
            ->havingRaw('COUNT(DISTINCT company_id) = 1');

        DB::table('suppliers as s')
            ->joinSub($supplierCompanies, 'supplier_companies', function ($join) {
                $join->on('supplier_companies.supplier_id', '=', 's.id');
            })
            ->whereNull('s.company_id')
            ->update(['s.company_id' => DB::raw('supplier_companies.company_id')]);
    }

    public function down(): void
    {
        // No se revierte la asignación de datos para evitar devolver proveedores
        // correctamente asociados a una empresa al estado global sin empresa.
    }
};
