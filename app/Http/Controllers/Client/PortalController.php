<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\AppRelease;
use App\Models\Backup;
use App\Models\BillingInvoice;
use App\Models\PurchaseInvoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class PortalController extends Controller
{
    public function dashboard()
    {
        $user     = auth()->user();
        $customer = $user->customer;
        $companies = $this->accessibleCompanies();

        [$activeCompany, $activeCompanyId] = $this->resolveActiveCompany($companies);

        $licenses = $customer
            ? $customer->licenses()
                ->with(['company', 'plan', 'backups' => fn($q) => $q->latest()->limit(1)])
                ->whereIn('company_id', $companies->pluck('id'))
                ->when($activeCompanyId, fn($q) => $q->where('company_id', $activeCompanyId))
                ->when(!$activeCompanyId && $companies->count() > 1, fn($q) => $q->whereRaw('1 = 0'))
                ->latest()
                ->get()
            : collect();

        $stats = ['billing' => 0, 'purchases' => 0, 'pendingBilling' => 0];
        $recentInvoices = collect();

        if ($customer && $activeCompanyId) {
            $billingBase = BillingInvoice::where('customer_id', $customer->id)
                ->where(fn($q) => $q->where('company_id', $activeCompanyId)->orWhereNull('company_id'));

            $stats['billing']        = (clone $billingBase)->count();
            $stats['pendingBilling'] = (clone $billingBase)->where('status', 'pending')->count();
            $stats['purchases']      = PurchaseInvoice::where('customer_id', $customer->id)
                ->where(fn($q) => $q->where('company_id', $activeCompanyId)->orWhereNull('company_id'))
                ->count();

            $recentInvoices = (clone $billingBase)->latest('issued_at')->limit(5)->get();
        }

        return Inertia::render('Client/Dashboard', [
            'customer'       => $customer,
            'companies'      => $companies,
            'activeCompany'  => $activeCompany,
            'licenses'       => $licenses,
            'releases'       => AppRelease::where('is_active', true)->latest()->get(),
            'stats'          => $stats,
            'recentInvoices' => $recentInvoices,
        ]);
    }

    public function profile()
    {
        return Inertia::render('Client/Profile', [
            'customer' => auth()->user()->customer,
        ]);
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password'         => ['required', 'confirmed', Password::min(8)],
        ]);

        auth()->user()->update(['password' => Hash::make($request->password)]);

        return back()->with('status', 'Contraseña actualizada.');
    }

    public function switchCompany(Request $request)
    {
        $data    = $request->validate(['company_id' => ['required', 'integer']]);
        $company = $this->accessibleCompanies()->firstWhere('id', (int) $data['company_id']);
        abort_unless($company, 404);

        session(['active_company_id' => $company->id]);
        return back();
    }

    public function billing(Request $request)
    {
        $customer = auth()->user()->customer;
        abort_unless($customer, 404);

        $activeCompany = $this->requireActiveCompany();

        $search = trim((string) $request->query('search', ''));
        $status = trim((string) $request->query('status', ''));

        $invoices = BillingInvoice::where('customer_id', $customer->id)
            ->where(fn($q) => $q->where('company_id', $activeCompany->id)->orWhereNull('company_id'))
            ->when($search, fn($q) => $q->where(fn($q2) => $q2
                ->where('number_control', 'like', "%{$search}%")
                ->orWhere('generation_code', 'like', "%{$search}%")
                ->orWhere('reception_stamp', 'like', "%{$search}%")
                ->orWhere('document_number', 'like', "%{$search}%")))
            ->when($status, fn($q) => $q->where('status', $status))
            ->latest('issued_at')
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Client/Billing', [
            'customer'      => $customer,
            'activeCompany' => $activeCompany,
            'search'        => $search,
            'statusFilter'  => $status,
            'invoices'      => $invoices,
        ]);
    }

    public function purchases(Request $request)
    {
        $customer = auth()->user()->customer;
        abort_unless($customer, 404);

        $activeCompany = $this->requireActiveCompany();

        $search = trim((string) $request->query('search', ''));
        $status = trim((string) $request->query('status', ''));

        $invoices = PurchaseInvoice::with('supplier')
            ->where('customer_id', $customer->id)
            ->where(fn($q) => $q->where('company_id', $activeCompany->id)->orWhereNull('company_id'))
            ->when($search, fn($q) => $q->where(fn($q2) => $q2
                ->where('invoice_number', 'like', "%{$search}%")
                ->orWhereHas('supplier', fn($sq) => $sq
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('document_number', 'like', "%{$search}%")
                    ->orWhere('nrc', 'like', "%{$search}%"))))
            ->when($status, fn($q) => $q->where('payment_status', $status))
            ->latest('purchase_date')
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Client/Purchases', [
            'customer'      => $customer,
            'activeCompany' => $activeCompany,
            'search'        => $search,
            'statusFilter'  => $status,
            'invoices'      => $invoices,
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

    private function resolveActiveCompany($companies): array
    {
        $activeCompanyId = session('active_company_id');
        $activeCompany   = $companies->firstWhere('id', $activeCompanyId);

        if ($activeCompanyId && !$activeCompany) {
            session()->forget('active_company_id');
            $activeCompanyId = null;
        }

        if (!$activeCompanyId && $companies->count() === 1) {
            $activeCompany   = $companies->first();
            $activeCompanyId = $activeCompany->id;
            session(['active_company_id' => $activeCompanyId]);
        }

        return [$activeCompany, $activeCompanyId];
    }

    private function requireActiveCompany()
    {
        $companies = $this->accessibleCompanies();
        abort_if($companies->isEmpty(), 403);

        [$activeCompany] = $this->resolveActiveCompany($companies);

        if (!$activeCompany) {
            redirect()->route('client.dashboard')
                ->withErrors(['company' => 'Seleccione la empresa a la que desea conectarse.'])
                ->throwResponse();
        }

        return $activeCompany;
    }
}
