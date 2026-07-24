<?php

namespace App\Console\Commands;

use App\Services\PurchaseInvoiceMailboxImporter;
use Illuminate\Console\Command;

class InspectPurchaseInvoiceMails extends Command
{
    protected $signature = 'mails:inspect {--from=} {--to=}';

    protected $description = 'Inspecciona correos de facturas sin importar';

    public function __construct(
        private readonly PurchaseInvoiceMailboxImporter $importer
    ) {
        parent::__construct();
    }

    public function handle()
    {
        $from = $this->option('from') ?: now()->toDateString();
        $to = $this->option('to') ?: now()->toDateString();

        $this->info("Buscando correos entre {$from} y {$to}...\n");

        $details = $this->importer->inspect($from, $to);

        $this->info("Correos encontrados: {$details['messages']}");
        $this->info("Archivos JSON: {$details['attachments']}\n");

        foreach ($details['files'] as $file) {
            $status = $file['status'] === 'ok' ? '✓' : '✗';
            $numeroControl = $file['numeroControl'] ?? 'N/A';
            $codigoGeneracion = $file['codigoGeneracion'] ?? 'N/A';
            $this->line("{$status} {$file['filename']}");
            $this->line("   Proveedor: {$file['supplier']}");
            $this->line("   NumControl: {$numeroControl}");
            $this->line("   CodGen: {$codigoGeneracion}");
            if ($file['message']) {
                $this->line("   ⚠ {$file['message']}");
            }
            $this->line('');
        }

        return Command::SUCCESS;
    }
}
