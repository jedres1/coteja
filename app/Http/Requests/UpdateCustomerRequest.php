<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerRequest extends FormRequest
{
    public function rules(): array
    {
        $customerId = $this->route('customer')?->id;

        return [
            'name'                 => ['required', 'string', 'max:255'],
            'email'                => ['required', 'email', 'max:255', "unique:customers,email,{$customerId}"],
            'phone'                => ['nullable', 'string', 'max:50'],
            'document_type'        => ['required', 'in:13,36,37,03,02'],
            'document_number'      => ['required', 'string', 'max:50'],
            'nrc'                  => ['nullable', 'string', 'max:50'],
            'trade_name'           => ['nullable', 'string', 'max:255'],
            'business_activity'    => ['nullable', 'string', 'max:10'],
            'activity_description' => ['nullable', 'string', 'max:255'],
            'address_department'   => ['required', 'string', 'size:2'],
            'address_municipality' => ['required', 'string', 'between:2,4'],
            'address'              => ['required', 'string', 'max:500'],
            'preferred_dte_type'   => ['required', 'in:01,03,05,06,07,11,14'],
            'billing_email'        => ['nullable', 'email', 'max:255'],
            'billing_phone'        => ['nullable', 'string', 'max:50'],
            'status'               => ['required', 'in:active,suspended,prospect'],
        ];
    }
}
