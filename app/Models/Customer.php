<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $fillable = [
        'name',
        'email',
        'phone',
        'document_type',
        'document_number',
        'nrc',
        'trade_name',
        'business_activity',
        'activity_description',
        'address_department',
        'address_municipality',
        'address',
        'preferred_dte_type',
        'billing_email',
        'billing_phone',
        'status',
        'notes',
    ];

    public function toDteReceptor(): array
    {
        return [
            'tipo_documento' => $this->document_type,
            'numero_documento' => $this->document_number,
            'nrc' => $this->nrc,
            'nombre' => $this->name,
            'nombre_comercial' => $this->trade_name,
            'giro' => $this->business_activity,
            'desc_actividad' => $this->activity_description,
            'departamento' => $this->address_department,
            'municipio' => $this->address_municipality,
            'direccion' => $this->address,
            'telefono' => $this->billing_phone ?: $this->phone,
            'email' => $this->billing_email ?: $this->email,
        ];
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function companies()
    {
        return $this->hasMany(Company::class);
    }

    public function licenses()
    {
        return $this->hasMany(License::class);
    }

    public function payments()
    {
        return $this->hasManyThrough(Payment::class, License::class);
    }

    public function purchaseInvoices()
    {
        return $this->hasMany(PurchaseInvoice::class);
    }

    public function backups()
    {
        return $this->hasManyThrough(Backup::class, License::class);
    }
}
