<?php

namespace App\Jobs;

use App\Services\PurchaseInvoiceMailboxImporter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

class ImportSelectedCompanyInvoicesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public readonly string $extractionId) {}

    public function handle(PurchaseInvoiceMailboxImporter $importer): void
    {
        $run = DB::table('purchase_invoice_extractions')->where('id', $this->extractionId)->first();
        if (! $run) return;

        DB::table('purchase_invoice_extractions')->where('id', $this->extractionId)->update([
            'status' => 'running', 'updated_at' => now(),
        ]);

        try {
            $summary = $importer->import(
                $run->date_from,
                $run->date_to,
                (int) $run->company_id,
                function (array $progress): void {
                    DB::table('purchase_invoice_extractions')->where('id', $this->extractionId)->update([
                        'total_messages' => $progress['total'],
                        'reviewed' => $progress['reviewed'],
                        'imported' => $progress['imported'],
                        'duplicates' => $progress['duplicates'],
                        'updated_at' => now(),
                    ]);
                }
            );

            DB::table('purchase_invoice_extractions')->where('id', $this->extractionId)->update([
                'status' => 'completed',
                'total_messages' => $summary['messages'],
                'reviewed' => $summary['messages'],
                'imported' => $summary['imported'],
                'duplicates' => $summary['duplicates'],
                'errors' => count($summary['errors']),
                'updated_at' => now(),
            ]);
        } catch (Throwable $exception) {
            DB::table('purchase_invoice_extractions')->where('id', $this->extractionId)->update([
                'status' => 'failed', 'updated_at' => now(),
            ]);
            throw $exception;
        }
    }
}
