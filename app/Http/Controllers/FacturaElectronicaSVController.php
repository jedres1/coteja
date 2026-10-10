<?php

namespace App\Http\Controllers;

use App\Exceptions\AccountingPeriodException;
use App\Http\Controllers\Admin\InventoryController;
use App\Jobs\SendInvoiceEmailJob;
use App\Models\BillingDteCorrelative;
use App\Models\BillingInvoice;
use App\Models\Customer;
use App\Models\BillingInvoicePayment;
use App\Models\BillingProduct;
use App\Models\BillingSetting;
use App\Models\InventoryMovement;
use App\Models\ProductType;
use App\Services\Accounting\AccountingEntryService;
use App\Services\Accounting\AccountingPeriodService;
use App\Services\Billing\CorrelativeService;
use App\Services\Billing\InvoiceContingenciaService;
use App\Services\Billing\InvoiceDispatchService;
use App\Services\Billing\InvoiceEOEService;
use App\Services\Billing\InvoiceReturnService;
use App\Services\Billing\InvoiceVoidService;
use App\Services\DteEngine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class FacturaElectronicaSVController extends Controller
{
    public function __construct(
        private readonly DteEngine $engine,
        private readonly CorrelativeService $correlatives,
        private readonly InvoiceVoidService $voids,
        private readonly InvoiceReturnService $returns,
        private readonly InvoiceEOEService $eoes,
        private readonly InvoiceContingenciaService $contingencias,
        private readonly InvoiceDispatchService $dispatch,
    ) {}

    // ── Cuentas por Cobrar ─────────────────────────────────────────────────────

    public function accountsReceivablePage(Request $request)
    {
        $tenantId = $this->activeTenantId($request);

        // Aplica el mes corriente como rango por defecto si no se envían parámetros
        if (! $request->query('from')) $request->merge(['from' => now()->startOfMonth()->toDateString()]);
        if (! $request->query('to'))   $request->merge(['to'   => now()->toDateString()]);

        $query    = $this->buildReceivableQuery($request, $tenantId);
        $all      = $this->buildReceivableBaseQuery($request, $tenantId);

        $availableCustomers = $request->user()?->isAdmin()
            ? Customer::where('status', 'active')->orderBy('name')->get(['id', 'name'])
                ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->values()
            : collect();

        return Inertia::render('Admin/Billing/CuentasPorCobrar', [
            'stats'              => $this->buildReceivableStats($all),
            'invoices'           => $query->paginate(15)->through(fn (BillingInvoice $invoice) => $this->formatReceivableItem($invoice)),
            'filters'            => [
                'search'         => trim((string) $request->query('search', '')),
                'from'           => $request->query('from'),
                'to'             => $request->query('to'),
                'payment_status' => $request->query('payment_status', ''),
            ],
            'availableCustomers' => $availableCustomers,
        ]);
    }

    public function accountsReceivable(Request $request)
    {
        $tenantId = $this->activeTenantId($request);
        $query    = $this->buildReceivableQuery($request, $tenantId);
        $all      = $this->buildReceivableBaseQuery($request, $tenantId);

        return response()->json([
            'success'  => true,
            'stats'    => $this->buildReceivableStats($all),
            'invoices' => $query->paginate(15)->through(fn (BillingInvoice $invoice) => [
                ...$this->formatReceivableItem($invoice),
                'status' => $invoice->status,
            ]),
        ]);
    }

    // ── Facturas ───────────────────────────────────────────────────────────────

    public function facturasPage(Request $request)
    {
        $tenantId = $this->activeTenantId($request);
        $search   = trim((string) $request->query('search', ''));
        $today    = now()->toDateString();

        // Aplica el mes corriente como rango por defecto si no se envían parámetros
        if (! $request->query('from')) $request->merge(['from' => now()->startOfMonth()->toDateString()]);
        if (! $request->query('to'))   $request->merge(['to'   => $today]);

        $scope    = fn ($q) => $q->when($tenantId !== null, fn ($q) => $q->where('tenant_customer_id', $tenantId));

        $invoices = $this->buildFacturasQuery($request, $tenantId)->paginate(15);

        $availableCustomers = $request->user()?->isAdmin()
            ? Customer::where('status', 'active')->orderBy('name')->get(['id', 'name'])
                ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->values()
            : collect();

        return Inertia::render('Admin/Billing/Facturas', [
            'stats' => [
                'todayCount'     => BillingInvoice::whereDate('issued_at', $today)->tap($scope)->count(),
                'todaySentTotal' => (float) BillingInvoice::whereDate('issued_at', $today)
                    ->whereIn('status', ['ENVIADO', 'ACEPTADO'])->tap($scope)->sum('total'),
                'sentCount'      => BillingInvoice::whereIn('status', ['ENVIADO', 'ACEPTADO'])->tap($scope)->count(),
                'pendingCount'   => BillingInvoice::whereIn('status', ['PENDIENTE', 'FIRMADO', 'CONTINGENCIA'])->tap($scope)->count(),
                'voidedCount'    => BillingInvoice::where('status', 'ANULADO')->tap($scope)->count(),
            ],
            'invoices'             => $invoices->through(fn (BillingInvoice $invoice) => $this->formatFacturaItem($invoice)),
            'filters'              => [
                'search' => $search,
                'from'   => $request->query('from'),
                'to'     => $request->query('to'),
                'status' => $request->query('status', ''),
                'type'   => $request->query('type', ''),
            ],
            'contingenciaInvoices' => BillingInvoice::where('status', 'CONTINGENCIA')
                ->tap($scope)
                ->whereNotNull('signed_dte')
                ->orderBy('issued_at')
                ->get()
                ->map(fn (BillingInvoice $inv) => [
                    'id'             => $inv->id,
                    'numberControl'  => $inv->number_control,
                    'generationCode' => $inv->generation_code,
                    'documentType'   => $inv->document_type,
                    'customerName'   => $inv->customer_name,
                    'date'           => optional($inv->issued_at)->format('Y-m-d'),
                ])->values(),
            'availableCustomers'   => $availableCustomers,
        ]);
    }

    public function facturas(Request $request)
    {
        return response()->json([
            'success'  => true,
            'invoices' => $this->buildFacturasQuery($request, null)->paginate(15)
                ->through(fn (BillingInvoice $invoice) => $this->formatFacturaItem($invoice)),
        ]);
    }

    public function dashboard()
    {
        $today = now()->toDateString();

        return response()->json([
            'success' => true,
            'stats'   => [
                'todayCount'     => BillingInvoice::whereDate('issued_at', $today)->count(),
                'todaySentTotal' => (float) BillingInvoice::whereDate('issued_at', $today)
                    ->whereIn('status', ['ENVIADO', 'ACEPTADO'])->sum('total'),
                'sentCount'      => BillingInvoice::whereIn('status', ['ENVIADO', 'ACEPTADO'])->count(),
                'pendingCount'   => BillingInvoice::whereIn('status', ['PENDIENTE', 'FIRMADO', 'CONTINGENCIA'])->count(),
                'voidedCount'    => BillingInvoice::where('status', 'ANULADO')->count(),
            ],
            'recent'  => BillingInvoice::latest('issued_at')->latest('id')->take(10)->get()
                ->map(fn (BillingInvoice $invoice) => [
                    'id'            => $invoice->id,
                    'date'          => optional($invoice->issued_at)->format('Y-m-d'),
                    'numberControl' => $invoice->number_control,
                    'customerName'  => $invoice->customer_name,
                    'total'         => (float) $invoice->total,
                    'status'        => $invoice->status,
                ]),
        ]);
    }

    // ── Vista de Factura ───────────────────────────────────────────────────────

    public function verFactura(BillingInvoice $invoice)
    {
        return response()->json([
            'success' => true,
            'invoice' => $this->invoiceDetail($invoice),
        ]);
    }

    // ── Pagos ──────────────────────────────────────────────────────────────────

    public function registerPayment(Request $request, BillingInvoice $invoice)
    {
        $isInertia = (bool) $request->header('X-Inertia');

        if (! in_array($invoice->status, ['ENVIADO', 'ACEPTADO'])) {
            if ($isInertia) return back()->withErrors(['amount' => 'Solo se pueden registrar pagos en facturas aceptadas o enviadas.']);
            return response()->json(['success' => false, 'message' => 'Solo se pueden registrar pagos en facturas aceptadas o enviadas.'], 422);
        }

        if ($invoice->payment_status === 'pagado') {
            if ($isInertia) return back()->withErrors(['amount' => 'Esta factura ya está completamente pagada.']);
            return response()->json(['success' => false, 'message' => 'Esta factura ya está completamente pagada.'], 422);
        }

        $data = $request->validate([
            'amount'    => 'required|numeric|min:0.01',
            'method'    => 'nullable|string|max:100',
            'reference' => 'nullable|string|max:200',
            'notes'     => 'nullable|string|max:500',
        ]);

        $maxAllowed = $invoice->balance;
        if ((float) $data['amount'] > $maxAllowed + 0.001) {
            $msg = "El monto ingresado (\${$data['amount']}) supera el saldo pendiente (\${$maxAllowed}).";
            if ($isInertia) return back()->withErrors(['amount' => $msg]);
            return response()->json(['success' => false, 'message' => $msg], 422);
        }

        BillingInvoicePayment::create([
            'billing_invoice_id' => $invoice->id,
            'amount'             => $data['amount'],
            'method'             => $data['method'] ?? null,
            'reference'          => $data['reference'] ?? null,
            'notes'              => $data['notes'] ?? null,
        ]);

        $newAmountPaid = round((float) $invoice->amount_paid + (float) $data['amount'], 2);
        $newStatus     = $newAmountPaid >= (float) $invoice->total - 0.001
            ? 'pagado'
            : ($newAmountPaid > 0 ? 'parcial' : 'pendiente');

        $invoice->update([
            'amount_paid'    => $newAmountPaid,
            'payment_status' => $newStatus,
            'paid_at'        => $newStatus === 'pagado' ? now() : $invoice->paid_at,
        ]);

        $message = $newStatus === 'pagado' ? 'Pago registrado. Factura marcada como pagada.' : 'Pago parcial registrado.';

        if ($isInertia) {
            return back()->with('status', $message);
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'invoice' => [
                'id'            => $invoice->id,
                'total'         => (float) $invoice->total,
                'amountPaid'    => (float) $newAmountPaid,
                'balance'       => max(0, (float) $invoice->total - $newAmountPaid),
                'paymentStatus' => $newStatus,
                'paidAt'        => $newStatus === 'pagado' ? now()->format('Y-m-d') : null,
            ],
        ]);
    }

    // ── Productos ──────────────────────────────────────────────────────────────

    public function productosPage(Request $request)
    {
        return Inertia::render('Admin/Billing/Productos', $this->buildProductosData($request));
    }

    public function productos(Request $request)
    {
        return response()->json(['success' => true, ...$this->buildProductosData($request)]);
    }

    public function guardarProducto(Request $request)
    {
        $hasInventory = $request->user()?->hasModuleAccess('inventory') ?? false;

        $data = $request->validate([
            'id'             => 'nullable|integer|exists:billing_products,id',
            'code'           => 'required|string|max:100',
            'description'    => 'required|string|max:255',
            'type'           => ['required', 'string', $hasInventory ? 'in:1,2,3,4' : 'in:1,2'],
            'price'          => 'required|numeric|min:0',
            'unit'           => 'required|string|in:59,11,14,22,23,26,29,58,99',
            'isExempt'       => 'boolean',
            'notes'          => 'nullable|string|max:1000',
            'stockQuantity'  => 'nullable|integer|min:0',
            'minStock'       => 'nullable|integer|min:0',
            'productTypeId'  => 'nullable|exists:product_types,id',
        ]);

        $productTypeId = $data['productTypeId'] ?? null;
        if (! $hasInventory && ! $productTypeId) {
            $defaultName   = $data['type'] === '2' ? 'Servicio' : 'Bien Terminado';
            $defaultType   = ProductType::firstOrCreate(['name' => $defaultName], ['controls_inventory' => $data['type'] !== '2']);
            $productTypeId = $defaultType->id;
        }

        $product = BillingProduct::updateOrCreate(
            ['id' => $data['id'] ?? null],
            [
                'code'            => $data['code'],
                'description'     => $data['description'],
                'type'            => $data['type'],
                'price'           => $data['price'],
                'unit'            => $data['unit'],
                'is_exempt'       => $data['isExempt'] ?? false,
                'notes'           => $data['notes'] ?? null,
                'stock_quantity'  => isset($data['stockQuantity']) ? (int) $data['stockQuantity'] : null,
                'min_stock'       => isset($data['minStock']) ? (int) $data['minStock'] : null,
                'product_type_id' => $productTypeId,
            ],
        );

        $product->load('productType');

        if ($request->header('X-Inertia')) {
            return redirect()->route('admin.factura-sv.billing.productos')
                ->with('status', 'Producto guardado correctamente.');
        }

        return response()->json([
            'success' => true,
            'product' => $this->formatProduct($product),
        ]);
    }

    // ── Dashboard Facturación ──────────────────────────────────────────────────

    public function billingDashboardPage(Request $request)
    {
        $tenantId = $this->activeTenantId($request);
        $today    = now()->toDateString();
        $scope    = fn ($q) => $q->when($tenantId !== null, fn ($q) => $q->where('tenant_customer_id', $tenantId));

        $availableCustomers = $request->user()?->isAdmin()
            ? Customer::where('status', 'active')->orderBy('name')->get(['id', 'name'])
                ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->values()
            : collect();

        return Inertia::render('Admin/Billing/Dashboard', [
            'stats'              => [
                'todayCount'     => BillingInvoice::whereDate('issued_at', $today)->tap($scope)->count(),
                'todaySentTotal' => (float) BillingInvoice::whereDate('issued_at', $today)
                    ->whereIn('status', ['ENVIADO', 'ACEPTADO'])->tap($scope)->sum('total'),
                'sentCount'      => BillingInvoice::whereIn('status', ['ENVIADO', 'ACEPTADO'])->tap($scope)->count(),
                'pendingCount'   => BillingInvoice::whereIn('status', ['PENDIENTE', 'FIRMADO', 'CONTINGENCIA'])->tap($scope)->count(),
                'voidedCount'    => BillingInvoice::where('status', 'ANULADO')->tap($scope)->count(),
            ],
            'recent'             => BillingInvoice::query()
                ->when($tenantId !== null, fn ($q) => $q->where('tenant_customer_id', $tenantId))
                ->latest('issued_at')->latest('id')->take(15)->get()
                ->map(fn (BillingInvoice $invoice) => $this->formatFacturaItem($invoice)),
            'availableCustomers' => $availableCustomers,
        ]);
    }

    // ── Nueva / Editar Factura ─────────────────────────────────────────────────

    public function nuevaFacturaPage(Request $request)
    {
        $tenantId = $this->activeTenantId($request);

        return Inertia::render('Admin/Billing/NuevaFactura', $this->buildInvoiceFormData($request, $tenantId));
    }

    public function editarFactura(Request $request, BillingInvoice $invoice)
    {
        if ($invoice->status !== 'RECHAZADO') {
            return redirect()->route('admin.factura-sv.billing.facturas')
                ->withErrors(['_error' => 'Solo se pueden corregir facturas en estado RECHAZADO.']);
        }

        $tenantId = $invoice->tenant_customer_id ?? $this->activeTenantId($request);

        return Inertia::render('Admin/Billing/NuevaFactura', [
            ...$this->buildInvoiceFormData($request, $tenantId),
            'editInvoice' => [
                'id'            => $invoice->id,
                'customerId'    => $invoice->customer_id,
                'numberControl' => $invoice->number_control,
                'observations'  => $invoice->observations,
                'dte'           => $invoice->json_dte,
            ],
        ]);
    }

    // ── Configuración ──────────────────────────────────────────────────────────

    public function configuracionPage(Request $request)
    {
        $tenantId = $this->activeTenantId($request);
        $settings = BillingSetting::allAsArray($tenantId);

        $availableCustomers = $request->user()?->isAdmin()
            ? Customer::where('status', 'active')->orderBy('name')->get(['id', 'name'])
                ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->values()
            : collect();

        return Inertia::render('Admin/Billing/Configuracion', [
            'settings'           => [
                'emisor'    => $settings['emisor']    ?? [],
                'hacienda'  => $settings['hacienda']  ?? [],
                'firma'     => $settings['firma']     ?? [],
                'correo'    => $settings['correo']    ?? [],
                'backup'    => $settings['backup']    ?? [],
                'documentos' => $settings['documentos'] ?? [],
            ],
            'correlativos'       => $this->buildCorrelativos($tenantId),
            'currentYear'        => now()->year,
            'availableCustomers' => $availableCustomers,
        ]);
    }

    public function guardarConfiguracion(Request $request)
    {
        $tenantId = $this->activeTenantId($request);

        $data = $request->validate([
            'emisor'       => 'required|array',
            'hacienda'     => 'required|array',
            'firma'        => 'required|array',
            'correo'       => 'required|array',
            'backup'       => 'nullable|array',
            'documentos'   => 'array',
            'correlativos' => 'array',
        ]);

        foreach (['emisor', 'hacienda', 'firma', 'correo', 'backup', 'documentos'] as $key) {
            BillingSetting::put($key, $data[$key] ?? [], $tenantId);
        }

        foreach (($data['correlativos'] ?? []) as $documentType => $nextNumber) {
            BillingDteCorrelative::updateOrCreate(
                [
                    'customer_id'   => $tenantId,
                    'document_type' => $documentType,
                    'year'          => now()->year,
                    'establishment' => $data['emisor']['codigo_establecimiento'] ?? 'M001',
                    'point_of_sale' => $data['emisor']['punto_venta']            ?? 'P001',
                ],
                ['next_number' => max(1, (int) $nextNumber)]
            );
        }

        if ($request->header('X-Inertia')) {
            return back()->with('status', 'Configuración guardada correctamente.');
        }

        return response()->json(['success' => true, 'message' => 'Configuración guardada.']);
    }

    // ── Procesar Factura (flujo principal DTE) ─────────────────────────────────

    public function procesarFactura(Request $request)
    {
        $data = $request->validate([
            'tipo'       => 'nullable|string|in:01,03,04,05,06,07,08,11,14,15',
            'config'     => 'required|array',
            'cliente'    => 'required|array',
            'items'      => 'required|array',
            'resumen'    => 'required|array',
            'opciones'   => 'array',
            'customerId' => 'nullable|integer|exists:customers,id',
            'firma'      => 'nullable|array',
            'hacienda'   => 'nullable|array',
            'correo'     => 'nullable|array',
        ]);

        $steps    = [];
        $tipo     = $data['tipo'] ?? '01';
        $tenantId = $this->activeTenantId($request);

        try {
            app(AccountingPeriodService::class)->validateDateOrFail(now());
        } catch (AccountingPeriodException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        [$invoice, $dte, $generated] = DB::transaction(function () use ($data, $tipo, $tenantId) {
            $correlative = $this->correlatives->reserve($tipo, $data['config'], $tenantId);
            $options     = $data['opciones'] ?? [];
            $options['correlativo'] = (int) $correlative->next_number;

            $generated = $this->engine->generate(
                $tipo,
                $data['config'],
                $data['cliente'],
                $data['items'],
                $data['resumen'],
                $options,
            );

            $dte            = $generated['dte'] ?? [];
            $numberControl  = data_get($dte, 'identificacion.numeroControl');
            $generationCode = data_get($dte, 'identificacion.codigoGeneracion');

            if ($conflict = BillingInvoice::where('number_control', $numberControl)->where('generation_code', '!=', $generationCode)->first()) {
                throw new \RuntimeException("El número de control {$numberControl} ya existe en la factura {$conflict->id}. Revise correlativos.");
            }

            $invoice = BillingInvoice::updateOrCreate(
                ['generation_code' => $generationCode],
                [
                    'tenant_customer_id' => $tenantId,
                    'number_control'     => $numberControl,
                    'document_type'      => $tipo,
                    'issued_at'          => data_get($dte, 'identificacion.fecEmi', now()->toDateString()),
                    'customer_id'        => $data['customerId'] ?? null,
                    'customer_name'      => $data['cliente']['nombre'] ?? null,
                    'subtotal'           => (float) ($data['resumen']['subtotal'] ?? $data['resumen']['subTotal'] ?? 0),
                    'iva'                => (float) ($data['resumen']['iva'] ?? 0),
                    'total'              => (float) ($data['resumen']['total'] ?? $data['resumen']['totalPagar'] ?? 0),
                    'status'             => 'PENDIENTE',
                    'has_error'          => ! ($generated['success'] ?? false),
                    'accepted'           => false,
                    'observations'       => json_encode($generated['validation'] ?? [], JSON_UNESCAPED_UNICODE),
                    'json_dte'           => $dte,
                ],
            );

            $usedNumber = $this->correlatives->extractUsedNumber($dte);
            if ($usedNumber !== null) {
                $this->correlatives->advance($correlative, $usedNumber);
            }

            return [$invoice, $dte, $generated];
        });

        $steps[] = [
            'key'     => 'generate',
            'status'  => ($generated['success'] ?? false) ? 'done' : 'warning',
            'message' => ($generated['success'] ?? false) ? 'DTE generado y validado.' : 'DTE generado con observaciones.',
        ];
        $steps[] = ['key' => 'save', 'status' => 'done', 'message' => 'Factura guardada localmente.'];

        $this->registrarSalidasInventario($request, $data['items'], $invoice);

        try {
            app(AccountingEntryService::class)->createFromBilling($invoice->fresh(), $request->user()?->id);
        } catch (\Throwable) {
            // No interrumpir el flujo DTE si falla la contabilidad
        }

        $tenantSettings = BillingSetting::allAsArray($tenantId);
        $firma          = $this->dispatch->resolveSignSettings($data['firma'] ?? [], $tenantSettings);
        $signedDte      = null;

        if (! empty($firma['certificado_path']) && ! empty($firma['password'])) {
            try {
                $signedDte = $this->dispatch->signDocument($invoice, $dte, $firma, $data['config']);
                $steps[] = ['key' => 'sign', 'status' => 'done', 'message' => 'Documento firmado.'];
            } catch (\Throwable $e) {
                $invoice->update(['has_error' => true, 'observations' => $e->getMessage()]);
                $steps[] = ['key' => 'sign', 'status' => 'error', 'message' => 'No se pudo firmar: ' . $e->getMessage()];
            }
        } else {
            $steps[] = ['key' => 'sign', 'status' => 'warning', 'message' => 'Factura pendiente de firma. Configure certificado y contraseña para firmar automáticamente.'];
        }

        $hacienda = $this->dispatch->resolveHaciendaSettings($data['hacienda'] ?? [], $tenantSettings);

        if ($signedDte && ! empty($hacienda['usuario']) && ! empty($hacienda['password'])) {
            try {
                ['accepted' => $accepted, 'sent' => $sent] = $this->dispatch->sendToHacienda(
                    $invoice, $signedDte, $data['config'], $hacienda, $firma['password'] ?? null,
                );

                $steps[] = ['key' => 'send', 'status' => $accepted ? 'done' : 'error', 'message' => $accepted ? 'Documento enviado a Hacienda.' : 'Hacienda no aceptó el documento.'];
                $steps[] = ['key' => 'response', 'status' => $accepted ? 'done' : 'error', 'message' => $accepted ? 'Sello de recepción recibido.' : 'Respuesta de Hacienda sin sello de aprobación.'];

                if ($accepted) {
                    $correo  = $this->dispatch->resolveMailSettings($data['correo'] ?? [], $tenantSettings);
                    $steps[] = ['key' => 'attachments', 'status' => 'done', 'message' => 'Creando PDF y JSON con respuesta de Hacienda.'];
                    $steps[] = $this->dispatch->sendEmail($invoice->fresh(), $signedDte, $data['config'], $correo, $data['cliente'], $sent);
                } else {
                    $steps[] = ['key' => 'attachments', 'status' => 'warning', 'message' => 'No se crearon adjuntos de correo porque el documento no fue aprobado.'];
                    $steps[] = ['key' => 'email', 'status' => 'warning', 'message' => 'No se envió correo porque Hacienda no devolvió sello de aprobación.'];
                }
            } catch (\Throwable $e) {
                $invoice->update(['status' => 'RECHAZADO', 'has_error' => true, 'observations' => $e->getMessage()]);
                $steps[] = ['key' => 'send', 'status' => 'error', 'message' => 'No se pudo enviar a Hacienda: ' . $e->getMessage()];
                $steps[] = ['key' => 'response', 'status' => 'error', 'message' => 'No hubo respuesta aprobada de Hacienda.'];
                $steps[] = ['key' => 'attachments', 'status' => 'warning', 'message' => 'No se crearon adjuntos de correo porque el documento no fue aprobado.'];
                $steps[] = ['key' => 'email', 'status' => 'warning', 'message' => 'No se envió correo porque el documento no fue aprobado.'];
            }
        } else {
            $steps[] = ['key' => 'send', 'status' => 'warning', 'message' => 'Envío pendiente. Firme el documento y configure credenciales de Hacienda.'];
            $steps[] = ['key' => 'response', 'status' => 'warning', 'message' => 'Respuesta de Hacienda pendiente.'];
            $steps[] = ['key' => 'attachments', 'status' => 'warning', 'message' => 'PDF y JSON de correo pendientes hasta aprobación.'];
            $steps[] = ['key' => 'email', 'status' => 'warning', 'message' => 'Correo pendiente hasta que el documento sea aprobado por Hacienda.'];
        }

        return response()->json([
            'success' => true,
            'message' => 'Proceso de factura finalizado.',
            'invoice' => $invoice->fresh(),
            'dte'     => $dte,
            'steps'   => $steps,
        ]);
    }

    // ── Enviar Factura Guardada ────────────────────────────────────────────────

    public function marcarContingencia(Request $request, BillingInvoice $invoice)
    {
        if (! in_array($invoice->status, ['FIRMADO', 'RECHAZADO'])) {
            return back()->withErrors(['_error' => 'Solo facturas en estado FIRMADO o RECHAZADO pueden marcarse como contingencia.']);
        }

        if (! $invoice->signed_dte) {
            return back()->withErrors(['_error' => 'La factura no tiene documento firmado. Fírmela antes de marcar como contingencia.']);
        }

        $invoice->update(['status' => 'CONTINGENCIA', 'has_error' => false]);

        return back()->with('status', 'Factura marcada como contingencia. Podrá enviarla en lote cuando Hacienda esté disponible.');
    }

    public function enviarFacturaGuardada(Request $request, BillingInvoice $invoice)
    {
        $isInertia = (bool) $request->header('X-Inertia');

        if ($invoice->status === 'CONTINGENCIA') {
            $msg = 'Las facturas en contingencia deben enviarse mediante el lote de contingencia, no de forma individual.';
            if ($isInertia) return back()->withErrors(['_error' => $msg]);
            return response()->json(['success' => false, 'message' => $msg], 422);
        }

        $tenantId = $invoice->tenant_customer_id ?? $this->activeTenantId($request);
        $settings = BillingSetting::allAsArray($tenantId);
        $firma    = $this->dispatch->resolveSignSettings($settings['firma'] ?? [], $settings);
        $hacienda = $this->dispatch->resolveHaciendaSettings($settings['hacienda'] ?? [], $settings);
        $correo   = $this->dispatch->resolveMailSettings($settings['correo'] ?? [], $settings);
        $emisor   = $settings['emisor'] ?? [];
        $dte      = $invoice->signed_dte ?: $invoice->json_dte;
        $steps    = [];

        if (! $dte) {
            if ($isInertia) return back()->with('error', 'La factura no tiene JSON DTE guardado.');
            return response()->json(['success' => false, 'message' => 'La factura no tiene JSON DTE guardado.'], 422);
        }

        if (! $invoice->signed_dte) {
            if (empty($firma['certificado_path']) || empty($firma['password'])) {
                if ($isInertia) return back()->with('error', 'La factura no está firmada y falta certificado/contraseña para firmar.');
                return response()->json(['success' => false, 'message' => 'La factura no está firmada y falta certificado/contraseña para firmar.'], 422);
            }

            try {
                $dte     = $this->dispatch->signDocument($invoice, $invoice->json_dte, $firma, $emisor);
                $steps[] = ['key' => 'sign', 'status' => 'done', 'message' => 'Documento firmado.'];
            } catch (\Throwable $e) {
                if ($isInertia) return back()->with('error', $e->getMessage());
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }
        }

        if (empty($hacienda['usuario']) || empty($hacienda['password'])) {
            if ($isInertia) return back()->with('error', 'Faltan credenciales de Hacienda para enviar.');
            return response()->json(['success' => false, 'message' => 'Faltan credenciales de Hacienda para enviar.'], 422);
        }

        try {
            ['accepted' => $accepted, 'sent' => $sent] = $this->dispatch->sendToHacienda(
                $invoice, $dte, $emisor, $hacienda, $firma['password'] ?? null,
            );
        } catch (\Throwable $e) {
            $invoice->update(['status' => 'RECHAZADO', 'has_error' => true, 'observations' => $e->getMessage()]);
            $steps[] = ['key' => 'send', 'status' => 'error', 'message' => 'No se pudo enviar a Hacienda: ' . $e->getMessage()];

            if ($isInertia) return back()->with('error', 'No se pudo enviar a Hacienda: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'No se pudo enviar a Hacienda: ' . $e->getMessage(),
                'invoice' => $invoice->fresh(),
                'steps'   => $steps,
            ], 422);
        }

        $steps[] = ['key' => 'send', 'status' => $accepted ? 'done' : 'error', 'message' => $accepted ? 'Documento enviado a Hacienda.' : 'Hacienda no aceptó el documento.'];

        if ($accepted) {
            $cliente = $invoice->customer?->toDteReceptor() ?: [
                'nombre' => $invoice->customer_name,
                'email'  => data_get($invoice->json_dte, 'receptor.correo'),
            ];
            $steps[] = ['key' => 'attachments', 'status' => 'done', 'message' => 'Creando PDF y JSON con respuesta de Hacienda.'];
            $steps[] = $this->dispatch->sendEmail($invoice->fresh(), $dte, $emisor, $correo, $cliente, $sent);
        } else {
            $steps[] = ['key' => 'email', 'status' => 'warning', 'message' => 'No se envió correo porque Hacienda no devolvió sello de aprobación.'];
        }

        if ($isInertia) {
            $msg = $accepted ? 'Factura enviada y correo procesado.' : 'Hacienda no aceptó el documento.';
            return back()->with($accepted ? 'status' : 'error', $msg);
        }

        return response()->json([
            'success' => $accepted,
            'message' => $accepted ? 'Factura enviada y correo procesado.' : 'Hacienda no aceptó el documento.',
            'invoice' => $invoice->fresh(),
            'steps'   => $steps,
        ]);
    }

    // ── Correo Guardado ────────────────────────────────────────────────────────

    public function enviarCorreoFacturaGuardada(BillingInvoice $invoice)
    {
        if (! $invoice->accepted || ! $invoice->reception_stamp) {
            return response()->json(['success' => false, 'message' => 'Solo se puede enviar por correo un documento aprobado por Hacienda.'], 422);
        }

        SendInvoiceEmailJob::dispatch($invoice->id);

        return response()->json([
            'success' => true,
            'message' => 'El correo fue encolado y se enviará en breve.',
            'invoice' => $this->invoiceDetail($invoice),
        ]);
    }

    // ── Anulación ──────────────────────────────────────────────────────────────

    public function anularFacturaGuardada(Request $request, BillingInvoice $invoice)
    {
        $isInertia = (bool) $request->header('X-Inertia');

        $data = $request->validate([
            'tipoAnulacion'     => 'required|integer|in:1,2,3',
            'motivo'            => 'required|string|min:5|max:250',
            'codigoGeneracionR' => 'nullable|string|max:36',
        ]);

        $tenantId = $invoice->tenant_customer_id ?? $this->activeTenantId($request);

        try {
            $result = $this->voids->process($invoice, BillingSetting::allAsArray($tenantId), $data);
        } catch (\Throwable $e) {
            $invoice->update(['has_error' => true, 'observations' => $e->getMessage()]);
            if ($isInertia) return back()->withErrors(['motivo' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        if (! ($result['success'] ?? false)) {
            $invoice->update([
                'has_error'    => true,
                'observations' => json_encode($result['response'] ?? $result, JSON_UNESCAPED_UNICODE),
            ]);
            if ($isInertia) {
                $msg = $result['message'] ?? json_encode($result['response'] ?? $result, JSON_UNESCAPED_UNICODE);
                return back()->withErrors(['motivo' => $msg]);
            }
            return response()->json($result, 422);
        }

        if ($isInertia) {
            return back()->with('status', 'Factura anulada exitosamente.');
        }

        return response()->json($result);
    }

    // ── Eventos ────────────────────────────────────────────────────────────────

    public function eventoRetorno(Request $request, BillingInvoice $invoice)
    {
        $isInertia = (bool) $request->header('X-Inertia');

        $data = $request->validate([
            'items'                      => 'required|array|min:1',
            'items.*.descripcion'        => 'required|string|max:1000',
            'items.*.cantidad'           => 'required|numeric|min:0.000001',
            'items.*.precioUni'          => 'required|numeric|min:0',
            'items.*.montoDescu'         => 'nullable|numeric|min:0',
            'items.*.tipoItem'           => 'nullable|integer|in:1,2,3,4',
            'items.*.unidad_medida'      => 'nullable|string',
            'items.*.seguro'             => 'nullable|numeric|min:0',
            'items.*.flete'              => 'nullable|numeric|min:0',
            'items.*.retencionRenta'     => 'nullable|numeric|min:0',
            'resumen.condicionOperacion' => 'nullable|integer|in:1,2,3',
            'resumen.observaciones'      => 'nullable|string|max:3000',
        ]);

        $tenantId = $invoice->tenant_customer_id ?? $this->activeTenantId($request);

        try {
            $result = $this->returns->process($invoice, BillingSetting::allAsArray($tenantId), $data);
        } catch (\Throwable $e) {
            $invoice->update(['has_error' => true, 'observations' => $e->getMessage()]);
            if ($isInertia) return back()->withErrors(['items' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        if (! ($result['success'] ?? false)) {
            $invoice->update([
                'has_error'    => true,
                'observations' => json_encode($result['response'] ?? $result, JSON_UNESCAPED_UNICODE),
            ]);
            if ($isInertia) {
                $msg = $result['message'] ?? json_encode($result['response'] ?? $result, JSON_UNESCAPED_UNICODE);
                return back()->withErrors(['items' => $msg]);
            }
            return response()->json($result, 422);
        }

        if ($isInertia) {
            return back()->with('status', 'Evento de Retorno enviado exitosamente a Hacienda.');
        }

        return response()->json($result);
    }

    public function eventoOperacionesEspeciales(Request $request)
    {
        $data = $request->validate([
            'detalle'                    => 'required|array|min:1',
            'detalle.*.tipoOperacion'    => 'required|string|in:01,02,03',
            'detalle.*.descripcion'      => 'required|string|max:1000',
            'detalle.*.monto'            => 'required|numeric|min:0',
            'detalle.*.observaciones'    => 'nullable|string|max:3000',
            'resumen.condicionOperacion' => 'nullable|integer|in:1,2,3',
            'resumen.observaciones'      => 'nullable|string|max:3000',
            'receptor.nombre'            => 'nullable|string|max:250',
            'receptor.codPais'           => 'nullable|string',
            'receptor.tipoPersona'       => 'nullable|integer|in:1,2',
        ]);

        $tenantId = $this->activeTenantId($request);

        try {
            $result = $this->eoes->process(BillingSetting::allAsArray($tenantId), $data);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        if (! ($result['success'] ?? false)) {
            return response()->json($result, 422);
        }

        return response()->json($result);
    }

    public function eventoContingencia(Request $request)
    {
        $data = $request->validate([
            'invoiceIds'                  => 'required|array|min:1',
            'invoiceIds.*'                => 'integer|exists:billing_invoices,id',
            'emisor.nombreResponsable'    => 'required|string|min:5|max:100',
            'emisor.tipoDocResponsable'   => 'required|string|in:36,13,02,03,37',
            'emisor.numeroDocResponsable' => 'required|string|min:3|max:20',
            'motivo.tipoContingencia'     => 'required|integer|in:1,2,3,4,5',
            'motivo.motivoContingencia'   => 'nullable|string|max:500',
            'motivo.fInicio'              => 'required|date_format:Y-m-d',
            'motivo.fFin'                 => 'required|date_format:Y-m-d',
            'motivo.hInicio'              => 'required|date_format:H:i:s',
            'motivo.hFin'                 => 'required|date_format:H:i:s',
        ]);

        $tenantId = $this->activeTenantId($request);

        $invoices = BillingInvoice::whereIn('id', $data['invoiceIds'])
            ->where('status', 'CONTINGENCIA')
            ->whereNotNull('signed_dte')
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_customer_id', $tenantId))
            ->get();

        if ($invoices->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontraron facturas en contingencia con DTE firmado para los IDs proporcionados.',
            ], 422);
        }

        try {
            $result = $this->contingencias->process($invoices, BillingSetting::allAsArray($tenantId), $data);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        if (! ($result['success'] ?? false)) {
            return response()->json($result, 422);
        }

        return response()->json($result);
    }

    // ── Utilidades ─────────────────────────────────────────────────────────────

    public function setActiveTenant(Request $request)
    {
        $request->validate(['customer_id' => 'required|integer|exists:customers,id']);
        $request->session()->put('billing_tenant_id', $request->input('customer_id'));
        return back()->with('status', 'Empresa activa actualizada.');
    }

    public function clearActiveTenant(Request $request)
    {
        $request->session()->forget('billing_tenant_id');
        return back()->with('status', 'Empresa activa eliminada.');
    }

    public function estadoFirmador(Request $request)
    {
        $data = $request->validate([
            'certificadoPath' => 'required|string',
            'nit'             => 'required|string',
            'passwordPri'     => 'required|string',
        ]);

        return response()->json($this->engine->validateCertificate(
            $data['nit'],
            $data['passwordPri'],
            $data['certificadoPath'],
        ));
    }

    public function autenticar(Request $request)
    {
        $data = $request->validate(['config' => 'required|array']);

        return response()->json($this->engine->authenticate($data['config']));
    }

    public function subirLogo(Request $request)
    {
        $request->validate(['logo' => 'required|file|mimes:png,jpg,jpeg|max:2048']);
        $path = $request->file('logo')->store('logos', 'public');

        return response()->json(['path' => '/storage/' . $path]);
    }

    public function subirCertificado(Request $request)
    {
        $request->validate(['certificado' => 'required|file|extensions:p12,pfx,crt|max:4096']);
        $path = $request->file('certificado')->storeAs(
            'certificados',
            $request->file('certificado')->getClientOriginalName(),
            'local'
        );

        return response()->json(['path' => Storage::disk('local')->path($path)]);
    }

    public function pdf(Request $request)
    {
        $data = $request->validate([
            'factura'  => 'array',
            'dte'      => 'required',
            'config'   => 'required|array',
            'filename' => 'nullable|string',
        ]);

        return response()->json($this->engine->pdf(
            $data['factura'] ?? [],
            $data['dte'],
            $data['config'],
            $data['filename'] ?? 'dte.pdf',
        ));
    }

    // ── Helpers privados ───────────────────────────────────────────────────────

    private function activeTenantId(Request $request): ?int
    {
        $user = $request->user();
        if (! $user) {
            return null;
        }
        if ($user->isAdmin()) {
            $id = $request->session()->get('billing_tenant_id');
            return $id ? (int) $id : null;
        }
        return $user->customer_id ? (int) $user->customer_id : null;
    }

    private function buildReceivableBaseQuery(Request $request, ?int $tenantId): Builder
    {
        $tenantScope = fn ($q) => $q->when($tenantId !== null, fn ($q) => $q->where('tenant_customer_id', $tenantId));

        return BillingInvoice::query()
            ->tap($tenantScope)
            ->whereIn('status', ['ENVIADO', 'ACEPTADO'])
            ->whereNotIn('document_type', ['04', '05', '07', '15']);
    }

    private function buildReceivableQuery(Request $request, ?int $tenantId): Builder
    {
        $search        = trim((string) $request->query('search', ''));
        $paymentStatus = $request->query('payment_status', '');

        return $this->buildReceivableBaseQuery($request, $tenantId)
            ->when($paymentStatus !== '', fn ($q) => $q->where('payment_status', $paymentStatus))
            ->when($search !== '', fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('number_control', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%");
            }))
            ->when($request->query('from'), fn ($q, $from) => $q->whereDate('issued_at', '>=', $from))
            ->when($request->query('to'), fn ($q, $to) => $q->whereDate('issued_at', '<=', $to))
            ->latest('issued_at')
            ->latest('id');
    }

    private function buildReceivableStats(Builder $baseQuery): array
    {
        return [
            'totalPorCobrar' => (float) (clone $baseQuery)->where('payment_status', '!=', 'pagado')->sum(DB::raw('total - amount_paid')),
            'totalCobrado'   => (float) (clone $baseQuery)->sum('amount_paid'),
            'countPendiente' => (clone $baseQuery)->where('payment_status', 'pendiente')->count(),
            'countParcial'   => (clone $baseQuery)->where('payment_status', 'parcial')->count(),
            'countPagado'    => (clone $baseQuery)->where('payment_status', 'pagado')->count(),
        ];
    }

    private function buildFacturasQuery(Request $request, ?int $tenantId): Builder
    {
        $search = trim((string) $request->query('search', ''));

        return BillingInvoice::query()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_customer_id', $tenantId))
            ->when($search !== '', fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('number_control', 'like', "%{$search}%")
                    ->orWhere('generation_code', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%")
                    ->orWhere('reception_stamp', 'like', "%{$search}%");
            }))
            ->when($request->query('from'), fn ($q, $from) => $q->whereDate('issued_at', '>=', $from))
            ->when($request->query('to'), fn ($q, $to) => $q->whereDate('issued_at', '<=', $to))
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('type'), fn ($q, $type) => $q->where('document_type', $type))
            ->latest('issued_at')
            ->latest('id');
    }

    private function buildCorrelativos(?int $tenantId): array
    {
        return BillingDteCorrelative::query()
            ->where('year', now()->year)
            ->when($tenantId !== null, fn ($q) => $q->where('customer_id', $tenantId))
            ->when($tenantId === null, fn ($q) => $q->whereNull('customer_id'))
            ->get()
            ->mapWithKeys(fn (BillingDteCorrelative $r) => [$r->document_type => $r->next_number])
            ->all();
    }

    private function buildInvoiceFormData(Request $request, ?int $tenantId): array
    {
        $settings = BillingSetting::allAsArray($tenantId);

        $customers = Customer::where('status', 'active')->orderBy('name')->get()
            ->map(fn (Customer $c) => [
                'id'               => $c->id,
                'name'             => $c->name,
                'preferredDteType' => $c->preferred_dte_type,
                'receptor'         => $c->toDteReceptor(),
                'isClientesVarios' => $c->email === 'clientes.varios@coteja.internal',
            ]);

        $products = BillingProduct::with('productType')->orderBy('description')->get()
            ->map(fn ($p) => [
                'id'          => $p->id,
                'code'        => $p->code,
                'description' => $p->description,
                'type'        => $p->type,
                'price'       => $p->price,
                'unit'        => $p->unit,
                'isExempt'    => (bool) $p->is_exempt,
                'notes'       => $p->notes,
            ]);

        $availableCustomers = $request->user()?->isAdmin()
            ? Customer::where('status', 'active')->orderBy('name')->get(['id', 'name'])
                ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->values()
            : collect();

        return [
            'customers'          => $customers,
            'products'           => $products,
            'settings'           => [
                'emisor'    => $settings['emisor']    ?? [],
                'hacienda'  => $settings['hacienda']  ?? [],
                'firma'     => $settings['firma']     ?? [],
                'correo'    => $settings['correo']    ?? [],
                'documentos' => $settings['documentos'] ?? [],
            ],
            'correlativos'       => $this->buildCorrelativos($tenantId),
            'clientesVariosId'   => Customer::where('email', 'clientes.varios@coteja.internal')->value('id'),
            'hasInventory'       => $request->user()?->hasModuleAccess('inventory') ?? false,
            'currentYear'        => now()->year,
            'availableCustomers' => $availableCustomers,
        ];
    }

    private function buildProductosData(Request $request): array
    {
        return [
            'hasInventory' => $request->user()?->hasModuleAccess('inventory') ?? false,
            'productTypes' => ProductType::orderBy('name')->get()
                ->map(fn (ProductType $t) => ['id' => $t->id, 'name' => $t->name, 'controlsInventory' => $t->controls_inventory]),
            'products'     => BillingProduct::with('productType')->orderBy('description')->get()
                ->map(fn (BillingProduct $p) => [
                    ...$this->formatProduct($p),
                    'invoiceItem' => $p->toInvoiceItem(),
                ]),
        ];
    }

    private function formatProduct(BillingProduct $product): array
    {
        return [
            'id'               => $product->id,
            'code'             => $product->code,
            'description'      => $product->description,
            'type'             => $product->type,
            'price'            => (float) $product->price,
            'unit'             => $product->unit,
            'isExempt'         => $product->is_exempt,
            'notes'            => $product->notes,
            'stockQuantity'    => $product->stock_quantity,
            'minStock'         => $product->min_stock,
            'productTypeId'    => $product->product_type_id,
            'productTypeName'  => $product->productType?->name,
            'controlsInventory' => $product->productType?->controls_inventory ?? false,
        ];
    }

    private function formatReceivableItem(BillingInvoice $invoice): array
    {
        return [
            'id'            => $invoice->id,
            'date'          => optional($invoice->issued_at)->format('Y-m-d'),
            'numberControl' => $invoice->number_control,
            'documentType'  => $invoice->document_type,
            'customerName'  => $invoice->customer_name,
            'total'         => (float) $invoice->total,
            'amountPaid'    => (float) $invoice->amount_paid,
            'balance'       => (float) $invoice->balance,
            'paymentStatus' => $invoice->payment_status,
            'paidAt'        => optional($invoice->paid_at)->format('Y-m-d'),
        ];
    }

    private function formatFacturaItem(BillingInvoice $invoice): array
    {
        return [
            'id'             => $invoice->id,
            'date'           => optional($invoice->issued_at)->format('Y-m-d'),
            'numberControl'  => $invoice->number_control,
            'generationCode' => $invoice->generation_code,
            'documentType'   => $invoice->document_type,
            'customerName'   => $invoice->customer_name,
            'total'          => (float) $invoice->total,
            'status'         => $invoice->status,
            'hasError'       => $invoice->has_error,
            'accepted'       => $invoice->accepted,
            'emailSent'      => $invoice->email_sent,
            'receptionStamp' => $invoice->reception_stamp,
            'returnStamp'    => $invoice->return_stamp,
            'voidStamp'      => $invoice->void_stamp,
            'voidedAt'       => optional($invoice->voided_at)->format('Y-m-d H:i:s'),
            'voidReason'     => $invoice->void_reason,
            'observations'   => $invoice->observations,
        ];
    }

    private function invoiceDetail(BillingInvoice $invoice): array
    {
        $dte      = $invoice->signed_dte ?: $invoice->json_dte ?: [];
        $receptor = $dte['receptor'] ?? $dte['sujetoExcluido'] ?? [];
        $resumen  = $dte['resumen'] ?? [];

        return [
            'id'             => $invoice->id,
            'date'           => optional($invoice->issued_at)->format('Y-m-d'),
            'numberControl'  => $invoice->number_control,
            'generationCode' => $invoice->generation_code,
            'documentType'   => $invoice->document_type,
            'customerName'   => $invoice->customer_name,
            'status'         => $invoice->status,
            'hasError'       => $invoice->has_error,
            'accepted'       => $invoice->accepted,
            'emailSent'      => $invoice->email_sent,
            'receptionStamp' => $invoice->reception_stamp,
            'voidStamp'      => $invoice->void_stamp,
            'voidedAt'       => optional($invoice->voided_at)->format('Y-m-d H:i:s'),
            'voidReason'     => $invoice->void_reason,
            'voidJson'       => $invoice->void_json,
            'observations'   => $invoice->observations,
            'subtotal'       => (float) ($resumen['subTotal'] ?? $resumen['subTotalVentas'] ?? $invoice->subtotal),
            'iva'            => (float) ($resumen['totalIva'] ?? $invoice->iva),
            'ivaRetenido'    => (float) ($resumen['ivaRete1'] ?? 0),
            'ivaPercibido'   => (float) ($resumen['ivaPerci1'] ?? 0),
            'rentaRetenida'  => (float) ($resumen['reteRenta'] ?? 0),
            'total'          => (float) ($resumen['totalPagar'] ?? $resumen['montoTotalOperacion'] ?? $invoice->total),
            'customer'       => [
                'name'     => $receptor['nombre']       ?? $invoice->customer_name,
                'document' => $receptor['numDocumento'] ?? null,
                'address'  => data_get($receptor, 'direccion.complemento'),
                'email'    => $receptor['correo']       ?? null,
                'phone'    => $receptor['telefono']     ?? null,
            ],
            'items' => collect($dte['cuerpoDocumento'] ?? [])->map(fn ($item) => [
                'description' => $item['descripcion'] ?? '',
                'quantity'    => (float) ($item['cantidad'] ?? 0),
                'unitPrice'   => (float) ($item['precioUni'] ?? 0),
                'iva'         => (float) ($item['ivaItem'] ?? 0),
                'subtotal'    => (float) ($item['ventaGravada'] ?? $item['ventaExenta'] ?? $item['compra'] ?? 0),
            ])->values(),
            'dte' => $dte,
        ];
    }

    private function registrarSalidasInventario(Request $request, array $items, BillingInvoice $invoice): void
    {
        if (! $request->user()?->hasModuleAccess('inventory')) {
            return;
        }

        $warehouseId = (int) BillingSetting::get('inventory_sales_warehouse_id');
        if (! $warehouseId) {
            return;
        }

        $docNumber   = $invoice->number_control;
        $itemsByCode = collect($items)
            ->filter(fn ($item) => ! blank($item['codigo'] ?? null))
            ->groupBy('codigo');

        foreach ($itemsByCode as $code => $codeItems) {
            $product = BillingProduct::with('productType')->where('code', $code)->first();

            if (! $product || ! ($product->productType?->controls_inventory ?? false)) {
                continue;
            }

            $qty = collect($codeItems)->sum('cantidad');

            InventoryMovement::create([
                'product_id'         => $product->id,
                'warehouse_id'       => $warehouseId,
                'type'               => 'exit',
                'quantity'           => $qty,
                'document_type'      => 'billing',
                'document_number'    => $docNumber,
                'billing_invoice_id' => $invoice->id,
                'notes'              => 'Salida automática por factura electrónica.',
            ]);

            InventoryController::syncWarehouseStock($product, $warehouseId, (float) $qty, 'exit');
        }
    }
}
