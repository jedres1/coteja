<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SaveJournalEntryRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'entry_date'             => ['required', 'date'],
            'description'            => ['required', 'string', 'max:300'],
            'reference'              => ['nullable', 'string', 'max:100'],
            'notes'                  => ['nullable', 'string', 'max:2000'],
            'action'                 => ['required', 'in:borrador,aprobado'],
            'lines'                  => ['required', 'array', 'min:2'],
            'lines.*.account_id'     => ['required', 'exists:accounting_accounts,id'],
            'lines.*.cost_center_id' => ['nullable', 'exists:cost_centers,id'],
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
