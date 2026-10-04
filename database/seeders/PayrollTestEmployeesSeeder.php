<?php

namespace Database\Seeders;

use App\Models\Employee;
use Illuminate\Database\Seeder;

class PayrollTestEmployeesSeeder extends Seeder
{
    public function run(): void
    {
        Employee::updateOrCreate(
            ['code' => 'TEST-1100'],
            [
                'name' => 'Empleado de prueba $1,100',
                'position' => 'Vendedor',
                'hire_date' => '2026-01-01',
                'salary' => 1100,
                'afp' => 'crecer',
                'is_active' => true,
            ]
        );

        Employee::updateOrCreate(
            ['code' => 'TEST-0550'],
            [
                'name' => 'Empleado de prueba $550',
                'position' => 'Contador',
                'hire_date' => '2026-01-01',
                'salary' => 550,
                'afp' => 'crecer',
                'is_active' => true,
            ]
        );
    }
}