<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveJournalEntryRequest extends FormRequest
{
    private function resolveCustomerId(): ?int
    {
        $user = $this->user();
        if (!$user) return null;

        $companyId = session('active_company_id');
        if (!$companyId) return null;

        $company = \App\Models\Company::find($companyId, ['customer_id']);
        if (!$company) return null;

        return $company->customer_id ? (int) $company->customer_id : -1;
    }

    public function rules(): array
    {
        $customerId = $this->resolveCustomerId();

        $accountRule = $customerId
            ? Rule::exists('accounting_accounts', 'id')->where('customer_id', $customerId)
            : 'exists:accounting_accounts,id';

        $costCenterRule = $customerId
            ? Rule::exists('cost_centers', 'id')->where('customer_id', $customerId)
            : 'exists:cost_centers,id';

        return [
            'entry_date'             => ['required', 'date'],
            'description'            => ['required', 'string', 'max:300'],
            'reference'              => ['nullable', 'string', 'max:100'],
            'notes'                  => ['nullable', 'string', 'max:2000'],
            'action'                 => ['required', 'in:borrador,aprobado'],
            'lines'                  => ['required', 'array', 'min:2'],
            'lines.*.account_id'     => ['required', $accountRule],
            'lines.*.cost_center_id' => ['nullable', $costCenterRule],
            'lines.*.description'    => ['nullable', 'string', 'max:255'],
            'lines.*.debit'          => ['required', 'numeric', 'min:0'],
            'lines.*.credit'         => ['required', 'numeric', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $lines = $this->input('lines', []);

            $totalDebit  = round(array_sum(array_column($lines, 'debit')), 2);
            $totalCredit = round(array_sum(array_column($lines, 'credit')), 2);

            if ($totalDebit <= 0) {
                $v->errors()->add('lines', 'El asiento debe tener al menos un monto en Debe y uno en Haber.');
                return;
            }

            if (abs($totalDebit - $totalCredit) > 0.01) {
                $v->errors()->add('lines', "El asiento no cuadra: Debe \${$totalDebit} ≠ Haber \${$totalCredit}. Diferencia: $" . number_format(abs($totalDebit - $totalCredit), 2));
                return;
            }

            foreach ($lines as $i => $line) {
                $d = (float) ($line['debit'] ?? 0);
                $c = (float) ($line['credit'] ?? 0);
                if ($d > 0 && $c > 0) {
                    $v->errors()->add("lines.{$i}", 'Una línea no puede tener monto simultáneo en Debe y Haber.');
                } elseif ($d == 0 && $c == 0) {
                    $v->errors()->add("lines.{$i}", 'Debe ingresar monto en Debe o Haber.');
                }
            }
        });
    }
}
