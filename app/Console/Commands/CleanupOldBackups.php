<?php

namespace App\Console\Commands;

use App\Models\Backup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupOldBackups extends Command
{
    protected $signature   = 'app:cleanup-old-backups {--days=90 : Eliminar backups más antiguos que N días}';
    protected $description = 'Elimina backups del almacenamiento local más antiguos que el umbral configurado';

    public function handle(): int
    {
        $days    = (int) $this->option('days');
        $cutoff  = now()->subDays($days);

        $old = Backup::where('uploaded_at', '<', $cutoff)
            ->orWhere(fn ($q) => $q->whereNull('uploaded_at')->where('created_at', '<', $cutoff))
            ->get();

        if ($old->isEmpty()) {
            $this->info("No hay backups anteriores a {$days} días.");
            return self::SUCCESS;
        }

        $deleted = 0;
        foreach ($old as $backup) {
            if ($backup->path && Storage::disk('local')->exists($backup->path)) {
                Storage::disk('local')->delete($backup->path);
            }
            $backup->delete();
            $deleted++;
        }

        $this->info("Se eliminaron {$deleted} backup(s) anteriores a {$days} días.");

        return self::SUCCESS;
    }
}
