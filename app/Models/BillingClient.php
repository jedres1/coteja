<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillingClient extends Model
{
    protected $fillable = [
        'customer_id',
        'company_id',
        'name',
        'trade_name',
        'document_type',
        'document_number',
        'nrc',
        'business_activity',
        'email',
        'phone',
        'billing_email',
        'billing_phone',
        'address_department',
        'address_municipality',
        'address_district',
        'address',
        'preferred_dte_type',
        'is_clients_varios',
    ];

    protected function casts(): array
    {
        return [
            'is_clients_varios' => 'boolean',
        ];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function invoices()
    {
        return $this->hasMany(BillingInvoice::class);
    }

    public function toDteReceptor(): array
    {
        return [
            'tipo_documento'  => $this->document_type,
            'numero_documento' => $this->document_number,
            'nrc'             => $this->nrc,
            'nombre'          => $this->name,
            'nombre_comercial' => $this->trade_name,
            'giro'            => $this->business_activity,
            'desc_actividad'  => $this->activity_description,
            'departamento'    => $this->address_department,
            'municipio'       => $this->address_municipality,
            'direccion'       => $this->address,
            'telefono'        => $this->billing_phone ?: $this->phone,
            'email'           => $this->billing_email ?: $this->email,
        ];
    }

    public function scopeForCustomer($query, ?int $companyId)
    {
        return $companyId
            ? $query->where('company_id', $companyId)
            : $query->whereNull('company_id');
    }

    public function scopeForCompany($query, ?int $companyId)
    {
        return $companyId
            ? $query->where('company_id', $companyId)
            : $query->whereNull('company_id');
    }
}
