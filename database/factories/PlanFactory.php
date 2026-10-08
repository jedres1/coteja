<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public function definition(): array
    {
        return [
            'code'                  => strtoupper(fake()->unique()->lexify('????')),
            'name'                  => fake()->words(2, true),
            'monthly_price'         => fake()->randomFloat(2, 0, 500),
            'annual_price'          => null,
            'implementation_fee'    => 0,
            'additional_user_price' => 0,
            'included_users'        => 5,
            'max_devices'           => 5,
            'support_included'      => false,
            'cloud_backup'          => false,
            'unlimited_documents'   => true,
            'is_active'             => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
