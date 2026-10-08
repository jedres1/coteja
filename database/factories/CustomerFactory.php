<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'name'                 => fake()->company(),
            'email'                => fake()->unique()->safeEmail(),
            'phone'                => fake()->numerify('########'),
            'document_type'        => '36',
            'document_number'      => '0614' . fake()->unique()->numerify('############'),
            'nrc'                  => fake()->numerify('#######'),
            'trade_name'           => null,
            'business_activity'    => '62010',
            'activity_description' => 'Actividades de programación informática',
            'address_department'   => '06',
            'address_municipality' => '0601',
            'address'              => fake()->streetAddress(),
            'preferred_dte_type'   => '01',
            'status'               => 'active',
        ];
    }

    public function inactive(): static
    {
        return $this->state(['status' => 'inactive']);
    }
}
