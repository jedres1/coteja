<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\AppRelease;
use App\Models\Backup;
use App\Models\BillingInvoice;
use App\Models\PurchaseInvoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class PortalController extends Controller
{
    public function dashboard()
    {
        $user = auth()->user();
        $customer = $user->customer;
        $companies = $this->accessibleCompanies();
        $activeCompanyId = session('active_company_id');
        $activeCompany = $companies->firstWhere('id', $activeCompanyId);

        if ($activeCompanyId && ! $activeCompany) {
            session()->forget('active_company_id');
            $activeCompanyId = null;
        }

        if (!$activeCompanyId && $companies->count() === 1) {
            $activeCompany = $companies->first();
            $activeCompanyId = $activeCompany->id;
            session(['active_company_id' => $activeCompanyId]);
        }

        $licenses = $customer
            ? $customer->licenses()
                ->with(['company', 'plan', 'backups'])
                ->whereIn('company_id', $companies->pluck('id'))
                ->when($activeCompanyId, fn ($query) => $query->where('company_id', $activeCompanyId))
                ->when(!$activeCompanyId && $companies->count() > 1, fn ($query) => $query->whereRaw('1 = 0'))
                ->latest()
                ->get()
            : collect();

        return Inertia::render('Client/Dashboard', [
            'customer' => $customer,
            'companies' => $companies,
            'activeCompany' => $activeCompany,
            'licenses' => $licenses,
            'releases' => AppRelease::where('is_active', true)->latest()->get(),
        ]);
    }

    public function switchCompany(Request $request)
    {
        $data = $request->validate([
            'company_id' => ['required', 'integer'],
        ]);

        $company = $this->accessibleCompanies()->firstWhere('id', (int) $data['company_id']);
        abort_unless($company, 404);

        session(['active_company_id' => $company->id]);

        return back();
    }

    public function billing(Request $request)
    {
        $customer = auth()->user()->customer;
        abort_unless($customer, 404);
        $activeCompany = $this->activeCompanyOrRedirect();
        if (!$activeCompany) {
            return redirect()->route('client.dashboard')->withErrors('Seleccione la empresa a la que desea conectarse.');
        }

        $search = trim((string) $request->query('search', ''));

        return Inertia::render('Client/Billing', [
            'customer' => $customer,
            'activeCompany' => $activeCompany,
            'search' => $search,
            'invoices' => BillingInvoice::where('customer_id', $customer->id)
                ->where(fn ($q) => $q
                    ->where('company_id', $activeCompany->id)
                    ->orWhereNull('company_id'))
                ->when($search !== '', function ($query) use ($search) {
                    $query->where(function ($query) use ($search) {
                        $query->where('number_control', 'like', "%{$search}%")
                            ->orWhere('generation_code', 'like', "%{$search}%")
                            ->orWhere('reception_stamp', 'like', "%{$search}%")
                            ->orWhere('status', 'like', "%{$search}%");
                    });
                })
                ->latest('issued_at')
                ->latest()
                ->paginate(15)
                ->withQueryString(),
        ]);
    }

    public function purchases(Request $request)
    {
        $customer = auth()->user()->customer;
        abort_unless($customer, 404);
        $activeCompany = $this->activeCompanyOrRedirect();
        if (!$activeCompany) {
            return redirect()->route('client.dashboard')->withErrors('Seleccione la empresa a la que desea conectarse.');
        }

        $search = trim((string) $request->query('search', ''));

        return Inertia::render('Client/Purchases', [
            'customer' => $customer,
            'activeCompany' => $activeCompany,
            'search' => $search,
            'invoices' => PurchaseInvoice::with('supplier')
                ->where('customer_id', $customer->id)
                ->where(fn ($q) => $q
                    ->where('company_id', $activeCompany->id)
                    ->orWhereNull('company_id'))
                ->when($search !== '', function ($query) use ($search) {
                    $query->where(function ($query) use ($search) {
                        $query->where('invoice_number', 'like', "%{$search}%")
                            ->orWhere('payment_method', 'like', "%{$search}%")
                            ->orWhere('payment_status', 'like', "%{$search}%")
                            ->orWhereHas('supplier', function ($query) use ($search) {
                                $query->where('name', 'like', "%{$search}%")
                                    ->orWhere('document_number', 'like', "%{$search}%")
                                    ->orWhere('nrc', 'like', "%{$search}%");
                            });
                    });
                })
                ->latest('purchase_date')
                ->latest()
                ->paginate(15)
                ->withQueryString(),
        ]);
    }

    public function backup(Backup $backup)
    {
        $allowed = $this->accessibleCompanies()->contains('id', $backup->company_id);

        abort_unless($allowed && Storage::disk('local')->exists($backup->path), 404);
        return Storage::disk('local')->download($backup->path, $backup->filename);
    }

    private function accessibleCompanies()
    {
        $user = auth()->user();

        return $user->accessibleCompanies()
            ->where('customer_id', $user->customer_id)
            ->orderBy('business_name')
            ->get();
    }

    private function activeCompanyOrRedirect()
    {
        $companies = $this->accessibleCompanies();
        abort_if($companies->isEmpty(), 403);

        $activeCompany = $companies->firstWhere('id', session('active_company_id'));

        if ($activeCompany) {
            return $activeCompany;
        }

        if ($companies->count() === 1) {
            $activeCompany = $companies->first();
            session(['active_company_id' => $activeCompany->id]);

            return $activeCompany;
        }

        return null;
    }
}
