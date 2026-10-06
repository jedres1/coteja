<?php

use App\Jobs\ImportMailboxInvoicesJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ── Importar facturas de compra desde buzón cada 30 minutos ────────────────
Schedule::job(new ImportMailboxInvoicesJob)->everyThirtyMinutes()->name('import-mailbox');

// ── Notificar licencias próximas a vencer (diario, 7 días de anticipación) ─
Schedule::command('app:check-license-expiry')->dailyAt('08:00');

// ── Limpiar backups antiguos (semanal, más de 90 días) ─────────────────────
Schedule::command('app:cleanup-old-backups')->weeklyOn(0, '02:00');
