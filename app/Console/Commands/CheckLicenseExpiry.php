<?php

namespace App\Console\Commands;

use App\Models\License;
use App\Models\User;
use App\Notifications\LicenseExpiringNotification;
use Illuminate\Console\Command;

class CheckLicenseExpiry extends Command
{
    protected $signature   = 'app:check-license-expiry {--days=7 : Días de anticipación para alertar}';
    protected $description = 'Notifica a los administradores sobre licencias próximas a vencer';

    public function handle(): int
    {
        $days = (int) $this->option('days');

        $expiring = License::with(['customer', 'company'])
            ->where('status', 'active')
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [now()->toDateString(), now()->addDays($days)->toDateString()])
            ->get();

        if ($expiring->isEmpty()) {
            $this->info('No hay licencias próximas a vencer.');
            return self::SUCCESS;
        }

        $admins = User::where('role', 'admin')->get();

        if ($admins->isEmpty()) {
            $this->warn('No hay usuarios admin para notificar.');
            return self::SUCCESS;
        }

        foreach ($expiring as $license) {
            $daysLeft = (int) now()->diffInDays($license->expires_at, false);
            $admins->each(fn (User $admin) => $admin->notify(new LicenseExpiringNotification($license, $daysLeft)));
            $this->line("  Licencia {$license->license_key} — vence en {$daysLeft} día(s).");
        }

        $this->info("Se notificaron {$expiring->count()} licencia(s) a {$admins->count()} admin(s).");

        return self::SUCCESS;
    }
}
