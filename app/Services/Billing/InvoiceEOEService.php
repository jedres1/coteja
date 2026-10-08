<?php

namespace App\Services\Billing;

use App\Services\DteEngine;
use RuntimeException;

class InvoiceEOEService
{
    public function __construct(private readonly DteEngine $engine) {}

    public function process(array $settings, array $input): array
    {
        $detalle = $input['detalle'] ?? [];
        $resumen = $input['resumen'] ?? [];
        $receptor = $input['receptor'] ?? null;

        if (empty($detalle)) {
            throw new RuntimeException('Debe incluir al menos una operación en el detalle.');
        }

        $config = $settings['emisor'] ?? [];

        $generated = $this->engine->generateEOE($config, $detalle, $resumen, $receptor);
        $eoe = $generated['eoe'] ?? null;

        if (! $eoe) {
            throw new RuntimeException($generated['validation']['errores'][0] ?? 'No se pudo generar el EOE.');
        }

        $validation = $generated['validation'] ?? [];
        if (! ($validation['valido'] ?? false)) {
            throw new RuntimeException('EOE no cumple el schema: '.implode(' | ', array_slice($validation['errores'] ?? [], 0, 8)));
        }

        $firma = $this->normalizeFirma($settings['firma'] ?? []);
        if (empty($firma['certificado_path']) || empty($firma['password'])) {
            throw new RuntimeException('Falta certificado o contraseña para firmar el EOE.');
        }

        $signed = $this->engine->signInternal(
            $eoe,
            $this->certificateNit($firma, $settings['emisor'] ?? []),
            $firma['password'],
            $firma['certificado_path'],
        );

        $signedEoe = $this->normalizeSignedDocument($signed);
        if (! $signedEoe) {
            throw new RuntimeException($signed['error'] ?? 'El firmador no devolvió un EOE con firmaMh.');
        }

        $signedValidation = $this->engine->validateEvent('eoe', $signedEoe);
        if (! ($signedValidation['validation']['valido'] ?? false)) {
            throw new RuntimeException('EOE firmado no cumple el schema: '.implode(' | ', array_slice($signedValidation['validation']['errores'] ?? [], 0, 8)));
        }

        $hacienda = $this->normalizeHacienda($settings['hacienda'] ?? []);
        if (empty($hacienda['usuario']) || empty($hacienda['password'])) {
            throw new RuntimeException('Faltan credenciales de Hacienda para enviar el EOE.');
        }

        $nit = preg_replace('/\D+/', '', $config['nit'] ?? '');
        $response = $this->engine->sendEOE($signedEoe, $hacienda, $nit);

        if (! ($response['success'] ?? false)) {
            return [
                'success'    => false,
                'eoe'        => $eoe,
                'signedEoe'  => $signedEoe,
                'response'   => $response,
                'message'    => $response['error'] ?? 'Hacienda rechazó el EOE.',
            ];
        }

        return [
            'success'   => true,
            'eoe'       => $eoe,
            'signedEoe' => $signedEoe,
            'response'  => $response,
            'message'   => 'EOE enviado exitosamente a Hacienda.',
        ];
    }

    private function normalizeSignedDocument(array $signed): ?array
    {
        $document = $signed['documentoFirmado'] ?? null;
        if (! is_array($document)) return null;
        if (! empty($document['firmaMh'])) return $document;
        if (! empty($signed['firmaMh'])) { $document['firmaMh'] = $signed['firmaMh']; return $document; }
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
