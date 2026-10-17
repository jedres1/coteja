<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'customer_id',
        'name',
        'email',
        'role',
        'is_active',
        'module_accesses',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_active' => 'boolean',
            'module_accesses' => 'array',
            'password' => 'hashed',
        ];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function accessibleCompanies()
    {
        return $this->belongsToMany(Company::class)->withTimestamps();
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isInquilino(): bool
    {
        return $this->role === 'inquilino';
    }

    public function canAccessAdmin(): bool
    {
        return in_array($this->role, ['admin', 'inquilino'], true);
    }

    public function hasModuleAccess(string $module): bool
    {
        if ($this->isAdmin()) return true;

        $companyId = session('active_company_id');
        if ($companyId) {
            $company = $this->accessibleCompanies()
                ->where('companies.id', $companyId)
                ->first();
            return $company && in_array($module, $company->module_accesses ?? [], true);
        }

        // No active company: show union so nav reflects all possible modules
        return $this->accessibleCompanies
            ->pluck('module_accesses')
            ->flatten()
            ->filter()
            ->unique()
            ->contains($module);
    }
}
