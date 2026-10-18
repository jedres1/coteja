<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\Company;
use App\Notifications\NewPurchaseInvoicesNotification;
use App\Services\PurchaseInvoiceMailboxImporter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ImportMailboxInvoicesJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(
        public readonly ?string $from = null,
        public readonly ?string $to   = null,
    ) {}

    public function handle(PurchaseInvoiceMailboxImporter $importer): void
    {
        foreach (Company::query()->orderBy('id')->get() as $company) {
            try {
                $summary = $importer->import($this->from, $this->to, $company->id);
            } catch (\RuntimeException $e) {
                Log::warning('ImportMailboxInvoicesJob: ' . $e->getMessage(), ['company_id' => $company->id]);
                return;
            }

            if ($summary['imported'] > 0) {
                User::where('role', 'admin')->each(
                    fn (User $admin) => $admin->notify(new NewPurchaseInvoicesNotification($summary))
                );
            }

            Log::info('ImportMailboxInvoicesJob completado para empresa', ['company_id' => $company->id] + $summary);
        }
    }
}
