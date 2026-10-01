<?php

use App\Http\Controllers\Admin\AppReleaseController;
use App\Http\Controllers\Admin\InventoryController;
use App\Http\Controllers\Admin\BackupController;
use App\Http\Controllers\Admin\CompanyController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\LicenseController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\AccountingController;
use App\Http\Controllers\Admin\JournalController;
use App\Http\Controllers\Admin\BankTransactionController;
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
    });
});

// Módulo Compras: admin, consultant o customer con acceso a purchases
Route::middleware(['auth', 'role:admin,consultant,customer', 'module:purchases'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('suppliers', SupplierController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::get('purchase-invoices/settings', [PurchaseInvoiceController::class, 'settingsIndex'])->name('purchase-invoices.settings');
    Route::post('purchase-invoices/settings', [PurchaseInvoiceController::class, 'settingsUpdate'])->name('purchase-invoices.settings.update');
    Route::post('purchase-invoices/extract', [PurchaseInvoiceController::class, 'extract'])->name('purchase-invoices.extract');
    Route::get('extraccion-pendiente', [PurchaseInvoiceController::class, 'pendingApproval'])->name('purchase-invoices.pending-approval');
    Route::post('purchase-invoices/aprobar-masivo', [PurchaseInvoiceController::class, 'approveBulk'])->name('purchase-invoices.approve-bulk');
    Route::post('purchase-invoices/rechazar-masivo', [PurchaseInvoiceController::class, 'rejectBulk'])->name('purchase-invoices.reject-bulk');
    Route::post('purchase-invoices/{purchaseInvoice}/aprobar', [PurchaseInvoiceController::class, 'approve'])->name('purchase-invoices.approve');
    Route::post('purchase-invoices/{purchaseInvoice}/rechazar', [PurchaseInvoiceController::class, 'reject'])->name('purchase-invoices.reject');
    Route::get('cuentas-por-pagar', [PurchaseInvoiceController::class, 'accountsPayable'])->name('purchase-invoices.accounts-payable');
    Route::post('purchase-invoices/{purchaseInvoice}/pay', [PurchaseInvoiceController::class, 'markPaid'])->name('purchase-invoices.pay');
    Route::get('control-bancario', [BankTransactionController::class, 'index'])->name('bank-transactions.index');
    Route::post('control-bancario', [BankTransactionController::class, 'store'])->name('bank-transactions.store');

    // Bank accounts
    Route::get('control-bancario/cuentas', [BankTransactionController::class, 'accounts'])->name('bank-transactions.accounts');
    Route::post('control-bancario/cuentas', [BankTransactionController::class, 'storeAccount'])->name('bank-transactions.accounts.store');
    Route::put('control-bancario/cuentas/{account}', [BankTransactionController::class, 'updateAccount'])->name('bank-transactions.accounts.update');
    Route::delete('control-bancario/cuentas/{account}', [BankTransactionController::class, 'destroyAccount'])->name('bank-transactions.accounts.destroy');

    // Transactions
    Route::get('control-bancario/transacciones', [BankTransactionController::class, 'transactions'])->name('bank-transactions.transactions');
    Route::post('control-bancario/transacciones', [BankTransactionController::class, 'storeTransaction'])->name('bank-transactions.transactions.store');

    // Reconciliations
    Route::get('control-bancario/conciliacion', [BankTransactionController::class, 'reconciliations'])->name('bank-transactions.reconciliations');
    Route::post('control-bancario/conciliacion', [BankTransactionController::class, 'storeReconciliation'])->name('bank-transactions.reconciliations.store');
    Route::get('control-bancario/conciliacion/{reconciliation}', [BankTransactionController::class, 'showReconciliation'])->name('bank-transactions.reconciliations.show');
    Route::post('control-bancario/conciliacion/{reconciliation}/upload', [BankTransactionController::class, 'uploadStatement'])->name('bank-transactions.reconciliations.upload');
    Route::post('control-bancario/conciliacion/{reconciliation}/toggle', [BankTransactionController::class, 'toggleReconcile'])->name('bank-transactions.reconciliations.toggle');
    Route::post('control-bancario/conciliacion/{reconciliation}/complete', [BankTransactionController::class, 'completeReconciliation'])->name('bank-transactions.reconciliations.complete');

    Route::resource('purchase-invoices', PurchaseInvoiceController::class)->only(['index', 'store', 'update', 'destroy']);
});

// Módulo Contabilidad: admin, consultant o customer con acceso a purchases
Route::middleware(['auth', 'role:admin,consultant,customer', 'module:purchases'])->prefix('admin')->name('admin.')->group(function () {
    // Catálogo y Configuración
    Route::prefix('contabilidad')->name('accounting.')->controller(AccountingController::class)->group(function () {
        Route::get('catalogo', 'catalogo')->name('catalogo');
        Route::post('catalogo', 'store')->name('catalogo.store');
        Route::put('catalogo/{account}', 'update')->name('catalogo.update');
        Route::delete('catalogo/{account}', 'destroy')->name('catalogo.destroy');

        Route::get('configuracion', 'configuracion')->name('configuracion');
        Route::post('paquetes', 'storePackage')->name('paquetes.store');
        Route::put('paquetes/{package}', 'updatePackage')->name('paquetes.update');
        Route::delete('paquetes/{package}', 'destroyPackage')->name('paquetes.destroy');

        Route::get('periodos', 'periodos')->name('periodos');
        Route::post('periodos/generar', 'generarPeriodos')->name('periodos.generar');
        Route::post('periodos/{period}/abrir', 'abrirPeriodo')->name('periodos.abrir');
        Route::post('periodos/{period}/cerrar', 'cerrarPeriodo')->name('periodos.cerrar');

        Route::get('saldos', 'saldos')->name('saldos');
        Route::get('balanza-comprobacion', 'balanzaComprobacion')->name('balanza-comprobacion');
        Route::get('libro-mayor', 'libroMayor')->name('libro-mayor');
        Route::get('balance-general', 'balanceGeneral')->name('balance-general');
        Route::get('estado-resultados', 'estadoResultados')->name('estado-resultados');
    });

    // Diario
    Route::prefix('contabilidad/diario')->name('accounting.diario.')->controller(JournalController::class)->group(function () {
        Route::get('',                    'index')  ->name('index');
        Route::get('nuevo',               'create') ->name('create');
        Route::post('',                   'store')  ->name('store');
        Route::get('{entry}',             'show')   ->name('show');
        Route::get('{entry}/editar',      'edit')   ->name('edit');
        Route::put('{entry}',             'update') ->name('update');
        Route::post('{entry}/aprobar',    'approve')->name('approve');
        Route::post('{entry}/anular',     'annul')  ->name('annul');
        Route::delete('{entry}',          'destroy')->name('destroy');
    });
});

// Módulo Inventario: admin, consultant o customer con acceso a inventory
Route::middleware(['auth', 'role:admin,consultant,customer', 'module:inventory'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('inventario/tipos-producto', [InventoryController::class, 'productTypes'])->name('inventory.product-types');
    Route::post('inventario/tipos-producto', [InventoryController::class, 'storeProductType'])->name('inventory.product-types.store');
    Route::delete('inventario/tipos-producto/{productType}', [InventoryController::class, 'destroyProductType'])->name('inventory.product-types.destroy');
    Route::get('inventario/bodegas', [InventoryController::class, 'warehouses'])->name('inventory.warehouses');
    Route::post('inventario/bodegas', [InventoryController::class, 'storeWarehouse'])->name('inventory.warehouses.store');
    Route::get('inventario/parametros', [InventoryController::class, 'parameters'])->name('inventory.parameters');
    Route::post('inventario/parametros', [InventoryController::class, 'saveParameters'])->name('inventory.parameters.save');
    Route::get('inventario/movimientos', [InventoryController::class, 'movements'])->name('inventory.movements');
    Route::post('inventario/movimientos', [InventoryController::class, 'storeMovement'])->name('inventory.movements.store');
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
        Route::post('facturas/{invoice}/pagar', 'registerPayment')->name('facturas.pagar');
        Route::get('cuentas-por-cobrar', 'accountsReceivable')->name('cuentas-por-cobrar');
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
