<?php

namespace App\Services\Billing;

use App\Models\BillingInvoice;
use App\Services\DteEngine;
use RuntimeException;

class InvoiceReturnService
{
    public function __construct(private readonly DteEngine $engine) {}

    public function process(BillingInvoice $invoice, array $settings, array $input): array
    {
        $this->ensureCanReturn($invoice);

        $dte = $invoice->signed_dte ?: $invoice->json_dte ?: [];
        $items = $input['items'] ?? [];
        $resumen = $input['resumen'] ?? [];

        if (empty($items)) {
            throw new RuntimeException('Debe incluir al menos un ítem a retornar.');
        }

        $documentoRelacionado = [
            'codigoGeneracion' => data_get($dte, 'identificacion.codigoGeneracion', $invoice->generation_code),
            'selloRecibido'    => $invoice->reception_stamp,
            'numeroControl'    => data_get($dte, 'identificacion.numeroControl', $invoice->number_control),
            'fecEmi'           => data_get($dte, 'identificacion.fecEmi', optional($invoice->issued_at)->format('Y-m-d')),
        ];

        $receptor = $dte['receptor'] ?? [];
        $config = $settings['emisor'] ?? [];

        $generated = $this->engine->generateEventoRetorno($config, $receptor, $items, $resumen, $documentoRelacionado);
        $er = $generated['er'] ?? null;

        if (! $er) {
            throw new RuntimeException($generated['validation']['errores'][0] ?? 'No se pudo generar el Evento de Retorno.');
        }

        $validation = $generated['validation'] ?? [];
        if (! ($validation['valido'] ?? false)) {
            throw new RuntimeException('ER no cumple el schema: '.implode(' | ', array_slice($validation['errores'] ?? [], 0, 8)));
        }

        $firma = $this->normalizeFirma($settings['firma'] ?? []);
        if (empty($firma['certificado_path']) || empty($firma['password'])) {
            throw new RuntimeException('Falta certificado o contraseña para firmar el Evento de Retorno.');
        }

        $signed = $this->engine->signInternal(
            $er,
            $this->certificateNit($firma, $settings['emisor'] ?? []),
            $firma['password'],
            $firma['certificado_path'],
        );

        $signedEr = $this->normalizeSignedDocument($signed);
        if (! $signedEr) {
            throw new RuntimeException($signed['error'] ?? 'El firmador no devolvió un Evento de Retorno con firmaMh.');
        }

        $signedValidation = $this->engine->validateEvent('retorno', $signedEr);
        if (! ($signedValidation['validation']['valido'] ?? false)) {
            throw new RuntimeException('ER firmado no cumple el schema: '.implode(' | ', array_slice($signedValidation['validation']['errores'] ?? [], 0, 8)));
        }

        $hacienda = $this->normalizeHacienda($settings['hacienda'] ?? []);
        if (empty($hacienda['usuario']) || empty($hacienda['password'])) {
            throw new RuntimeException('Faltan credenciales de Hacienda para enviar el Evento de Retorno.');
        }

        $nit = preg_replace('/\D+/', '', $config['nit'] ?? '');
        $response = $this->engine->sendRetorno($signedEr, $hacienda, $nit);

        if (! ($response['success'] ?? false)) {
            return [
                'success'     => false,
                'er'          => $er,
                'signedEr'    => $signedEr,
                'response'    => $response,
                'message'     => $response['error'] ?? 'Hacienda rechazó el Evento de Retorno.',
            ];
        }

        $invoice->update([
            'return_stamp' => $response['selloRecibido'] ?? null,
            'returned_at'  => now(),
            'return_json'  => [
                'evento'    => $signedEr,
                'respuesta' => $response['raw'] ?? $response,
            ],
        ]);

        return [
            'success'  => true,
            'er'       => $er,
            'signedEr' => $signedEr,
            'response' => $response,
            'invoice'  => $invoice->fresh(),
            'message'  => 'Evento de Retorno enviado exitosamente a Hacienda.',
        ];
    }

    public function ensureCanReturn(BillingInvoice $invoice): void
    {
        if ($invoice->document_type !== '11') {
            throw new RuntimeException('El Evento de Retorno solo aplica para Facturas de Exportación (tipo 11).');
        }

        if (! $invoice->accepted || ! $invoice->reception_stamp || ! in_array($invoice->status, ['ENVIADO', 'ACEPTADO'], true)) {
            throw new RuntimeException('Solo se puede registrar retorno en facturas de exportación enviadas y aceptadas por Hacienda.');
        }

        if ($invoice->status === 'ANULADO') {
            throw new RuntimeException('No se puede registrar retorno en una factura anulada.');
        }
    }

    private function normalizeSignedDocument(array $signed): ?array
    {
        $document = $signed['documentoFirmado'] ?? null;
        if (! is_array($document)) {
            return null;
        }

        if (! empty($document['firmaMh'])) {
            return $document;
        }

        if (! empty($signed['firmaMh'])) {
            $document['firmaMh'] = $signed['firmaMh'];

            return $document;
        }

        return null;
    }

    private function normalizeHacienda(array $input): array
    {
        $environment = $input['ambiente'] ?? '00';

        return [
            'ambiente' => in_array($environment, ['produccion', '01'], true) ? '01' : '00',
            'usuario'  => $input['usuario'] ?? '',
            'password' => $input['password'] ?? '',
        ];
    }

    private function normalizeFirma(array $input): array
    {
        return [
            'nit'              => $input['nit'] ?? $input['firmador_usuario'] ?? $input['firmadorUsuario'] ?? '',
            'certificado_path' => $input['certificado_path'] ?? '',
            'password'         => $input['password'] ?? $input['certificado_password'] ?? $input['firmador_pin'] ?? '',
        ];
    }

    private function certificateNit(array $firma, array $emisor): string
    {
        return preg_replace('/\D+/', '', $firma['nit'] ?? $emisor['nit'] ?? '');
    }
}
