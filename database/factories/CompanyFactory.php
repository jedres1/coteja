<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

class CompanyFactory extends Factory
{
    protected $model = Company::class;

    public function definition(): array
    {
        return [
            'customer_id'   => Customer::factory(),
            'business_name' => fake()->company(),
            'trade_name'    => null,
            'nit_dui'       => '0614' . fake()->unique()->numerify('############'),
            'nrc'           => fake()->numerify('#######'),
            'email'         => fake()->safeEmail(),
            'phone'         => fake()->numerify('########'),
            'environment'   => 'pruebas',
        ];
    }
}
