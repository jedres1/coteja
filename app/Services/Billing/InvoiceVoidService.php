<?php

namespace App\Services\Billing;

use App\Models\BillingInvoice;
use App\Services\DteEngine;
use Carbon\Carbon;
use Illuminate\Support\Str;
use RuntimeException;

class InvoiceVoidService
{
    public function __construct(private readonly DteEngine $engine) {}

    public function process(BillingInvoice $invoice, array $settings, array $input): array
    {
        $this->ensureCanVoid($invoice);

        $dte = $invoice->signed_dte ?: $invoice->json_dte ?: [];
        $documentType = $this->documentType($invoice, $dte);
        $voidType = (int) ($input['tipoAnulacion'] ?? 2);
        $replacementCode = $this->normalizeGenerationCode($input['codigoGeneracionR'] ?? null);
        $reason = trim((string) ($input['motivo'] ?? ''));

        $this->validateVoidInput($invoice, $documentType, $voidType, $replacementCode, $reason);

        $event = $this->buildEvent($invoice, $dte, $settings['emisor'] ?? [], [
            'tipoAnulacion' => $voidType,
            'codigoGeneracionR' => $replacementCode,
            'motivo' => $reason,
        ]);

        $validation = $this->engine->validateEvent('anulacion', $event);
        $validationResult = $validation['validation'] ?? [];
        if (! ($validationResult['valido'] ?? false)) {
            throw new RuntimeException('Evento de anulación no cumple el schema oficial: '.implode(' | ', array_slice($validationResult['errores'] ?? [], 0, 8)));
        }

        $firma = $this->normalizeFirma($settings['firma'] ?? []);
        if (empty($firma['certificado_path']) || empty($firma['password'])) {
            throw new RuntimeException('Falta certificado o contraseña para firmar el evento de anulación.');
        }

        $signed = $this->engine->signInternal(
            $event,
            $this->certificateNit($firma, $settings['emisor'] ?? []),
            $firma['password'],
            $firma['certificado_path'],
        );
        $signedEvent = $this->normalizeSignedDocument($signed);
        if (! $signedEvent) {
            throw new RuntimeException($signed['error'] ?? 'El firmador no devolvió un evento de anulación con firmaMh.');
        }

        $signedValidation = $this->engine->validateEvent('anulacion', $signedEvent);
        $signedValidationResult = $signedValidation['validation'] ?? [];
        if (! ($signedValidationResult['valido'] ?? false)) {
            throw new RuntimeException('Evento firmado de anulación no cumple el schema oficial: '.implode(' | ', array_slice($signedValidationResult['errores'] ?? [], 0, 8)));
        }

        $hacienda = $this->normalizeHacienda($settings['hacienda'] ?? []);
        if (empty($hacienda['usuario']) || empty($hacienda['password'])) {
            throw new RuntimeException('Faltan credenciales de Hacienda para enviar la anulación.');
        }

        $response = $this->engine->voidDte($signedEvent, $hacienda);
        if (! ($response['success'] ?? false)) {
            return [
                'success' => false,
                'event' => $event,
                'signedEvent' => $signedEvent,
                'response' => $response,
                'message' => $response['error'] ?? 'Hacienda rechazó el evento de anulación.',
            ];
        }

        $invoice->update([
            'status' => 'ANULADO',
            'has_error' => false,
            'voided_at' => now(),
            'void_stamp' => $response['selloRecibido'] ?? null,
            'void_reason' => $reason,
            'void_json' => [
                'evento' => $signedEvent,
                'respuesta' => $response['raw'] ?? $response,
            ],
            'observations' => json_encode($response['observaciones'] ?? $response['raw'] ?? $response, JSON_UNESCAPED_UNICODE),
        ]);

        return [
            'success' => true,
            'event' => $event,
            'signedEvent' => $signedEvent,
            'response' => $response,
            'invoice' => $invoice->fresh(),
            'message' => 'Factura anulada exitosamente en Hacienda.',
        ];
    }

    public function ensureCanVoid(BillingInvoice $invoice): void
    {
        if ($invoice->status === 'ANULADO') {
            throw new RuntimeException('La factura ya está anulada.');
        }

        if (! $invoice->accepted || ! $invoice->reception_stamp || ! in_array($invoice->status, ['ENVIADO', 'ACEPTADO'], true)) {
            throw new RuntimeException('Solo se pueden anular facturas enviadas y aceptadas por Hacienda.');
        }

        $limit = $this->voidLimit($invoice);
        if (! $limit['allowed']) {
            throw new RuntimeException($limit['message']);
        }
    }

    public function allowedVoidTypes(BillingInvoice $invoice): array
    {
        $type = $this->documentType($invoice, $invoice->json_dte ?: []);
        if (in_array($type, ['01', '11'], true)) {
            return [1, 2, 3];
        }

        if ($type === '03') {
            return $this->ccfExceededDirectWindow($invoice) ? [1, 3] : [1, 2, 3];
        }

        if (in_array($type, ['05', '08'], true)) {
            return [2];
        }

        return [1, 3];
    }

    private function validateVoidInput(BillingInvoice $invoice, string $documentType, int $voidType, ?string $replacementCode, string $reason): void
    {
        $allowedTypes = $this->allowedVoidTypes($invoice);
        if (! in_array($voidType, $allowedTypes, true)) {
            throw new RuntimeException("Tipo de anulación inválido para DTE {$documentType}. Use: ".implode(', ', $allowedTypes).'.');
        }

        if ($reason === '') {
            throw new RuntimeException('Ingrese el motivo de anulación.');
        }

        if ($voidType === 2 && ! in_array($documentType, ['01', '03', '05', '08', '11'], true)) {
            throw new RuntimeException("El tipo 2 - Rescindir operación no aplica para DTE tipo {$documentType}.");
        }

        if ($this->requiresReplacementCode($documentType, $voidType) && ! $replacementCode) {
            throw new RuntimeException($documentType === '03'
                ? 'Para anular un CCF debe ingresar el código de generación del CCF corregido que reemplaza al documento.'
                : 'Ingrese el código de generación del DTE que reemplaza al documento a invalidar.');
        }
    }

    private function buildEvent(BillingInvoice $invoice, array $dte, array $emisorConfig, array $options): array
    {
        $now = now();
        $receptor = $dte['receptor'] ?? $dte['sujetoExcluido'] ?? [];
        $receptorDocument = $this->receiverDocument($receptor);
        $emisorDte = $dte['emisor'] ?? [];
        $issuerNit = $this->digits($emisorDte['nit'] ?? $emisorConfig['nit'] ?? '');
        $issuerName = $emisorDte['nombre'] ?? $emisorConfig['nombre_empresa'] ?? $invoice->customer_name ?? 'EMISOR';
        $issuerPhone = $this->textOrNull($emisorDte['telefono'] ?? $emisorConfig['telefono'] ?? null) ?: '00000000';
        $issuerEmail = $this->textOrNull($emisorDte['correo'] ?? $emisorConfig['email'] ?? null) ?: 'correo@emisor.test';
        $documentType = $this->documentType($invoice, $dte);

        return [
            'identificacion' => [
                'version' => 2,
                'ambiente' => data_get($dte, 'identificacion.ambiente', $this->environmentCode($emisorConfig['hacienda_ambiente'] ?? null)),
                'codigoGeneracion' => strtoupper((string) Str::uuid()),
                'fecAnula' => $now->format('Y-m-d'),
                'horAnula' => $now->format('H:i:s'),
            ],
            'emisor' => [
                'nit' => $issuerNit,
                'nombre' => $issuerName,
                'tipoEstablecimiento' => $emisorDte['tipoEstablecimiento'] ?? $emisorConfig['tipo_establecimiento'] ?? '01',
                'nomEstablecimiento' => $emisorDte['nombreComercial'] ?? $emisorConfig['nombre_comercial'] ?? $issuerName,
                'codEstableMH' => $emisorDte['codEstableMH'] ?? null,
                'codEstable' => $emisorDte['codEstable'] ?? $emisorConfig['codigo_establecimiento'] ?? 'M001',
                'codPuntoVentaMH' => $emisorDte['codPuntoVentaMH'] ?? null,
                'codPuntoVenta' => $emisorDte['codPuntoVenta'] ?? $emisorConfig['punto_venta'] ?? 'P001',
                'telefono' => $issuerPhone,
                'correo' => $issuerEmail,
            ],
            'documento' => [
                'tipoDte' => $documentType,
                'codigoGeneracion' => data_get($dte, 'identificacion.codigoGeneracion', $invoice->generation_code),
                'selloRecibido' => $invoice->reception_stamp,
                'numeroControl' => data_get($dte, 'identificacion.numeroControl', $invoice->number_control),
                'fecEmi' => data_get($dte, 'identificacion.fecEmi', optional($invoice->issued_at)->format('Y-m-d')),
                'montoIva' => $this->ivaAmount($dte),
                'codigoGeneracionR' => $options['codigoGeneracionR'] ?? null,
                'tipoDocumento' => $receptorDocument['tipoDocumento'] ?? '36',
                'numDocumento' => (string) ($receptorDocument['numeroDocumento'] ?: $issuerNit),
                'nombre' => $receptor['nombre'] ?? 'CONSUMIDOR FINAL',
                'telefono' => $this->textOrNull($receptor['telefono'] ?? null),
                'correo' => $this->textOrNull($receptor['correo'] ?? null) ?: $issuerEmail,
            ],
            'motivo' => [
                'tipoAnulacion' => (int) $options['tipoAnulacion'],
                'motivoAnulacion' => $options['motivo'],
                'nombreResponsable' => $issuerName,
                'tipDocResponsable' => '36',
                'numDocResponsable' => $issuerNit,
                'nombreSolicita' => $receptor['nombre'] ?? $issuerName,
                'tipDocSolicita' => $receptorDocument['tipoDocumento'] ?? '36',
                'numDocSolicita' => (string) ($receptorDocument['numeroDocumento'] ?: $issuerNit),
            ],
        ];
    }

    private function voidLimit(BillingInvoice $invoice): array
    {
        $issuedAt = Carbon::parse($invoice->issued_at)->startOfDay();
        $type = $this->documentType($invoice, $invoice->json_dte ?: []);

        if ($type === '03') {
            $limit = $issuedAt->copy()->addDay()->endOfDay();

            return [
                'allowed' => now()->lte($limit),
                'message' => 'El CCF solo puede anularse directamente hasta las 23:59 del día siguiente a su emisión. Después de ese plazo debe corregirse mediante Nota de Crédito.',
            ];
        }

        if (in_array($type, ['01', '11', '14'], true)) {
            $limit = $issuedAt->copy()->addDays(90)->endOfDay();

            return [
                'allowed' => now()->lte($limit),
                'message' => 'Este documento solo puede anularse directamente durante 90 días contados desde su fecha de emisión.',
            ];
        }

        return ['allowed' => true, 'message' => null];
    }

    private function ccfExceededDirectWindow(BillingInvoice $invoice): bool
    {
        return $this->documentType($invoice, $invoice->json_dte ?: []) === '03' && ! $this->voidLimit($invoice)['allowed'];
    }

    private function requiresReplacementCode(string $documentType, int $voidType): bool
    {
        if (! in_array($voidType, [1, 3], true)) {
            return false;
        }

        return ! in_array($documentType, ['05', '08'], true);
    }

    private function documentType(BillingInvoice $invoice, array $dte): string
    {
        return str_pad((string) data_get($dte, 'identificacion.tipoDte', $invoice->document_type), 2, '0', STR_PAD_LEFT);
    }

    private function receiverDocument(array $receiver): array
    {
        $hasDocument = array_key_exists('numDocumento', $receiver);
        $hasNit = array_key_exists('nit', $receiver);

        return [
            'tipoDocumento' => $receiver['tipoDocumento'] ?? (! empty($receiver['nit']) ? '36' : null),
            'numeroDocumento' => $hasDocument ? $receiver['numDocumento'] : ($hasNit ? $receiver['nit'] : null),
        ];
    }

    private function ivaAmount(array $dte): float
    {
        $resumen = $dte['resumen'] ?? [];
        $tributos = collect($resumen['tributos'] ?? []);
        $iva = $tributos->firstWhere('codigo', '20');

        return round((float) ($resumen['totalIva'] ?? $iva['valor'] ?? 0), 2);
    }

    private function normalizeGenerationCode(?string $value): ?string
    {
        $code = strtoupper(trim((string) $value));

        return preg_match('/^[0-9A-F]{8}-[0-9A-F]{4}-[1-5][0-9A-F]{3}-[89AB][0-9A-F]{3}-[0-9A-F]{12}$/', $code) ? $code : null;
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
            'ambiente' => $this->environmentCode($environment),
            'usuario' => $input['usuario'] ?? '',
            'password' => $input['password'] ?? '',
        ];
    }

    private function normalizeFirma(array $input): array
    {
        return [
            'nit' => $input['nit'] ?? $input['firmador_usuario'] ?? $input['firmadorUsuario'] ?? '',
            'certificado_path' => $input['certificado_path'] ?? '',
            'password' => $input['password'] ?? $input['certificado_password'] ?? $input['firmador_pin'] ?? '',
        ];
    }

    private function certificateNit(array $firma, array $emisor): string
    {
        return $this->digits($firma['nit'] ?? $emisor['nit'] ?? '');
    }

    private function environmentCode(?string $environment): string
    {
        return in_array($environment, ['produccion', '01'], true) ? '01' : '00';
    }

    private function textOrNull(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text !== '' ? $text : null;
    }

    private function digits(mixed $value): string
    {
        return preg_replace('/\D+/', '', (string) ($value ?? ''));
    }
}
