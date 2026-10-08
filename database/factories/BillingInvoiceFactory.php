<?php

namespace Database\Factories;

use App\Models\BillingInvoice;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class BillingInvoiceFactory extends Factory
{
    protected $model = BillingInvoice::class;

    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 10, 5000);
        $iva      = round($subtotal * 0.13, 2);
        $total    = round($subtotal + $iva, 2);

        return [
            'tenant_customer_id' => null,
            'number_control'     => 'DTE-01-' . fake()->unique()->numerify('########-################-################'),
            'generation_code'    => strtoupper((string) Str::uuid()),
            'document_type'      => '01',
            'issued_at'          => now()->toDateString(),
            'customer_id'        => null,
            'customer_name'      => fake()->company(),
            'subtotal'           => $subtotal,
            'iva'                => $iva,
            'total'              => $total,
            'status'             => 'ENVIADO',
            'has_error'          => false,
            'accepted'           => true,
            'email_sent'         => false,
            'payment_status'     => 'pendiente',
            'amount_paid'        => 0,
        ];
    }

    public function pending(): static
    {
        return $this->state(['status' => 'PENDIENTE', 'accepted' => false]);
    }

    public function voided(): static
    {
        return $this->state(['status' => 'ANULADO', 'voided_at' => now()]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attrs) => [
            'payment_status' => 'pagado',
            'amount_paid'    => $attrs['total'],
            'paid_at'        => now(),
        ]);
    }
}
