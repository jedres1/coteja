<?php

namespace App\Services\Billing;

use App\Models\BillingInvoice;
use App\Models\BillingSetting;
use App\Services\DteEngine;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;

class InvoiceDispatchService
{
    public function __construct(private DteEngine $engine) {}

    public function resolveSignSettings(array $input, ?array $settings = null): array
    {
        $settings = $settings ?? BillingSetting::allAsArray();
        $saved    = $settings['firma'] ?? [];
        $raw      = $settings['electron_raw_config'] ?? [];

        return [
            'nit' => $input['nit']
                ?? $input['firmador_usuario']
                ?? $input['firmadorUsuario']
                ?? $saved['nit']
                ?? $saved['firmador_usuario']
                ?? $saved['firmadorUsuario']
                ?? $raw['firmador_usuario']
                ?? '',
            'certificado_path' => $input['certificado_path'] ?? $saved['certificado_path'] ?? $raw['certificado_path'] ?? '',
            'password'         => $input['password']
                ?? $saved['certificado_password']
                ?? $saved['firmador_pin']
                ?? $raw['certificado_password']
                ?? $raw['firmador_pin']
                ?? '',
        ];
    }

    public function resolveHaciendaSettings(array $input, ?array $settings = null): array
    {
        $settings = $settings ?? BillingSetting::allAsArray();
        $saved    = $settings['hacienda'] ?? [];
        $raw      = $settings['electron_raw_config'] ?? [];
        $ambiente = $input['ambiente'] ?? $saved['ambiente'] ?? $raw['hacienda_ambiente'] ?? '00';

        return [
            'ambiente' => $ambiente === 'produccion' ? '01' : ($ambiente === 'pruebas' ? '00' : $ambiente),
            'usuario'  => $input['usuario'] ?? $saved['usuario'] ?? $raw['hacienda_usuario'] ?? '',
            'password' => $input['password'] ?? $saved['password'] ?? $raw['hacienda_password'] ?? '',
        ];
    }

    public function resolveMailSettings(array $input, ?array $settings = null): array
    {
        $settings = $settings ?? BillingSetting::allAsArray();
        $saved    = $settings['correo'] ?? [];
        $raw      = $settings['electron_raw_config'] ?? [];

        return [
            'smtpHost'   => $input['smtpHost']   ?? $saved['smtpHost']   ?? $raw['correo_smtp_host']   ?? 'smtp.gmail.com',
            'smtpPort'   => $input['smtpPort']   ?? $saved['smtpPort']   ?? $raw['correo_smtp_port']   ?? 465,
            'smtpSecure' => $input['smtpSecure'] ?? $saved['smtpSecure'] ?? $raw['correo_smtp_secure'] ?? true,
            'username'   => $input['username']   ?? $saved['username']   ?? $raw['correo_usuario']     ?? '',
            'password'   => $input['password']   ?? $saved['password']   ?? $raw['correo_password']    ?? '',
            'from'       => $input['from']       ?? $saved['from']       ?? $raw['correo_remitente']   ?? '',
            'fromName'   => $input['fromName']   ?? $saved['fromName']   ?? $raw['correo_nombre']      ?? '',
        ];
    }

    public function resolveNit(array $firma, array $emisor): string
    {
        return preg_replace('/\D+/', '', (string) ($firma['nit'] ?? $emisor['nit'] ?? ''));
    }

    public function normalizeSignedDocument(array $signed): ?array
    {
        $documento = $signed['documentoFirmado'] ?? null;

        if (! is_array($documento)) {
            return null;
        }

        if (! empty($documento['firmaMh'])) {
            return $documento;
        }

        if (! empty($signed['firmaMh'])) {
            $documento['firmaMh'] = $signed['firmaMh'];

            return $documento;
        }

        return null;
    }

    /**
     * Firma el DTE proporcionado, actualiza la factura y devuelve el DTE firmado.
     * Lanza RuntimeException si falla la firma.
     */
    public function signDocument(BillingInvoice $invoice, array $dteToSign, array $firma, array $emisor): array
    {
        $signed    = $this->engine->signInternal(
            $dteToSign,
            $this->resolveNit($firma, $emisor),
            $firma['password'],
            $firma['certificado_path'],
        );
        $signedDte = $this->normalizeSignedDocument($signed);

        if (! $signedDte) {
            throw new \RuntimeException($signed['error'] ?? 'El firmador no devolvió un documento con firmaMh.');
        }

        $invoice->update(['status' => 'FIRMADO', 'signed_dte' => $signedDte, 'has_error' => false]);

        return $signedDte;
    }

    /**
     * Envía el DTE firmado a Hacienda, actualiza la factura y devuelve la respuesta.
     * Lanza exception si el envío falla a nivel de red/proceso.
     */
    public function sendToHacienda(
        BillingInvoice $invoice,
        array $dte,
        array $emisor,
        array $hacienda,
        ?string $firmaPassword = null,
    ): array {
        $sent     = $this->engine->send(
            $dte,
            $emisor['nit'] ?? data_get($invoice->json_dte, 'emisor.nit', ''),
            $hacienda,
            $firmaPassword,
        );
        $accepted = (bool) ($sent['success'] ?? false) && ! empty($sent['selloRecibido']);

        $invoice->update([
            'status'           => $accepted ? 'ENVIADO' : 'RECHAZADO',
            'accepted'         => $accepted,
            'has_error'        => ! $accepted,
            'reception_stamp'  => $sent['selloRecibido'] ?? null,
            'observations'     => json_encode($sent, JSON_UNESCAPED_UNICODE),
        ]);

        return ['accepted' => $accepted, 'sent' => $sent];
    }

    /**
     * Envía el PDF y JSON del DTE por correo al cliente.
     * Devuelve un step array para la respuesta de la UI.
     */
    public function sendEmail(
        BillingInvoice $invoice,
        array $dte,
        array $config,
        array $correo,
        array $cliente,
        array $respuestaHacienda,
    ): array {
        $destinatario = trim((string) ($cliente['email'] ?? ''));
        $usuario      = trim((string) ($correo['username'] ?? $correo['from'] ?? ''));
        $password     = (string) ($correo['password'] ?? '');

        if ($destinatario === '') {
            return ['key' => 'email', 'status' => 'warning', 'message' => 'Documento aprobado, pero el cliente no tiene correo registrado.'];
        }

        if ($usuario === '' || $password === '') {
            return ['key' => 'email', 'status' => 'warning', 'message' => 'Documento aprobado, pero falta configurar usuario SMTP y contraseña de aplicación.'];
        }

        $codigo   = $invoice->generation_code ?: $invoice->number_control;
        $safeCode = preg_replace('/[^A-Za-z0-9_-]/', '_', $codigo ?: 'DTE');

        $jsonAdjunto = [
            'dte'                => $dte,
            'respuestaHacienda'  => [
                'estado'         => $invoice->status,
                'selloRecibido'  => $invoice->reception_stamp,
                'observaciones'  => $respuestaHacienda['observaciones'] ?? null,
                'raw'            => $respuestaHacienda,
            ],
        ];

        try {
            $pdf      = $this->engine->pdf(
                [
                    'numero_control'     => $invoice->number_control,
                    'codigo_generacion'  => $invoice->generation_code,
                    'fecha_emision'      => optional($invoice->issued_at)->format('Y-m-d'),
                    'cliente'            => $invoice->customer_name,
                    'subtotal'           => (float) $invoice->subtotal,
                    'iva'                => (float) $invoice->iva,
                    'total'              => (float) $invoice->total,
                    'estado'             => $invoice->status,
                    'sello_recepcion'    => $invoice->reception_stamp,
                ],
                $dte,
                $config,
                "DTE_{$safeCode}.pdf",
            );

            $host     = $correo['smtpHost'] ?? 'smtp.gmail.com';
            $port     = (int) ($correo['smtpPort'] ?? 465);
            $secure   = filter_var($correo['smtpSecure'] ?? true, FILTER_VALIDATE_BOOL);
            $from     = trim((string) ($correo['from'] ?? $usuario));
            $fromName = trim((string) ($correo['fromName'] ?? $config['nombre_empresa'] ?? $from));

            Config::set([
                'mail.mailers.billing_smtp' => [
                    'transport'    => 'smtp',
                    'scheme'       => $secure || $port === 465 ? 'smtps' : null,
                    'host'         => $host,
                    'port'         => $port,
                    'username'     => $usuario,
                    'password'     => $password,
                    'timeout'      => null,
                    'local_domain' => parse_url((string) config('app.url'), PHP_URL_HOST),
                ],
                'mail.from.address' => $from,
                'mail.from.name'    => $fromName,
            ]);

            Mail::mailer('billing_smtp')->raw(
                "Estimado cliente,\n\nAdjunto encontrara el PDF y JSON del Documento Tributario Electronico {$codigo}.\n\nSaludos.",
                function ($message) use ($destinatario, $from, $fromName, $codigo, $pdf, $jsonAdjunto, $safeCode) {
                    $message
                        ->from($from, $fromName)
                        ->to($destinatario)
                        ->subject("Documento Tributario Electronico {$codigo}");

                    $message->attachData(base64_decode($pdf['base64'] ?? ''), "DTE_{$safeCode}.pdf", ['mime' => 'application/pdf']);
                    $message->attachData(json_encode($jsonAdjunto, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), "DTE_{$safeCode}.json", ['mime' => 'application/json']);
                }
            );

            $invoice->update(['email_sent' => true]);

            return ['key' => 'email', 'status' => 'done', 'message' => 'PDF y JSON enviados al correo del cliente.'];
        } catch (\Throwable $e) {
            return ['key' => 'email', 'status' => 'warning', 'message' => 'Documento aprobado por Hacienda, pero no se pudo enviar el correo: ' . $e->getMessage()];
        }
    }
}
