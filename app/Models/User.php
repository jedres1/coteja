<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Cache;

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
            $modules = Cache::remember(
                "user_modules:{$this->id}:{$companyId}",
                3600,
                function () use ($companyId) {
                    $company = $this->accessibleCompanies()
                        ->where('companies.id', $companyId)
                        ->first();
                    return $company ? ($company->module_accesses ?? []) : [];
                }
            );
            return in_array($module, $modules, true);
        }

        // No active company: union of all accessible modules for nav visibility
        $modules = Cache::remember(
            "user_all_modules:{$this->id}",
            3600,
            fn () => $this->accessibleCompanies()
                ->get()
                ->pluck('module_accesses')
                ->flatten()
                ->filter()
                ->unique()
                ->values()
                ->all()
        );
        return in_array($module, $modules, true);
    }
}
