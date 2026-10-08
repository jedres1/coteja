<?php

namespace Database\Factories;

use App\Models\PurchaseInvoice;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

class PurchaseInvoiceFactory extends Factory
{
    protected $model = PurchaseInvoice::class;

    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 10, 5000);
        $iva      = round($subtotal * 0.13, 2);
        $total    = round($subtotal + $iva, 2);

        return [
            'supplier_id'    => Supplier::factory(),
            'customer_id'    => null,
            'document_type'  => '01',
            'invoice_number' => fake()->unique()->numerify('DTE-01-########-################-################'),
            'purchase_date'  => fake()->dateTimeBetween('-3 months', 'now')->format('Y-m-d'),
            'due_date'       => null,
            'subtotal'       => $subtotal,
            'iva'            => $iva,
            'total'          => $total,
            'payment_method' => 'contado',
            'payment_status' => 'pending',
            'status'         => 'extracted',
            'notes'          => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(['status' => 'approved', 'payment_status' => 'pending']);
    }

    public function paid(): static
    {
        return $this->state(['status' => 'approved', 'payment_status' => 'paid']);
    }

    public function rejected(): static
    {
        return $this->state(['status' => 'rejected']);
    }
}
