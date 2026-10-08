<?php

namespace Database\Factories;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    public function definition(): array
    {
        return [
            'name'                 => fake()->company(),
            'email'                => fake()->unique()->safeEmail(),
            'phone'                => fake()->numerify('########'),
            'document_type'        => '36',
            'document_number'      => '0614' . fake()->unique()->numerify('############'),
            'nrc'                  => fake()->numerify('#######'),
            'status'               => 'active',
            'address_department'   => '06',
            'address_municipality' => '0601',
            'address'              => fake()->streetAddress(),
        ];
    }
}
