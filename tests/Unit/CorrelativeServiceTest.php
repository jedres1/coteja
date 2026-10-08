<?php

namespace Tests\Unit;

use App\Models\BillingDteCorrelative;
use App\Services\Billing\CorrelativeService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CorrelativeServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('billing_dte_correlatives');
        Schema::create('billing_dte_correlatives', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('document_type', 2);
            $table->unsignedSmallInteger('year');
            $table->string('establishment', 4)->default('');
            $table->string('point_of_sale', 4)->default('');
            $table->unsignedBigInteger('next_number')->default(1);
            $table->timestamps();
            $table->unique(['document_type', 'year', 'establishment', 'point_of_sale', 'customer_id']);
        });
    }

    public function test_it_reserves_and_advances_a_dte_correlative(): void
    {
        $service = new CorrelativeService;
        $reserved = $service->reserve('14', [
            'codigo_establecimiento' => 'M001',
            'punto_venta' => 'P001',
        ]);

        $this->assertSame(1, (int) $reserved->next_number);

        $service->advance($reserved, 1007);

        $this->assertSame(1008, (int) BillingDteCorrelative::first()->next_number);
    }

    public function test_it_extracts_the_used_number_from_numero_control(): void
    {
        $service = new CorrelativeService;

        $this->assertSame(1007, $service->extractUsedNumber([
            'identificacion' => [
                'numeroControl' => 'DTE-14-M001P001-000000000001007',
            ],
        ]));
    }
}
