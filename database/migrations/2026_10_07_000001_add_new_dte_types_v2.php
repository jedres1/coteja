<?php

use App\Models\BillingSetting;
use App\Models\BillingDteCorrelative;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Agregar nuevos tipos DTE V2.0/V2.1 a la configuración existente de documentos habilitados
        $existingDocs = BillingSetting::get('documentos', []);

        $newTypes = ['04', '08', '15'];
        $updated = false;

        foreach ($newTypes as $type) {
            if (! in_array($type, $existingDocs)) {
                $updated = true;
            }
        }

        if ($updated) {
            $merged = array_values(array_unique(array_merge($existingDocs, $newTypes)));
            sort($merged);
            BillingSetting::put('documentos', $merged);
        }

        // Crear correlativos iniciales para los nuevos tipos si hay correlativos existentes
        $existingCorrelative = BillingDteCorrelative::first();

        if ($existingCorrelative) {
            $year = now()->year;
            $establishment = $existingCorrelative->establishment;
            $pointOfSale = $existingCorrelative->point_of_sale;

            foreach ($newTypes as $type) {
                BillingDteCorrelative::firstOrCreate(
                    [
                        'document_type' => $type,
                        'year' => $year,
                        'establishment' => $establishment,
                        'point_of_sale' => $pointOfSale,
                    ],
                    ['next_number' => 1]
                );
            }
        }
    }

    public function down(): void
    {
        $newTypes = ['04', '08', '15'];

        $existing = BillingSetting::get('documentos', []);
        $filtered = array_values(array_diff($existing, $newTypes));
        BillingSetting::put('documentos', $filtered);

        BillingDteCorrelative::whereIn('document_type', $newTypes)->delete();
    }
};
