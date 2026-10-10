<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveSupplierRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name'                 => ['required', 'string', 'max:255'],
            'email'                => ['nullable', 'email', 'max:255'],
            'phone'                => ['nullable', 'string', 'max:50'],
            'document_type'        => ['required', 'in:13,36,37,03,02'],
            'document_number'      => ['required', 'string', 'max:50'],
            'nrc'                  => ['nullable', 'string', 'max:50'],
            'trade_name'           => ['nullable', 'string', 'max:255'],
            'business_activity'    => ['nullable', 'string', 'max:10'],
            'activity_description' => ['nullable', 'string', 'max:255'],
            'address_department'   => ['required', 'string', 'size:2'],
            'address_municipality' => ['required', 'string', 'between:2,4'],
            'address_district'     => ['nullable', 'string', 'max:50'],
            'address'              => ['required', 'string', 'max:500'],
            'billing_email'        => ['nullable', 'email', 'max:255'],
            'billing_phone'        => ['nullable', 'string', 'max:50'],
            'status'               => ['required', 'in:active,suspended,prospect'],
            'notes'                => ['nullable', 'string', 'max:2000'],
        ];
    }
}
