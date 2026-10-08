<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $newTypes = ['04', '08', '15'];

        $row = DB::table('billing_settings')->where('key', 'documentos')->first();
        $existingDocs = $row ? (json_decode($row->value, true) ?? []) : [];

        $merged = array_values(array_unique(array_merge($existingDocs, $newTypes)));
        sort($merged);

        DB::table('billing_settings')->updateOrInsert(
            ['key' => 'documentos'],
            ['value' => json_encode($merged, JSON_UNESCAPED_UNICODE)],
        );

        $existingCorrelative = DB::table('billing_dte_correlatives')->first();

        if ($existingCorrelative) {
            $year          = now()->year;
            $establishment = $existingCorrelative->establishment;
            $pointOfSale   = $existingCorrelative->point_of_sale;

            foreach ($newTypes as $type) {
                $exists = DB::table('billing_dte_correlatives')->where([
                    'document_type' => $type,
                    'year'          => $year,
                    'establishment' => $establishment,
                    'point_of_sale' => $pointOfSale,
                ])->exists();

                if (! $exists) {
                    DB::table('billing_dte_correlatives')->insert([
                        'document_type' => $type,
                        'year'          => $year,
                        'establishment' => $establishment,
                        'point_of_sale' => $pointOfSale,
                        'next_number'   => 1,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        $newTypes = ['04', '08', '15'];

        $row = DB::table('billing_settings')->where('key', 'documentos')->first();
        $existing = $row ? (json_decode($row->value, true) ?? []) : [];
        $filtered = array_values(array_diff($existing, $newTypes));

        DB::table('billing_settings')->updateOrInsert(
            ['key' => 'documentos'],
            ['value' => json_encode($filtered, JSON_UNESCAPED_UNICODE)],
        );

        DB::table('billing_dte_correlatives')->whereIn('document_type', $newTypes)->delete();
    }
};
