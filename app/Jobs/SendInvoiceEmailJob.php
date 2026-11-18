<?php

namespace App\Jobs;

use App\Models\BillingInvoice;
use App\Models\BillingSetting;
use App\Services\Billing\InvoicePdfService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendInvoiceEmailJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly int $invoiceId, public readonly ?string $recipient = null) {}

    public function handle(InvoicePdfService $pdfService): void
    {
        $invoice = BillingInvoice::with(['customer', 'billingClient'])->find($this->invoiceId);

        if (! $invoice || ! $invoice->accepted || ! $invoice->reception_stamp) {
            return;
        }

        $settings = BillingSetting::allAsArray($invoice->company_id ?? $invoice->tenant_customer_id);
        $correo   = $settings['correo'] ?? [];
        $emisor   = $settings['emisor'] ?? [];
        $dte      = $invoice->signed_dte ?: $invoice->json_dte;

        $destinatario = trim((string) ($this->recipient
            ?: $invoice->billingClient?->billing_email
            ?: $invoice->billingClient?->email
            ?: $invoice->customer?->billing_email
            ?: $invoice->customer?->email
            ?: data_get($invoice->json_dte, 'receptor.correo')
            ?: data_get($invoice->json_dte, 'sujetoExcluido.correo')
            ?: ''));

        $usuario  = trim((string) ($correo['username'] ?? $correo['from'] ?? ''));
        $password = (string) ($correo['password'] ?? '');

        if ($destinatario === '' || $usuario === '' || $password === '') {
            return;
        }

        $codigo   = $invoice->generation_code ?: $invoice->number_control;
        $safeCode = preg_replace('/[^A-Za-z0-9_-]/', '_', $codigo ?: 'DTE');

        $pdfResponse = $pdfService->generate($invoice, $settings);

        $jsonAdjunto = [
            'dte' => $dte,
            'respuestaHacienda' => [
                'estado'         => $invoice->status,
                'selloRecibido'  => $invoice->reception_stamp,
            ],
        ];

        $host     = $correo['smtpHost'] ?? 'smtp.gmail.com';
        $port     = (int) ($correo['smtpPort'] ?? 465);
        $secure   = filter_var($correo['smtpSecure'] ?? true, FILTER_VALIDATE_BOOL);
        $from     = trim((string) ($correo['from'] ?? $usuario));
        $fromName = trim((string) ($correo['fromName'] ?? $emisor['nombre_empresa'] ?? $from));

        config([
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
            function ($message) use ($destinatario, $from, $fromName, $codigo, $pdfResponse, $jsonAdjunto, $safeCode) {
                $message
                    ->from($from, $fromName)
                    ->to($destinatario)
                    ->subject("Documento Tributario Electronico {$codigo}");

                $message->attachData(base64_decode($pdfResponse['base64'] ?? ''), "DTE_{$safeCode}.pdf", ['mime' => 'application/pdf']);
                $message->attachData(json_encode($jsonAdjunto, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), "DTE_{$safeCode}.json", ['mime' => 'application/json']);
            }
        );

        $invoice->update(['email_sent' => true]);
    }
}
