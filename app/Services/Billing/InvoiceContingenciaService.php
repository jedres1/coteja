<?php

namespace App\Services\Billing;

use App\Models\BillingInvoice;
use App\Services\DteEngine;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use RuntimeException;

class InvoiceContingenciaService
{
    public function __construct(private readonly DteEngine $engine) {}

    public function process(Collection $invoices, array $settings, array $input): array
    {
        foreach ($invoices as $invoice) {
            if (! $invoice->signed_dte) {
                throw new RuntimeException("Factura #{$invoice->id} ({$invoice->number_control}) no tiene documento firmado. Fírmela antes de enviar contingencia.");
            }
        }

        $event = $this->buildEvent($invoices, $settings, $input);

        $validation = $this->engine->validateEvent('contingencia', $event);
        $validationResult = $validation['validation'] ?? [];
        if (! ($validationResult['valido'] ?? false)) {
            throw new RuntimeException('Evento de contingencia no cumple el schema: '.implode(' | ', array_slice($validationResult['errores'] ?? [], 0, 8)));
        }

        $firma = $this->normalizeFirma($settings['firma'] ?? []);
        if (empty($firma['certificado_path']) || empty($firma['password'])) {
            throw new RuntimeException('Falta certificado o contraseña para firmar el evento de contingencia.');
        }

        $nit = $this->digits($settings['emisor']['nit'] ?? '');

        $signed = $this->engine->signInternal(
            $event,
            $this->certificateNit($firma, $settings['emisor'] ?? []),
            $firma['password'],
            $firma['certificado_path'],
        );

        $signedEvent = $this->normalizeSignedDocument($signed);
        if (! $signedEvent) {
            throw new RuntimeException($signed['error'] ?? 'El firmador no devolvió un evento de contingencia con firmaMh.');
        }

        $signedValidation = $this->engine->validateEvent('contingencia', $signedEvent);
        if (! ($signedValidation['validation']['valido'] ?? false)) {
            throw new RuntimeException('Evento firmado de contingencia no cumple el schema: '.implode(' | ', array_slice($signedValidation['validation']['errores'] ?? [], 0, 8)));
        }

        $hacienda = $this->normalizeHacienda($settings['hacienda'] ?? []);
        if (empty($hacienda['usuario']) || empty($hacienda['password'])) {
            throw new RuntimeException('Faltan credenciales de Hacienda para enviar la contingencia.');
        }

        $dtes = $invoices->map(fn (BillingInvoice $inv) => $inv->signed_dte)->values()->all();
        $batchResult = $this->engine->sendBatch($dtes, $nit, $hacienda);

        $contingenciaResult = $this->engine->sendContingency($signedEvent, $nit, $hacienda);

        $batchOk = (bool) ($batchResult['success'] ?? false);
        $contingenciaOk = (bool) ($contingenciaResult['success'] ?? false);

        if ($batchOk) {
            foreach ($invoices as $invoice) {
                $invoice->update([
                    'status'          => 'ENVIADO',
                    'accepted'        => true,
                    'has_error'       => false,
                    'reception_stamp' => $batchResult['selloRecibido'] ?? null,
                    'observations'    => json_encode($batchResult['raw'] ?? $batchResult, JSON_UNESCAPED_UNICODE),
                ]);
            }
        }

        $success = $batchOk && $contingenciaOk;

        return [
            'success'          => $success,
            'event'            => $event,
            'signedEvent'      => $signedEvent,
            'batchResult'      => $batchResult,
            'contingenciaResult' => $contingenciaResult,
            'message'          => $success
                ? 'Lote y evento de contingencia enviados exitosamente a Hacienda.'
                : ($batchOk
                    ? 'Lote enviado, pero hubo un error al enviar el evento de contingencia: '.($contingenciaResult['error'] ?? 'Error desconocido.')
                    : 'Hacienda rechazó el lote de contingencia: '.($batchResult['error'] ?? 'Error desconocido.')),
        ];
    }

    private function buildEvent(Collection $invoices, array $settings, array $input): array
    {
        $emisorConfig = $settings['emisor'] ?? [];
        $now = now();

        $detalleDTE = $invoices->values()->map(fn (BillingInvoice $inv, int $idx) => [
            'noItem'           => $idx + 1,
            'codigoGeneracion' => strtoupper((string) ($inv->generation_code ?? '')),
            'tipoDoc'          => str_pad((string) ($inv->document_type ?? '01'), 2, '0', STR_PAD_LEFT),
        ])->all();

        $ambiente = $this->environmentCode($emisorConfig['hacienda_ambiente'] ?? null);
        $issuerNit = $this->digits($emisorConfig['nit'] ?? '');
        $issuerName = $emisorConfig['nombre_empresa'] ?? 'EMISOR';
        $responsable = $input['emisor'] ?? [];

        return [
            'identificacion' => [
                'version'          => 3,
                'ambiente'         => $ambiente,
                'codigoGeneracion' => strtoupper((string) Str::uuid()),
                'fTransmision'     => $now->format('Y-m-d'),
                'hTransmision'     => $now->format('H:i:s'),
            ],
            'emisor' => [
                'nit'                  => $issuerNit,
                'nombre'               => $issuerName,
                'nombreResponsable'    => $responsable['nombreResponsable'] ?? $issuerName,
                'tipoDocResponsable'   => $responsable['tipoDocResponsable'] ?? '36',
                'numeroDocResponsable' => $responsable['numeroDocResponsable'] ?? $issuerNit,
                'tipoEstablecimiento'  => $emisorConfig['tipo_establecimiento'] ?? '01',
                'codEstableMH'         => $emisorConfig['cod_estable_mh'] ?? null,
                'codPuntoVenta'        => $emisorConfig['punto_venta'] ?? null,
                'telefono'             => $this->textOrNull($emisorConfig['telefono'] ?? null) ?? '00000000',
                'correo'               => $emisorConfig['email'] ?? '',
            ],
            'detalleDTE' => $detalleDTE,
            'motivo' => [
                'fInicio'             => $input['motivo']['fInicio'],
                'fFin'                => $input['motivo']['fFin'],
                'hInicio'             => $input['motivo']['hInicio'],
                'hFin'                => $input['motivo']['hFin'],
                'tipoContingencia'    => (int) ($input['motivo']['tipoContingencia']),
                'motivoContingencia'  => $this->textOrNull($input['motivo']['motivoContingencia'] ?? null),
            ],
        ];
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
