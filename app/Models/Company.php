<?php

namespace App\Models;

use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\BillingSetting;

class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'business_name',
        'trade_name',
        'nit_dui',
        'nrc',
        'tipo_persona',
        'business_activity',
        'address_department',
        'address_municipality',
        'address_district',
        'address',
        'email',
        'phone',
        'environment',
        'module_accesses',
    ];

    protected function casts(): array
    {
        return [
            'module_accesses' => 'array',
        ];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function licenses()
    {
        return $this->hasMany(License::class);
    }

    public function activeLicense()
    {
        return $this->hasOne(License::class)
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>=', now()->toDateString());
            })
            ->latest('starts_at');
    }

    public function latestLicense()
    {
        return $this->hasOne(License::class)->latest();
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function backups()
    {
        return $this->hasMany(Backup::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    /**
     * Replica los datos de la empresa en la configuración del módulo de facturación.
     * Preserva campos exclusivos del módulo (tipo_persona, certificado, credenciales, etc.).
     */
    public function syncBillingSettings(): void
    {
        // Preserve billing-only fields that have no counterpart in the company table
        $existing = BillingSetting::get('emisor', [], $this->id);

        $emisor = array_merge(
            [
                'desc_actividad'         => $existing['desc_actividad']         ?? null,
                'codigo_establecimiento' => $existing['codigo_establecimiento'] ?? 'M001',
                'punto_venta'            => $existing['punto_venta']            ?? 'P001',
                'logo_path'              => $existing['logo_path']              ?? null,
            ],
            [
                'nit'                 => $this->nit_dui,
                'nrc'                 => $this->nrc,
                'tipo_persona'        => $this->tipo_persona ?? 'Natural',
                'nombre_empresa'      => $this->business_name,
                'nombre_comercial'    => $this->trade_name ?? $this->business_name,
                'actividad_economica' => $this->business_activity,
                'telefono'            => $this->phone,
                'email'               => $this->email,
                'departamento'        => $this->address_department,
                'municipio'           => $this->address_municipality,
                'distrito'            => $this->address_district,
                'direccion'           => $this->address,
            ]
        );

        BillingSetting::put('emisor', $emisor, $this->id);

        // Sync ambiente: testing → '00', production → '01'
        $hacienda = BillingSetting::get('hacienda', [], $this->id);
        $hacienda['ambiente'] = $this->environment === 'production' ? '01' : '00';
        BillingSetting::put('hacienda', $hacienda, $this->id);
    }
}
