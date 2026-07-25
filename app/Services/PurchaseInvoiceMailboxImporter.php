<?php

namespace App\Services;

use App\Models\PurchaseInvoice;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PurchaseInvoiceMailboxImporter
{
    private $socket = null;

    private int $tagCounter = 1;

    public function import(?string $from = null, ?string $to = null): array
    {
        $config = config('services.purchase_invoice_mailbox');

        if (blank($config['username']) || blank($config['password'])) {
            throw new RuntimeException('Configure PURCHASE_INVOICE_MAIL_USERNAME y PURCHASE_INVOICE_MAIL_PASSWORD para extraer facturas.');
        }

        $summary = [
            'messages' => 0,
            'attachments' => 0,
            'imported' => 0,
            'duplicates' => 0,
            'errors' => [],
            'details' => [],
        ];

        $this->connect($config);

        try {
            $this->command('LOGIN '.$this->quote($config['username']).' '.$this->quote($config['password']));
            $this->command('SELECT '.$this->quote($config['mailbox'] ?: 'INBOX'));

            $queryTokens = [];
            if ($config['only_unseen']) {
                $queryTokens[] = 'UNSEEN';
            }

            if ($fromDate = $this->imapDate($from)) {
                $queryTokens[] = 'SINCE '.$fromDate;
            }

            if ($toDate = $this->imapDate($to)) {
                $queryTokens[] = 'BEFORE '.$this->imapDatePlusOneDay($toDate);
            }

            $search = empty($queryTokens) ? 'ALL' : implode(' ', $queryTokens);
            $uids = $this->parseSearchUids($this->command('UID SEARCH '.$search));
            $uids = array_slice(array_reverse($uids), 0, max(1, (int) $config['limit']));

            foreach ($uids as $uid) {
                $summary['messages']++;

                try {
                    $message = $this->fetchMessage($uid);
                    $jsonAttachments = $this->jsonAttachments($message);
                    $summary['attachments'] += count($jsonAttachments);

                    $messageImported = false;

                    foreach ($jsonAttachments as $attachment) {
                        $result = $this->importJson($attachment['content'], $attachment['filename'], $uid, $summary);
                        $summary[$result]++;
                        $messageImported = $messageImported || $result === 'imported';
                    }

                    if ($messageImported) {
                        $this->command('UID STORE '.$uid.' +FLAGS (\Seen)');
                    }
                } catch (\Throwable $exception) {
                    $summary['errors'][] = "Correo UID {$uid}: ".$exception->getMessage();
                }
            }
        } finally {
            try {
                $this->command('LOGOUT');
            } catch (\Throwable) {
                //
            }

            if (is_resource($this->socket)) {
                fclose($this->socket);
            }
        }

        return $summary;
    }

    public function inspect(?string $from = null, ?string $to = null): array
    {
        $config = config('services.purchase_invoice_mailbox');

        if (blank($config['username']) || blank($config['password'])) {
            throw new RuntimeException('Configure PURCHASE_INVOICE_MAIL_USERNAME y PURCHASE_INVOICE_MAIL_PASSWORD para extraer facturas.');
        }

        $details = [
            'messages' => 0,
            'attachments' => 0,
            'files' => [],
        ];

        $this->connect($config);

        try {
            $this->command('LOGIN '.$this->quote($config['username']).' '.$this->quote($config['password']));
            $this->command('SELECT '.$this->quote($config['mailbox'] ?: 'INBOX'));

            $queryTokens = [];
            if ($fromDate = $this->imapDate($from)) {
                $queryTokens[] = 'SINCE '.$fromDate;
            }

            if ($toDate = $this->imapDate($to)) {
                $queryTokens[] = 'BEFORE '.$this->imapDatePlusOneDay($toDate);
            }

            $search = empty($queryTokens) ? 'ALL' : implode(' ', $queryTokens);
            $uids = $this->parseSearchUids($this->command('UID SEARCH '.$search));
            $uids = array_slice(array_reverse($uids), 0, max(1, (int) $config['limit']));

            foreach ($uids as $uid) {
                $details['messages']++;

                try {
                    $message = $this->fetchMessage($uid);
                    $jsonAttachments = $this->jsonAttachments($message);
                    $details['attachments'] += count($jsonAttachments);

                    foreach ($jsonAttachments as $attachment) {
                        $this->inspectJson($attachment['content'], $attachment['filename'], $details);
                    }
                } catch (\Throwable $exception) {
                    $details['files'][] = [
                        'filename' => "Error UID {$uid}",
                        'supplier' => 'N/A',
                        'numeroControl' => null,
                        'codigoGeneracion' => null,
                        'status' => 'error',
                        'message' => $exception->getMessage(),
                    ];
                }
            }
        } finally {
            try {
                $this->command('LOGOUT');
            } catch (\Throwable) {
                //
            }

            if (is_resource($this->socket)) {
                fclose($this->socket);
            }
        }

        return $details;
    }

    public function hasDuplicateInvoice(int|string $supplierId, string $invoiceNumber): bool
    {
        return PurchaseInvoice::query()
            ->where('supplier_id', $supplierId)
            ->where('invoice_number', $invoiceNumber)
            ->exists();
    }

    private function inspectJson(string $content, string $filename, array &$details): void
    {
        try {
            $data = json_decode($this->cleanJsonContent($content), true, flags: JSON_THROW_ON_ERROR);
            $identification = $data['identificacion'] ?? [];
            $issuer = $data['emisor'] ?? [];

            $numeroControl = $identification['numeroControl'] ?? null;
            $codigoGeneracion = $identification['codigoGeneracion'] ?? null;
            $invoiceNumber = $numeroControl ?? $codigoGeneracion ?? null;
            $supplierName = $issuer['nombre'] ?? null;

            if (blank($invoiceNumber) || blank($supplierName)) {
                $details['files'][] = [
                    'filename' => $filename,
                    'supplier' => $supplierName ?? 'DESCONOCIDO',
                    'numeroControl' => $numeroControl,
                    'codigoGeneracion' => $codigoGeneracion,
                    'status' => 'error',
                    'message' => 'Falta numeroControl o nombre de emisor',
                ];
                return;
            }

            $supplier = $this->supplierFromIssuer($issuer);
            $isDuplicate = $this->hasDuplicateInvoice($supplier->id, $invoiceNumber);

            $details['files'][] = [
                'filename' => $filename,
                'supplier' => $supplierName,
                'numeroControl' => $numeroControl,
                'codigoGeneracion' => $codigoGeneracion,
                'status' => $isDuplicate ? 'duplicate' : 'ok',
                'message' => $isDuplicate ? "Ya existe en sistema (proveedor: {$supplier->name})" : null,
            ];
        } catch (\Throwable $exception) {
            $details['files'][] = [
                'filename' => $filename,
                'supplier' => 'ERROR',
                'numeroControl' => null,
                'codigoGeneracion' => null,
                'status' => 'error',
                'message' => $exception->getMessage(),
            ];
        }
    }

    private function importJson(string $content, string $filename, string $uid, array &$summary): string
    {
        $data = json_decode($this->cleanJsonContent($content), true, 512, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
        $identification = $data['identificacion'] ?? [];
        $issuer = $data['emisor'] ?? [];
        $summary_data = $data['resumen'] ?? [];

        $numeroControl = $identification['numeroControl'] ?? null;
        $codigoGeneracion = $identification['codigoGeneracion'] ?? null;
        $invoiceNumber = $numeroControl ?? $codigoGeneracion ?? null;
        $supplierName = $issuer['nombre'] ?? null;

        if (blank($invoiceNumber) || blank($supplierName)) {
            throw new RuntimeException("El adjunto {$filename} no contiene identificacion.numeroControl o emisor.nombre.");
        }

        return DB::transaction(function () use ($data, $identification, $issuer, $summary_data, $invoiceNumber, $numeroControl, $codigoGeneracion, $supplierName, $filename, $uid, &$summary) {
            $supplier = $this->supplierFromIssuer($issuer);

            if ($this->hasDuplicateInvoice($supplier->id, $invoiceNumber)) {
                $summary['details'][] = [
                    'filename' => $filename,
                    'supplier' => $supplierName,
                    'numeroControl' => $numeroControl,
                    'codigoGeneracion' => $codigoGeneracion,
                    'status' => 'DUPLICADA',
                    'razon' => "Ya existe en el sistema (ID proveedor: {$supplier->id})"
                ];
                return 'duplicates';
            }

            try {
                PurchaseInvoice::create([
                    'supplier_id' => $supplier->id,
                    'customer_id' => null,
                    'document_type' => $this->documentType($identification['tipoDte'] ?? null),
                    'invoice_number' => $invoiceNumber,
                    'purchase_date' => $identification['fecEmi'] ?? now()->toDateString(),
                    'due_date' => null,
                    'subtotal' => $this->money($summary_data['subTotal'] ?? $summary_data['totalGravada'] ?? 0),
                    'iva' => $this->money($summary_data['totalIva'] ?? $summary_data['ivaPerci1'] ?? 0),
                    'total' => $this->money($summary_data['montoTotalOperacion'] ?? $summary_data['totalPagar'] ?? 0),
                    'payment_method' => $this->paymentMethod($summary_data['pagos'][0]['codigo'] ?? null),
                    'payment_status' => 'pending',
                    'status' => 'registered',
                    'notes' => trim(sprintf(
                        "Extraida de correo %s. Archivo: %s. Codigo generacion: %s.",
                        $uid,
                        $filename,
                        $codigoGeneracion ?? 'N/D'
                    )),
                    'extracted_document_body' => json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]);
                
                $summary['details'][] = [
                    'filename' => $filename,
                    'supplier' => $supplierName,
                    'numeroControl' => $numeroControl,
                    'codigoGeneracion' => $codigoGeneracion,
                    'status' => 'IMPORTADA',
                    'razon' => null
                ];
            } catch (\Illuminate\Database\QueryException $exception) {
                if ($this->isDuplicateException($exception)) {
                    $summary['details'][] = [
                        'filename' => $filename,
                        'supplier' => $supplierName,
                        'numeroControl' => $numeroControl,
                        'codigoGeneracion' => $codigoGeneracion,
                        'status' => 'DUPLICADA',
                        'razon' => "Violación de restricción única en BD"
                    ];
                    return 'duplicates';
                }

                throw $exception;
            }

            return 'imported';
        });
    }

    private function isDuplicateException(\Illuminate\Database\QueryException $exception): bool
    {
        $code = (string) $exception->getCode();
        $message = strtolower($exception->getMessage());

        return in_array($code, ['23000', '23505', '1062'], true)
            || str_contains($message, 'unique')
            || str_contains($message, 'duplicate');
    }

    private function supplierFromIssuer(array $issuer): Supplier
    {
        $documentNumber = $issuer['nit'] ?? $issuer['numDocumento'] ?? $issuer['nrc'] ?? null;

        $supplier = null;

        if (! blank($documentNumber)) {
            $supplier = Supplier::where('document_number', $documentNumber)->first();
        } elseif (! blank($issuer['correo'] ?? null)) {
            $supplier = Supplier::where('email', $issuer['correo'])->first();
        }

        $address = $issuer['direccion'] ?? [];

        $attributes = [
            'name' => $issuer['nombre'] ?? 'Proveedor sin nombre',
            'email' => $issuer['correo'] ?? null,
            'phone' => $issuer['telefono'] ?? null,
            'document_type' => ($issuer['nit'] ?? null) ? '36' : '13',
            'document_number' => $documentNumber ?: 'SIN-DOCUMENTO-'.substr(md5($issuer['nombre'] ?? microtime()), 0, 10),
            'nrc' => $issuer['nrc'] ?? null,
            'trade_name' => $issuer['nombreComercial'] ?? $issuer['nombre'] ?? null,
            'business_activity' => $issuer['codActividad'] ?? null,
            'activity_description' => $issuer['descActividad'] ?? null,
            'address_department' => $this->fixedCode($address['departamento'] ?? null, 2, '06'),
            'address_municipality' => $this->municipalityCode($address['departamento'] ?? null, $address['municipio'] ?? null),
            'address' => mb_substr($address['complemento'] ?? 'Direccion no indicada en DTE', 0, 500),
            'billing_email' => $issuer['correo'] ?? null,
            'billing_phone' => $issuer['telefono'] ?? null,
            'status' => 'active',
        ];

        if ($supplier) {
            $supplier->fill(array_filter($attributes, fn ($value) => ! blank($value)));
            $supplier->save();

            return $supplier;
        }

        return Supplier::create($attributes);
    }

    private function connect(array $config): void
    {
        $target = sprintf('ssl://%s:%d', $config['host'], $config['port']);
        $this->socket = @stream_socket_client($target, $errno, $error, 30);

        if (! is_resource($this->socket)) {
            throw new RuntimeException("No se pudo conectar al correo: {$error}");
        }

        stream_set_timeout($this->socket, 30);
        fgets($this->socket);
    }

    private function command(string $command): string
    {
        $tag = 'A'.str_pad((string) $this->tagCounter++, 4, '0', STR_PAD_LEFT);

        fwrite($this->socket, "{$tag} {$command}\r\n");

        $response = '';

        while (($line = fgets($this->socket)) !== false) {
            $response .= $line;

            if (preg_match('/\{(\d+)\}\r\n$/', $line, $matches)) {
                $literal = stream_get_contents($this->socket, (int) $matches[1]);
                $response .= $literal;
            }

            if (str_starts_with($line, $tag.' ')) {
                if (! str_contains($line, ' OK')) {
                    throw new RuntimeException($this->friendlyServerError(trim($line)));
                }

                return $response;
            }
        }

        throw new RuntimeException('Respuesta incompleta del servidor de correo.');
    }

    private function fetchMessage(string $uid): string
    {
        $response = $this->command('UID FETCH '.$uid.' (BODY.PEEK[])');

        if (preg_match('/\{(\d+)\}\r\n/', $response, $matches, PREG_OFFSET_CAPTURE)) {
            $size  = (int) $matches[1][0];
            $start = $matches[0][1] + strlen($matches[0][0]);

            return substr($response, $start, $size);
        }

        throw new RuntimeException('No se pudo leer el contenido del mensaje.');
    }

    private function jsonAttachments(string $message): array
    {
        return array_values(array_filter(
            $this->extractParts($message),
            fn ($part) => ($part['filename'] && str_ends_with(strtolower($part['filename']), '.json'))
                || str_contains(strtolower($part['content_type']), 'json')
        ));
    }

    private function extractParts(string $raw): array
    {
        [$headers, $body] = $this->splitHeadersBody($raw);
        $headers = $this->headers($headers);
        $contentType = strtolower($headers['content-type'] ?? 'text/plain');
        $boundary = $this->headerParameter($headers['content-type'] ?? '', 'boundary');

        if ($boundary) {
            $parts = [];
            foreach ($this->splitMultipart($body, $boundary) as $section) {
                $parts = array_merge($parts, $this->extractParts($section));
            }

            return $parts;
        }

        $filename = $this->headerParameter($headers['content-disposition'] ?? '', 'filename')
            ?: $this->headerParameter($headers['content-type'] ?? '', 'name');

        $decoded = $this->decodeBody($body, strtolower($headers['content-transfer-encoding'] ?? ''));

        return [[
            'filename' => $filename ? $this->decodeMimeHeader($filename) : null,
            'content_type' => $contentType,
            'content' => $decoded,
        ]];
    }

    private function splitHeadersBody(string $raw): array
    {
        $position = strpos($raw, "\r\n\r\n");

        if ($position === false) {
            $position = strpos($raw, "\n\n");
            $separator = 2;
        } else {
            $separator = 4;
        }

        if ($position === false) {
            return [$raw, ''];
        }

        return [substr($raw, 0, $position), substr($raw, $position + $separator)];
    }

    private function headers(string $raw): array
    {
        $raw = preg_replace("/\r?\n[ \t]+/", ' ', $raw);
        $headers = [];

        foreach (preg_split("/\r?\n/", trim($raw)) as $line) {
            if (! str_contains($line, ':')) {
                continue;
            }

            [$name, $value] = explode(':', $line, 2);
            $headers[strtolower(trim($name))] = trim($value);
        }

        return $headers;
    }

    private function splitMultipart(string $body, string $boundary): array
    {
        $chunks = preg_split('/\r?\n--'.preg_quote($boundary, '/').'(--)?\r?\n/', "\r\n".$body);

        return array_values(array_filter($chunks, fn ($chunk) => trim($chunk) !== ''));
    }

    private function decodeBody(string $body, string $encoding): string
    {
        return match ($encoding) {
            'base64' => base64_decode(preg_replace('/\s+/', '', $body), true) ?: '',
            'quoted-printable' => quoted_printable_decode($body),
            default => trim($body),
        };
    }

    private function headerParameter(string $header, string $parameter): ?string
    {
        if (preg_match('/(?:^|;)\s*'.preg_quote($parameter, '/').'\*?=(?:"([^"]+)"|([^;]+))/i', $header, $matches)) {
            return trim($matches[1] ?: $matches[2]);
        }

        return null;
    }

    private function decodeMimeHeader(string $value): string
    {
        if (function_exists('mb_decode_mimeheader')) {
            return mb_decode_mimeheader($value);
        }

        return $value;
    }

    private function cleanJsonContent(string $content): string
    {
        $content = trim($content);
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        $content = mb_convert_encoding($content, 'UTF-8', 'UTF-8');

        json_decode($content);

        if (json_last_error() === JSON_ERROR_NONE) {
            return $content;
        }

        $objectStart = strpos($content, '{');
        $objectEnd = strrpos($content, '}');

        if ($objectStart !== false && $objectEnd !== false && $objectEnd > $objectStart) {
            $candidate = substr($content, $objectStart, $objectEnd - $objectStart + 1);
            json_decode($candidate);

            if (json_last_error() === JSON_ERROR_NONE) {
                return $candidate;
            }
        }

        $arrayStart = strpos($content, '[');
        $arrayEnd = strrpos($content, ']');

        if ($arrayStart !== false && $arrayEnd !== false && $arrayEnd > $arrayStart) {
            $candidate = substr($content, $arrayStart, $arrayEnd - $arrayStart + 1);
            json_decode($candidate);

            if (json_last_error() === JSON_ERROR_NONE) {
                return $candidate;
            }
        }

        return $content;
    }

    private function parseSearchUids(string $response): array
    {
        if (! preg_match('/\* SEARCH\s*(.*)\r?\n/i', $response, $matches)) {
            return [];
        }

        return array_values(array_filter(preg_split('/\s+/', trim($matches[1]))));
    }

    private function quote(string $value): string
    {
        return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
    }

    private function friendlyServerError(string $message): string
    {
        if (str_contains($message, 'AUTHENTICATE failed')) {
            return 'Outlook rechazo el acceso IMAP. Revise que el buzon tenga IMAP habilitado y use una clave de aplicacion valida en PURCHASE_INVOICE_MAIL_PASSWORD.';
        }

        return $message;
    }

    private function documentType(?string $value): string
    {
        if (in_array($value, ['01', '03', '05', '06', '11', '14'], true)) {
            return $value;
        }

        \Illuminate\Support\Facades\Log::warning("PurchaseInvoiceMailboxImporter: tipoDte desconocido '{$value}', se almacena como '99'.");

        return '99';
    }

    private function imapDate(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        try {
            $date = new \DateTimeImmutable($value);
        } catch (\Throwable $exception) {
            return null;
        }

        return $date->format('j-M-Y');
    }

    private function imapDatePlusOneDay(string $dateString): string
    {
        $date = new \DateTimeImmutable($dateString);

        return $date->modify('+1 day')->format('j-M-Y');
    }

    private function paymentMethod(?string $code): ?string
    {
        return match ($code) {
            '01' => 'Efectivo',
            '02' => 'Tarjeta debito',
            '03' => 'Tarjeta credito',
            '05' => 'Transferencia',
            default => $code,
        };
    }

    private function money(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    private function fixedCode(?string $value, int $length, string $fallback): string
    {
        $value = preg_replace('/\D+/', '', (string) $value);

        if ($value === '') {
            return $fallback;
        }

        return str_pad(substr($value, 0, $length), $length, '0', STR_PAD_LEFT);
    }

    private function municipalityCode(?string $department, ?string $municipality): string
    {
        $municipality = preg_replace('/\D+/', '', (string) $municipality);

        if ($municipality === '') {
            return '0601';
        }

        if (strlen($municipality) >= 4) {
            return substr($municipality, 0, 4);
        }

        return $this->fixedCode($department, 2, '06').str_pad($municipality, 2, '0', STR_PAD_LEFT);
    }
}
