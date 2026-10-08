<?php

namespace App\Services\Billing;

use App\Models\BillingDteCorrelative;

class CorrelativeService
{
    public function reserve(string $documentType, array $config, ?int $customerId = null): BillingDteCorrelative
    {
        $year          = (int) now()->year;
        $establishment = $this->normalizeCode($config['codigo_establecimiento'] ?? 'M001');
        $pointOfSale   = $this->normalizeCode($config['punto_venta'] ?? 'P001');

        $criteria = [
            'customer_id'   => $customerId,
            'document_type' => $documentType,
            'year'          => $year,
            'establishment' => $establishment,
            'point_of_sale' => $pointOfSale,
        ];

        BillingDteCorrelative::firstOrCreate($criteria, ['next_number' => 1]);

        return BillingDteCorrelative::where($criteria)->lockForUpdate()->firstOrFail();
    }

    public function advance(BillingDteCorrelative $correlative, int $usedNumber): void
    {
        $correlative->update([
            'next_number' => max((int) $correlative->next_number, $usedNumber + 1),
        ]);
    }

    public function extractUsedNumber(array $dte): ?int
    {
        $numberControl = (string) data_get($dte, 'identificacion.numeroControl', '');

        if (! preg_match('/^DTE-[0-9]{2}-[A-Z0-9]{8}-([0-9]{15})$/', $numberControl, $matches)) {
            return null;
        }

        return (int) $matches[1];
    }

    private function normalizeCode(string $value): string
    {
        return substr(str_pad(preg_replace('/[^A-Z0-9]/', '', strtoupper($value)), 4, '0', STR_PAD_LEFT), 0, 4);
    }
}
