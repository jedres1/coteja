<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Backup;
use App\Models\Company;
use App\Models\License;
use App\Models\Payment;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __invoke()
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        return Inertia::render('Admin/Dashboard', [
            'companiesCount' => Company::count(),
            'activeLicensesCount' => License::where('status', 'active')->count(),
            'expiredLicensesCount' => License::whereDate('expires_at', '<', now())->count(),
            'monthlyRevenue' => Payment::where('status', 'paid')->whereMonth('paid_at', now()->month)->sum('total'),
            'recentLicenses' => License::with(['customer', 'company', 'plan'])->latest()->take(8)->get(),
            'recentBackups' => Backup::with(['company', 'license.customer'])->latest('uploaded_at')->take(8)->get(),
        ]);
    }
}
