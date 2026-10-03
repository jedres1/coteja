<?php

namespace App\Services\Payroll;

use App\Models\AccountingPackage;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\PayrollPeriod;
use App\Models\PayrollSetting;
use Illuminate\Support\Facades\DB;

class PayrollEntryService
{
    public function createFromPayroll(PayrollPeriod $period, ?int $userId = null): ?JournalEntry
    {
        if ($period->status === 'aplicado') {
            return null;
        }

        $lines = $period->lines()->with('employee')->get();

        if ($lines->isEmpty()) {
            return null;
        }

        $settings = PayrollSetting::current();

        $package = AccountingPackage::where('code', 'NOM')->where('is_active', true)->first();

        $missing = [];
        if (! $package) {
            $missing[] = 'paquete contable NOM';
        }
        if (! $settings->account_salaries_id) {
            $missing[] = 'cuenta Sueldos y Salarios';
        }
        if (! $settings->account_isss_employer_id) {
            $missing[] = 'cuenta Gasto Cuota Patronal ISSS';
        }
        if (! $settings->account_afp_employer_id) {
            $missing[] = 'cuenta Gasto Cuota Patronal AFP';
        }
        if (! $settings->account_isss_employee_payable_id) {
            $missing[] = 'cuenta Cuota Laboral ISSS por Pagar';
        }
        if (! $settings->account_isss_employer_payable_id) {
            $missing[] = 'cuenta Cuota Patronal ISSS por Pagar';
        }
        if (! $settings->account_afp_employee_payable_id) {
            $missing[] = 'cuenta Cuota Laboral AFP por Pagar';
        }
        if (! $settings->account_afp_employer_payable_id) {
            $missing[] = 'cuenta Cuota Patronal AFP por Pagar';
        }
        if (! $settings->account_isr_payable_id) {
            $missing[] = 'cuenta ISR Empleados Retenido por Pagar';
        }
        if (! $settings->account_salaries_payable_id) {
            $missing[] = 'cuenta Salarios por Pagar';
        }

        if (! empty($missing)) {
            throw new \RuntimeException(
                'Configuración de nómina incompleta. Faltan: ' . implode(', ', $missing) . '.'
            );
        }

        // Totals
        $totalGross         = round($lines->sum(fn ($l) => (float) $l->gross_salary), 2);
        $totalIsssEmployer  = round($lines->sum(fn ($l) => (float) $l->isss_employer), 2);
        $totalAfpEmployer   = round($lines->sum(fn ($l) => (float) $l->afp_employer), 2);
        $totalIsssEmployee  = round($lines->sum(fn ($l) => (float) $l->isss_employee), 2);
        $totalAfpEmployee   = round($lines->sum(fn ($l) => (float) $l->afp_employee), 2);
        $totalIsr           = round($lines->sum(fn ($l) => (float) $l->isr), 2);
        $totalNet           = round($lines->sum(fn ($l) => (float) $l->net_salary), 2);

        return DB::transaction(function () use (
            $period, $package, $settings, $userId,
            $totalGross, $totalIsssEmployer, $totalAfpEmployer,
            $totalIsssEmployee, $totalAfpEmployee, $totalIsr, $totalNet
        ) {
            $number = $package->reserveNextNumber();

            $entry = JournalEntry::create([
                'entry_number'          => $number,
                'entry_date'            => $period->period_end->toDateString(),
                'description'           => 'Nómina: ' . $period->name,
                'reference'             => $period->name,
                'status'                => 'aprobado',
                'created_by'            => $userId,
                'approved_by'           => $userId,
                'approved_at'           => now(),
                'accounting_package_id' => $package->id,
                'source_type'           => 'payroll',
                'source_id'             => $period->id,
                'source_document'       => $period->name,
            ]);

            $sort = 0;
            $entryLines = [
                // DEBE
                [
                    'journal_entry_id' => $entry->id,
                    'account_id'       => $settings->account_salaries_id,
                    'description'      => 'Sueldos y Salarios — ' . $period->name,
                    'debit'            => $totalGross,
                    'credit'           => 0,
                    'sort_order'       => $sort++,
                ],
                [
                    'journal_entry_id' => $entry->id,
                    'account_id'       => $settings->account_isss_employer_id,
                    'description'      => 'Cuota Patronal ISSS — ' . $period->name,
                    'debit'            => $totalIsssEmployer,
                    'credit'           => 0,
                    'sort_order'       => $sort++,
                ],
                [
                    'journal_entry_id' => $entry->id,
                    'account_id'       => $settings->account_afp_employer_id,
                    'description'      => 'Cuota Patronal AFP — ' . $period->name,
                    'debit'            => $totalAfpEmployer,
                    'credit'           => 0,
                    'sort_order'       => $sort++,
                ],
                // HABER
                [
                    'journal_entry_id' => $entry->id,
                    'account_id'       => $settings->account_isss_employee_payable_id,
                    'description'      => 'Cuota Laboral ISSS por Pagar — ' . $period->name,
                    'debit'            => 0,
                    'credit'           => $totalIsssEmployee,
                    'sort_order'       => $sort++,
                ],
                [
                    'journal_entry_id' => $entry->id,
                    'account_id'       => $settings->account_isss_employer_payable_id,
                    'description'      => 'Cuota Patronal ISSS por Pagar — ' . $period->name,
                    'debit'            => 0,
                    'credit'           => $totalIsssEmployer,
                    'sort_order'       => $sort++,
                ],
                [
                    'journal_entry_id' => $entry->id,
                    'account_id'       => $settings->account_afp_employee_payable_id,
                    'description'      => 'Cuota Laboral AFP por Pagar — ' . $period->name,
                    'debit'            => 0,
                    'credit'           => $totalAfpEmployee,
                    'sort_order'       => $sort++,
                ],
                [
                    'journal_entry_id' => $entry->id,
                    'account_id'       => $settings->account_afp_employer_payable_id,
                    'description'      => 'Cuota Patronal AFP por Pagar — ' . $period->name,
                    'debit'            => 0,
                    'credit'           => $totalAfpEmployer,
                    'sort_order'       => $sort++,
                ],
                [
                    'journal_entry_id' => $entry->id,
                    'account_id'       => $settings->account_isr_payable_id,
                    'description'      => 'ISR Empleados Retenido por Pagar — ' . $period->name,
                    'debit'            => 0,
                    'credit'           => $totalIsr,
                    'sort_order'       => $sort++,
                ],
                [
                    'journal_entry_id' => $entry->id,
                    'account_id'       => $settings->account_salaries_payable_id,
                    'description'      => 'Salarios por Pagar — ' . $period->name,
                    'debit'            => 0,
                    'credit'           => $totalNet,
                    'sort_order'       => $sort++,
                ],
            ];

            JournalEntryLine::insert($entryLines);

            $period->update([
                'journal_entry_id' => $entry->id,
                'status'           => 'aplicado',
                'applied_at'       => now(),
                'applied_by'       => $userId,
            ]);

            return $entry;
        });
    }
}
