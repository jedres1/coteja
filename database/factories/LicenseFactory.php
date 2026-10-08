<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Customer;
use App\Models\License;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class LicenseFactory extends Factory
{
    protected $model = License::class;

    public function definition(): array
    {
        return [
            'customer_id'       => Customer::factory(),
            'company_id'        => Company::factory(),
            'plan_id'           => Plan::factory(),
            'license_key'       => strtoupper(Str::random(20)),
            'status'            => 'active',
            'starts_at'         => now()->startOfMonth(),
            'expires_at'        => now()->addYear(),
            'max_users'         => 5,
            'max_devices'       => 5,
            'grace_days'        => 7,
            'last_validated_at' => null,
        ];
    }

    public function expired(): static
    {
        return $this->state(['status' => 'expired', 'expires_at' => now()->subMonth()]);
    }

    public function inactive(): static
    {
        return $this->state(['status' => 'inactive']);
    }
}
