<?php

namespace App\Providers;

use App\Models\BillingInvoice;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\JournalEntry;
use App\Models\PurchaseInvoice;
use App\Models\Supplier;
use App\Observers\AuditObserver;
use App\Policies\CustomerPolicy;
use App\Policies\JournalEntryPolicy;
use App\Policies\PurchaseInvoicePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $observer = AuditObserver::class;

        Customer::observe($observer);
        Supplier::observe($observer);
        BillingInvoice::observe($observer);
        PurchaseInvoice::observe($observer);
        JournalEntry::observe($observer);
        Employee::observe($observer);

        Gate::policy(JournalEntry::class, JournalEntryPolicy::class);
        Gate::policy(PurchaseInvoice::class, PurchaseInvoicePolicy::class);
        Gate::policy(Customer::class, CustomerPolicy::class);
    }
}
