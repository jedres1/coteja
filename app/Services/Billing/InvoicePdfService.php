<?php

namespace App\Services\Billing;

use App\Models\BillingInvoice;
use App\Services\DteEngine;
use Illuminate\Support\Facades\Storage;

class InvoicePdfService
{
    public function __construct(private readonly DteEngine $engine) {}

    public function generate(BillingInvoice $invoice, array $settings): array
    {
        $dte = $invoice->signed_dte ?: $invoice->json_dte ?: [];
        $code = preg_replace('/[^A-Za-z0-9_-]/', '_', $invoice->generation_code ?: $invoice->number_control);

        return $this->generateDocument([
            'numero_control' => $invoice->number_control,
            'codigo_generacion' => $invoice->generation_code,
            'fecha_emision' => optional($invoice->issued_at)->format('Y-m-d'),
            'cliente' => $invoice->customer_name,
            'subtotal' => (float) $invoice->subtotal,
            'iva' => (float) $invoice->iva,
            'total' => (float) ($invoice->total ?: data_get($dte, 'resumen.totalPagar', 0)),
            'estado' => $invoice->status,
            'sello_recepcion' => $invoice->reception_stamp,
        ], $dte, $settings, 'DTE_'.$code.'.pdf');
    }

    public function generateDocument(array $factura, array|string $dte, array $settings, string $filename = 'dte.pdf'): array
    {
        return $this->engine->pdf($factura, $dte, $this->resolveLogoPath($settings['emisor'] ?? []), $filename);
    }

    private function resolveLogoPath(array $emisor): array
    {
        $configuredPath = trim((string) ($emisor['logo_path'] ?? ''));
        if ($configuredPath === '' || is_file($configuredPath)) return $emisor;

        $path = parse_url($configuredPath, PHP_URL_PATH) ?: $configuredPath;
        $relativePath = preg_replace('#^/?storage/#', '', ltrim($path, '/'));
        $relativePath = ltrim((string) $relativePath, '/');

        if (Storage::disk('public')->exists($relativePath)) {
            $emisor['logo_path'] = Storage::disk('public')->path($relativePath);
            return $emisor;
        }

        $publicPath = public_path(ltrim($path, '/'));
        if (is_file($publicPath)) {
            $emisor['logo_path'] = $publicPath;
        }

        return $emisor;
    }
}
