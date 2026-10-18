<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Add company_id column + index if not already present (idempotent). */
    private function addCompanyIdIfMissing(string $table): void
    {
        if (! Schema::hasColumn($table, 'company_id')) {
            Schema::table($table, function (Blueprint $t) {
                $t->unsignedBigInteger('company_id')->nullable()->after('customer_id');
                $t->index('company_id');
            });
        }
    }

    public function up(): void
    {
        // ── 1. billing_products ─────────────────────────────────────────────
        $this->addCompanyIdIfMissing('billing_products');

        DB::statement("UPDATE billing_products SET company_id = (SELECT id FROM companies WHERE companies.customer_id = billing_products.customer_id LIMIT 1) WHERE company_id IS NULL");

        // Drop old unique, add new unique with company_id
        try { Schema::table('billing_products', fn (Blueprint $t) => $t->dropUnique('billing_products_customer_id_code_unique')); } catch (\Throwable) {}
        try { Schema::table('billing_products', fn (Blueprint $t) => $t->unique(['company_id', 'code'])); } catch (\Throwable) {}

        // ── 2. billing_settings ─────────────────────────────────────────────
        $this->addCompanyIdIfMissing('billing_settings');

        DB::statement("UPDATE billing_settings SET company_id = (SELECT id FROM companies WHERE companies.customer_id = billing_settings.customer_id LIMIT 1) WHERE company_id IS NULL AND customer_id IS NOT NULL");

        try { Schema::table('billing_settings', fn (Blueprint $t) => $t->dropUnique('billing_settings_key_customer_id_unique')); } catch (\Throwable) {}
        try { Schema::table('billing_settings', fn (Blueprint $t) => $t->unique(['key', 'company_id'])); } catch (\Throwable) {}

        // ── 3. billing_dte_correlatives ─────────────────────────────────────
        $this->addCompanyIdIfMissing('billing_dte_correlatives');

        DB::statement("UPDATE billing_dte_correlatives SET company_id = (SELECT id FROM companies WHERE companies.customer_id = billing_dte_correlatives.customer_id LIMIT 1) WHERE company_id IS NULL AND customer_id IS NOT NULL");

        // Drop old unique (any name variant)
        try { Schema::table('billing_dte_correlatives', fn (Blueprint $t) => $t->dropUnique('billing_dte_correlatives_document_type_year_establishment_point_of_sale_customer_id_unique')); } catch (\Throwable) {}
        try { Schema::table('billing_dte_correlatives', fn (Blueprint $t) => $t->dropUnique('billing_dte_correlatives_unique')); } catch (\Throwable) {}
        // Add new unique with explicit short name (MySQL max identifier length = 64)
        try {
            DB::statement("ALTER TABLE billing_dte_correlatives ADD UNIQUE KEY bdc_dt_yr_est_pos_co_unique (document_type, year, establishment, point_of_sale, company_id)");
        } catch (\Throwable) {}

        // ── 4. bank_accounts ────────────────────────────────────────────────
        $this->addCompanyIdIfMissing('bank_accounts');

        DB::statement("UPDATE bank_accounts SET company_id = (SELECT id FROM companies WHERE companies.customer_id = bank_accounts.customer_id LIMIT 1) WHERE company_id IS NULL");

        // ── 5. accounting_accounts ──────────────────────────────────────────
        $this->addCompanyIdIfMissing('accounting_accounts');

        DB::statement("UPDATE accounting_accounts SET company_id = (SELECT id FROM companies WHERE companies.customer_id = accounting_accounts.customer_id LIMIT 1) WHERE company_id IS NULL AND customer_id IS NOT NULL");

        try { Schema::table('accounting_accounts', fn (Blueprint $t) => $t->dropUnique('accounting_accounts_customer_id_code_unique')); } catch (\Throwable) {}
        try { Schema::table('accounting_accounts', fn (Blueprint $t) => $t->unique(['company_id', 'code'])); } catch (\Throwable) {}

        // ── 6. journal_entries ──────────────────────────────────────────────
        $this->addCompanyIdIfMissing('journal_entries');

        DB::statement("UPDATE journal_entries SET company_id = (SELECT id FROM companies WHERE companies.customer_id = journal_entries.customer_id LIMIT 1) WHERE company_id IS NULL AND customer_id IS NOT NULL");

        try { Schema::table('journal_entries', fn (Blueprint $t) => $t->dropUnique('journal_entries_customer_id_entry_number_unique')); } catch (\Throwable) {}
        try { Schema::table('journal_entries', fn (Blueprint $t) => $t->unique(['company_id', 'entry_number'])); } catch (\Throwable) {}

        // ── 7. accounting_packages ──────────────────────────────────────────
        $this->addCompanyIdIfMissing('accounting_packages');

        DB::statement("UPDATE accounting_packages SET company_id = (SELECT id FROM companies WHERE companies.customer_id = accounting_packages.customer_id LIMIT 1) WHERE company_id IS NULL AND customer_id IS NOT NULL");

        try { Schema::table('accounting_packages', fn (Blueprint $t) => $t->dropUnique('accounting_packages_customer_id_code_unique')); } catch (\Throwable) {}
        try { Schema::table('accounting_packages', fn (Blueprint $t) => $t->unique(['company_id', 'code'])); } catch (\Throwable) {}

        // ── 8. cost_centers ─────────────────────────────────────────────────
        $this->addCompanyIdIfMissing('cost_centers');

        DB::statement("UPDATE cost_centers SET company_id = (SELECT id FROM companies WHERE companies.customer_id = cost_centers.customer_id LIMIT 1) WHERE company_id IS NULL AND customer_id IS NOT NULL");

        try { Schema::table('cost_centers', fn (Blueprint $t) => $t->dropUnique('cost_centers_customer_id_code_unique')); } catch (\Throwable) {}
        try { Schema::table('cost_centers', fn (Blueprint $t) => $t->unique(['company_id', 'code'])); } catch (\Throwable) {}

        // ── 9. suppliers ────────────────────────────────────────────────────
        $this->addCompanyIdIfMissing('suppliers');

        DB::statement("UPDATE suppliers SET company_id = (SELECT id FROM companies WHERE companies.customer_id = suppliers.customer_id LIMIT 1) WHERE company_id IS NULL");

        // ── 10. warehouses ──────────────────────────────────────────────────
        $this->addCompanyIdIfMissing('warehouses');

        DB::statement("UPDATE warehouses SET company_id = (SELECT id FROM companies WHERE companies.customer_id = warehouses.customer_id LIMIT 1) WHERE company_id IS NULL");

        // ── 11. product_types ───────────────────────────────────────────────
        $this->addCompanyIdIfMissing('product_types');

        DB::statement("UPDATE product_types SET company_id = (SELECT id FROM companies WHERE companies.customer_id = product_types.customer_id LIMIT 1) WHERE company_id IS NULL");

        // ── 12. inventory_movements ─────────────────────────────────────────
        $this->addCompanyIdIfMissing('inventory_movements');

        DB::statement("UPDATE inventory_movements SET company_id = (SELECT id FROM companies WHERE companies.customer_id = inventory_movements.customer_id LIMIT 1) WHERE company_id IS NULL");

        // ── 13. employees ───────────────────────────────────────────────────
        $this->addCompanyIdIfMissing('employees');

        DB::statement("UPDATE employees SET company_id = (SELECT id FROM companies WHERE companies.customer_id = employees.customer_id LIMIT 1) WHERE company_id IS NULL");

        try { Schema::table('employees', fn (Blueprint $t) => $t->dropUnique('employees_customer_id_code_unique')); } catch (\Throwable) {}
        try { Schema::table('employees', fn (Blueprint $t) => $t->unique(['company_id', 'code'])); } catch (\Throwable) {}

        // ── 14. payroll_periods ─────────────────────────────────────────────
        $this->addCompanyIdIfMissing('payroll_periods');

        DB::statement("UPDATE payroll_periods SET company_id = (SELECT id FROM companies WHERE companies.customer_id = payroll_periods.customer_id LIMIT 1) WHERE company_id IS NULL");

        // ── 15. payroll_concepts ────────────────────────────────────────────
        $this->addCompanyIdIfMissing('payroll_concepts');

        DB::statement("UPDATE payroll_concepts SET company_id = (SELECT id FROM companies WHERE companies.customer_id = payroll_concepts.customer_id LIMIT 1) WHERE company_id IS NULL AND customer_id IS NOT NULL");

        try { Schema::table('payroll_concepts', fn (Blueprint $t) => $t->dropUnique('payroll_concepts_customer_id_system_key_unique')); } catch (\Throwable) {}
        try { Schema::table('payroll_concepts', fn (Blueprint $t) => $t->unique(['company_id', 'system_key'])); } catch (\Throwable) {}

        // ── 16. payroll_settings ────────────────────────────────────────────
        $this->addCompanyIdIfMissing('payroll_settings');

        DB::statement("UPDATE payroll_settings SET company_id = (SELECT id FROM companies WHERE companies.customer_id = payroll_settings.customer_id LIMIT 1) WHERE company_id IS NULL AND customer_id IS NOT NULL");

        // ── billing_clients ─────────────────────────────────────────────────
        $this->addCompanyIdIfMissing('billing_clients');

        DB::statement("UPDATE billing_clients SET company_id = (SELECT id FROM companies WHERE companies.customer_id = billing_clients.customer_id LIMIT 1) WHERE company_id IS NULL AND customer_id IS NOT NULL");

        // ── billing_invoices (already has company_id) ───────────────────────
        DB::statement("UPDATE billing_invoices SET company_id = (SELECT id FROM companies WHERE companies.customer_id = billing_invoices.tenant_customer_id LIMIT 1) WHERE company_id IS NULL AND tenant_customer_id IS NOT NULL");
    }

    public function down(): void
    {
        $tables = [
            'billing_clients',
            'billing_products',
            'billing_settings',
            'billing_dte_correlatives',
            'bank_accounts',
            'accounting_accounts',
            'journal_entries',
            'accounting_packages',
            'cost_centers',
            'suppliers',
            'warehouses',
            'product_types',
            'inventory_movements',
            'employees',
            'payroll_periods',
            'payroll_concepts',
            'payroll_settings',
        ];

        foreach ($tables as $table) {
            if (Schema::hasColumn($table, 'company_id')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropColumn('company_id');
                });
            }
        }
    }
};
