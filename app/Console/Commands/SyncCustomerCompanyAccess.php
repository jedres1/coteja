<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\User;
use Illuminate\Console\Command;

class SyncCustomerCompanyAccess extends Command
{
    protected $signature   = 'app:sync-customer-company-access {--dry-run : Muestra los cambios sin aplicarlos}';
    protected $description = 'Vincula todos los usuarios cliente con todas las empresas de su cliente';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $companies = Company::all();
        $linked = 0;

        foreach ($companies as $company) {
            $userIds = User::where('customer_id', $company->customer_id)->pluck('id');

            if ($userIds->isEmpty()) {
                continue;
            }

            $existing = $company->users()->pluck('users.id');
            $missing  = $userIds->diff($existing);

            if ($missing->isEmpty()) {
                continue;
            }

            $this->line("Empresa <info>{$company->business_name}</info>: vinculando {$missing->count()} usuario(s).");

            if (! $dryRun) {
                $company->users()->syncWithoutDetaching($missing->all());
            }

            $linked += $missing->count();
        }

        if ($linked === 0) {
            $this->info('Todos los usuarios ya tienen acceso a sus empresas. Nada que hacer.');
            return self::SUCCESS;
        }

        $prefix = $dryRun ? '[dry-run] ' : '';
        $this->info("{$prefix}{$linked} vínculo(s) " . ($dryRun ? 'pendiente(s).' : 'creado(s) correctamente.'));

        return self::SUCCESS;
    }
}
