<?php

namespace App\Services\Payroll;

use App\Models\AccountingPackage;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\PayrollConcept;
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
        $legalConcepts = PayrollConcept::whereNotNull('system_key')
            ->where('is_active', true)
            ->get()
            ->keyBy('system_key');

        $package = AccountingPackage::where('code', 'NOM')->where('is_active', true)->first();

        $missing = [];
        if (! $package) {
            $missing[] = 'paquete contable NOM';
        }
        foreach ([
            'salario' => 'cuenta de gasto para Salario base',
            'isss_laboral' => 'cuenta por pagar para ISSS laboral',
            'isss_patronal' => 'cuenta de gasto para ISSS patronal',
            'afp_laboral' => 'cuenta por pagar para AFP laboral',
            'afp_patronal' => 'cuenta de gasto para AFP patronal',
            'isr' => 'cuenta por pagar para ISR',
        ] as $key => $label) {
            if (! $legalConcepts->get($key)?->account_id) {
                $missing[] = $label . ' en Gestión de conceptos';
            }
        }
        if (! $legalConcepts->get('isss_patronal')?->payable_account_id) {
            $missing[] = 'cuenta por pagar para ISSS patronal en Gestión de conceptos';
        }
        if (! $legalConcepts->get('afp_patronal')?->payable_account_id) {
            $missing[] = 'cuenta por pagar para AFP patronal en Gestión de conceptos';
        }
        if (! $settings->account_salaries_payable_id) {
            $missing[] = 'cuenta Salarios por pagar en Configuración';
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
        $benefitConcepts = $this->sumConcepts($lines, 'beneficio');
        $deductionConcepts = $this->sumConcepts($lines, 'deduccion');
        $totalBenefits = round(array_sum($benefitConcepts), 2);

        foreach (array_merge(array_keys($benefitConcepts), array_keys($deductionConcepts)) as $accountId) {
            if (! $accountId) {
                throw new \RuntimeException('Hay conceptos de nómina sin cuenta contable asignada.');
            }
        }

        return DB::transaction(function () use (
            $period, $package, $settings, $legalConcepts, $userId,
            $totalGross, $totalIsssEmployer, $totalAfpEmployer,
            $totalIsssEmployee, $totalAfpEmployee, $totalIsr, $totalNet,
            $benefitConcepts, $deductionConcepts, $totalBenefits
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
                    'account_id'       => $legalConcepts->get('salario')->account_id,
                    'description'      => 'Sueldos y Salarios — ' . $period->name,
                    'debit'            => max(0, round($totalGross - $totalBenefits, 2)),
                    'credit'           => 0,
                    'sort_order'       => $sort++,
                ],
                [
                    'journal_entry_id' => $entry->id,
                    'account_id'       => $legalConcepts->get('isss_patronal')->account_id,
                    'description'      => 'Cuota Patronal ISSS — ' . $period->name,
                    'debit'            => $totalIsssEmployer,
                    'credit'           => 0,
                    'sort_order'       => $sort++,
                ],
                [
                    'journal_entry_id' => $entry->id,
                    'account_id'       => $legalConcepts->get('afp_patronal')->account_id,
                    'description'      => 'Cuota Patronal AFP — ' . $period->name,
                    'debit'            => $totalAfpEmployer,
                    'credit'           => 0,
                    'sort_order'       => $sort++,
                ],
                // HABER
                [
                    'journal_entry_id' => $entry->id,
                    'account_id'       => $legalConcepts->get('isss_laboral')->account_id,
                    'description'      => 'Cuota Laboral ISSS por Pagar — ' . $period->name,
                    'debit'            => 0,
                    'credit'           => $totalIsssEmployee,
                    'sort_order'       => $sort++,
                ],
                [
                    'journal_entry_id' => $entry->id,
                    'account_id'       => $legalConcepts->get('isss_patronal')->payable_account_id,
                    'description'      => 'Cuota Patronal ISSS por Pagar — ' . $period->name,
                    'debit'            => 0,
                    'credit'           => $totalIsssEmployer,
                    'sort_order'       => $sort++,
                ],
                [
                    'journal_entry_id' => $entry->id,
                    'account_id'       => $legalConcepts->get('afp_laboral')->account_id,
                    'description'      => 'Cuota Laboral AFP por Pagar — ' . $period->name,
                    'debit'            => 0,
                    'credit'           => $totalAfpEmployee,
                    'sort_order'       => $sort++,
                ],
                [
                    'journal_entry_id' => $entry->id,
                    'account_id'       => $legalConcepts->get('afp_patronal')->payable_account_id,
                    'description'      => 'Cuota Patronal AFP por Pagar — ' . $period->name,
                    'debit'            => 0,
                    'credit'           => $totalAfpEmployer,
                    'sort_order'       => $sort++,
                ],
                [
                    'journal_entry_id' => $entry->id,
                    'account_id'       => $legalConcepts->get('isr')->account_id,
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

            foreach ($benefitConcepts as $accountId => $amount) {
                if ($amount > 0) {
                    $entryLines[] = [
                        'journal_entry_id' => $entry->id,
                        'account_id' => $accountId,
                        'description' => 'Beneficios de nómina — ' . $period->name,
                        'debit' => $amount,
                        'credit' => 0,
                        'sort_order' => $sort++,
                    ];
                }
            }

            foreach ($deductionConcepts as $accountId => $amount) {
                if ($amount > 0) {
                    $entryLines[] = [
                        'journal_entry_id' => $entry->id,
                        'account_id' => $accountId,
                        'description' => 'Deducciones de nómina — ' . $period->name,
                        'debit' => 0,
                        'credit' => $amount,
                        'sort_order' => $sort++,
                    ];
                }
            }

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

    private function sumConcepts($lines, string $type): array
    {
        $totals = [];
        foreach ($lines as $line) {
            foreach ($line->concept_details ?? [] as $concept) {
                if (isset($concept['system_key'])) {
                    continue;
                }
                if (($concept['type'] ?? null) !== $type || (float) ($concept['amount'] ?? 0) <= 0) {
                    continue;
                }
                $accountId = $concept['account_id'] ?? null;
                $totals[$accountId] = ($totals[$accountId] ?? 0) + (float) $concept['amount'];
            }
        }
        return array_map(fn ($amount) => round($amount, 2), $totals);
    }
}
