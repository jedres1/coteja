<?php

use App\Http\Controllers\Admin\AppReleaseController;
use App\Http\Controllers\Admin\BackupController;
use App\Http\Controllers\Admin\CompanyController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\LicenseController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\PurchaseInvoiceController;
use App\Http\Controllers\Admin\SupplierController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Client\PortalController;
use App\Http\Controllers\FacturaElectronicaSVController;
use App\Models\BillingDteCorrelative;
use App\Models\BillingSetting;
use App\Models\Customer;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.store');
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

// Admin + Consultant: dashboard y gestión interna
Route::middleware(['auth', 'role:admin,consultant'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::middleware('role:admin')->group(function () {
        Route::resource('users', UserController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('customers', CustomerController::class)->only(['index', 'store', 'update']);
        Route::resource('companies', CompanyController::class)->only(['index', 'store', 'update']);
        Route::resource('plans', PlanController::class)->only(['index', 'store', 'update']);
        Route::resource('licenses', LicenseController::class)->only(['index', 'store', 'update']);
        Route::resource('payments', PaymentController::class)->only(['index', 'store', 'update']);
        Route::resource('releases', AppReleaseController::class)->only(['index', 'store']);
        Route::get('backups', [BackupController::class, 'index'])->name('backups.index');
        Route::get('backups/{backup}/download', [BackupController::class, 'download'])->name('backups.download');
        // Configuración y extracción de correo: solo admin
        Route::get('purchase-invoices/settings', [PurchaseInvoiceController::class, 'settingsIndex'])->name('purchase-invoices.settings');
        Route::post('purchase-invoices/settings', [PurchaseInvoiceController::class, 'settingsUpdate'])->name('purchase-invoices.settings.update');
        Route::post('purchase-invoices/extract', [PurchaseInvoiceController::class, 'extract'])->name('purchase-invoices.extract');
    });
});

// Módulo Compras: admin, consultant o customer con acceso a purchases
Route::middleware(['auth', 'role:admin,consultant,customer', 'module:purchases'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('suppliers', SupplierController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('purchase-invoices', PurchaseInvoiceController::class)->only(['index', 'store', 'update', 'destroy']);
});

// Módulo Facturación: admin, consultant o customer con acceso a billing
Route::middleware(['auth', 'role:admin,consultant,customer', 'module:billing'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('factura-sv', function () {
        return view('admin.factura-electronica-sv', [
            'billingSettings' => BillingSetting::allAsArray(),
            'billingCorrelatives' => BillingDteCorrelative::query()
                ->where('year', now()->year)
                ->get()
                ->mapWithKeys(fn (BillingDteCorrelative $row) => [$row->document_type => $row->next_number])
                ->all(),
            'billingCustomers' => Customer::where('status', 'active')
                ->orderBy('name')
                ->get()
                ->map(fn (Customer $customer) => [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'preferredDteType' => $customer->preferred_dte_type,
                    'receptor' => $customer->toDteReceptor(),
                ]),
        ]);
    })->name('factura-sv');
    Route::prefix('factura-sv')->name('factura-sv.')->controller(FacturaElectronicaSVController::class)->group(function () {
        Route::post('procesar', 'procesarFactura')->name('procesar');
        Route::get('dashboard', 'dashboard')->name('dashboard');
        Route::get('facturas', 'facturas')->name('facturas');
        Route::get('facturas/{invoice}', 'verFactura')->name('facturas.ver');
        Route::post('facturas/{invoice}/enviar', 'enviarFacturaGuardada')->name('facturas.enviar');
        Route::post('facturas/{invoice}/correo', 'enviarCorreoFacturaGuardada')->name('facturas.correo');
        Route::post('facturas/{invoice}/anular', 'anularFacturaGuardada')->name('facturas.anular');
        Route::get('productos', 'productos')->name('productos');
        Route::post('productos', 'guardarProducto')->name('productos.guardar');
        Route::post('configuracion', 'guardarConfiguracion')->name('configuracion.guardar');
        Route::post('firmador/estado', 'estadoFirmador')->name('firmador.estado');
        Route::post('autenticar', 'autenticar')->name('autenticar');
        Route::post('pdf', 'pdf')->name('pdf');
    });
});

Route::middleware(['auth', 'role:customer'])->prefix('cliente')->name('client.')->group(function () {
    Route::get('/', [PortalController::class, 'dashboard'])->name('dashboard');
    Route::get('/facturacion', [PortalController::class, 'billing'])->middleware('module:billing')->name('billing');
    Route::get('/compras', [PortalController::class, 'purchases'])->middleware('module:purchases')->name('purchases');
    Route::post('/empresa-activa', [PortalController::class, 'switchCompany'])->name('companies.switch');
    Route::get('/backups/{backup}/download', [PortalController::class, 'backup'])->name('backups.download');
});
