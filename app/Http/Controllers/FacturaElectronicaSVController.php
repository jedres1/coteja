<?php

namespace App\Http\Controllers;

use App\Exceptions\AccountingPeriodException;
use App\Http\Controllers\Admin\InventoryController;
use App\Models\BillingDteCorrelative;
use App\Models\BillingInvoice;
use App\Models\BillingInvoicePayment;
use App\Models\BillingProduct;
use App\Models\BillingSetting;
use App\Models\InventoryMovement;
use App\Models\ProductType;
use App\Services\Accounting\AccountingEntryService;
use App\Services\Accounting\AccountingPeriodService;
use App\Services\Billing\CorrelativeService;
use App\Services\Billing\InvoiceVoidService;
use App\Services\DteEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class FacturaElectronicaSVController extends Controller
{
    public function __construct(
        private readonly DteEngine $engine,
        private readonly CorrelativeService $correlatives,
        private readonly InvoiceVoidService $voids,
    ) {}

    public function accountsReceivable(Request $request)
    {
        $paymentStatus = $request->query('payment_status', '');
        $search = trim((string) $request->query('search', ''));

        $query = BillingInvoice::query()
            ->whereIn('status', ['ENVIADO', 'ACEPTADO'])
            ->where('document_type', '!=', '05')
            ->when($paymentStatus !== '', fn ($q) => $q->where('payment_status', $paymentStatus))
            ->when($search !== '', fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('number_control', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%");
            }))
            ->when($request->query('from'), fn ($q, $from) => $q->whereDate('issued_at', '>=', $from))
            ->when($request->query('to'), fn ($q, $to) => $q->whereDate('issued_at', '<=', $to))
            ->latest('issued_at')
            ->latest('id');

        $all = BillingInvoice::query()
            ->whereIn('status', ['ENVIADO', 'ACEPTADO'])
            ->where('document_type', '!=', '05');

        $stats = [
            'totalPorCobrar' => (float) (clone $all)->where('payment_status', '!=', 'pagado')->sum(\DB::raw('total - amount_paid')),
            'totalCobrado' => (float) (clone $all)->sum('amount_paid'),
            'countPendiente' => (clone $all)->where('payment_status', 'pendiente')->count(),
            'countParcial' => (clone $all)->where('payment_status', 'parcial')->count(),
            'countPagado' => (clone $all)->where('payment_status', 'pagado')->count(),
        ];

        $invoices = $query->paginate(15);

        return response()->json([
            'success' => true,
            'stats' => $stats,
            'invoices' => $invoices->through(fn (BillingInvoice $invoice) => [
                'id' => $invoice->id,
                'date' => optional($invoice->issued_at)->format('Y-m-d'),
                'numberControl' => $invoice->number_control,
                'documentType' => $invoice->document_type,
                'customerName' => $invoice->customer_name,
                'total' => (float) $invoice->total,
                'amountPaid' => (float) $invoice->amount_paid,
                'balance' => $invoice->balance,
                'paymentStatus' => $invoice->payment_status,
                'paidAt' => optional($invoice->paid_at)->format('Y-m-d'),
                'status' => $invoice->status,
            ]),
        ]);
    }

    public function registerPayment(Request $request, BillingInvoice $invoice)
    {
        if (! in_array($invoice->status, ['ENVIADO', 'ACEPTADO'])) {
            return response()->json(['success' => false, 'message' => 'Solo se pueden registrar pagos en facturas aceptadas o enviadas.'], 422);
        }

        if ($invoice->payment_status === 'pagado') {
            return response()->json(['success' => false, 'message' => 'Esta factura ya está completamente pagada.'], 422);
        }

        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'method' => 'nullable|string|max:100',
            'reference' => 'nullable|string|max:200',
            'notes' => 'nullable|string|max:500',
        ]);

        $maxAllowed = $invoice->balance;
        if ((float) $data['amount'] > $maxAllowed + 0.001) {
            return response()->json(['success' => false, 'message' => "El monto ingresado (\${$data['amount']}) supera el saldo pendiente (\${$maxAllowed})."], 422);
        }

        BillingInvoicePayment::create([
            'billing_invoice_id' => $invoice->id,
            'amount' => $data['amount'],
            'method' => $data['method'] ?? null,
            'reference' => $data['reference'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        $newAmountPaid = round((float) $invoice->amount_paid + (float) $data['amount'], 2);
        $newStatus = $newAmountPaid >= (float) $invoice->total - 0.001 ? 'pagado' : ($newAmountPaid > 0 ? 'parcial' : 'pendiente');

        $invoice->update([
            'amount_paid' => $newAmountPaid,
            'payment_status' => $newStatus,
            'paid_at' => $newStatus === 'pagado' ? now() : $invoice->paid_at,
        ]);

        return response()->json([
            'success' => true,
            'message' => $newStatus === 'pagado' ? 'Pago registrado. Factura marcada como pagada.' : 'Pago parcial registrado.',
            'invoice' => [
                'id' => $invoice->id,
                'total' => (float) $invoice->total,
                'amountPaid' => (float) $newAmountPaid,
                'balance' => max(0, (float) $invoice->total - $newAmountPaid),
                'paymentStatus' => $newStatus,
                'paidAt' => $newStatus === 'pagado' ? now()->format('Y-m-d') : null,
            ],
        ]);
    }

    public function productos(Request $request)
    {
        $hasInventory = $request->user()?->hasModuleAccess('inventory') ?? false;

        return response()->json([
            'success'      => true,
            'hasInventory' => $hasInventory,
            'productTypes' => ProductType::orderBy('name')->get()
                ->map(fn (ProductType $t) => ['id' => $t->id, 'name' => $t->name, 'controlsInventory' => $t->controls_inventory]),
            'products' => BillingProduct::with('productType')->orderBy('description')->get()
                ->map(fn (BillingProduct $product) => [
                    'id'              => $product->id,
                    'code'            => $product->code,
                    'description'     => $product->description,
                    'type'            => $product->type,
                    'price'           => (float) $product->price,
                    'unit'            => $product->unit,
                    'isExempt'        => $product->is_exempt,
                    'notes'           => $product->notes,
                    'stockQuantity'   => $product->stock_quantity,
                    'minStock'        => $product->min_stock,
                    'productTypeId'   => $product->product_type_id,
                    'productTypeName' => $product->productType?->name,
                    'controlsInventory' => $product->productType?->controls_inventory ?? false,
                    'invoiceItem'     => $product->toInvoiceItem(),
                ]),
        ]);
    }

    public function dashboard()
    {
        $today = now()->toDateString();
        $recent = BillingInvoice::query()
            ->latest('issued_at')
            ->latest('id')
            ->take(10)
            ->get();

        return response()->json([
            'success' => true,
            'stats' => [
                'todayCount' => BillingInvoice::whereDate('issued_at', $today)->count(),
                'todaySentTotal' => (float) BillingInvoice::whereDate('issued_at', $today)
                    ->whereIn('status', ['ENVIADO', 'ACEPTADO'])
                    ->sum('total'),
                'sentCount' => BillingInvoice::whereIn('status', ['ENVIADO', 'ACEPTADO'])->count(),
                'pendingCount' => BillingInvoice::whereIn('status', ['PENDIENTE', 'FIRMADO', 'CONTINGENCIA'])->count(),
                'voidedCount' => BillingInvoice::where('status', 'ANULADO')->count(),
            ],
            'recent' => $recent->map(fn (BillingInvoice $invoice) => [
                'id' => $invoice->id,
                'date' => optional($invoice->issued_at)->format('Y-m-d'),
                'numberControl' => $invoice->number_control,
                'customerName' => $invoice->customer_name,
                'total' => (float) $invoice->total,
                'status' => $invoice->status,
            ]),
        ]);
    }

    public function facturas(Request $request)
    {
        $search = trim((string) $request->query('search', ''));

        $invoices = BillingInvoice::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('number_control', 'like', "%{$search}%")
                        ->orWhere('generation_code', 'like', "%{$search}%")
                        ->orWhere('customer_name', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%")
                        ->orWhere('reception_stamp', 'like', "%{$search}%");
                });
            })
            ->when($request->query('from'), fn ($query, $from) => $query->whereDate('issued_at', '>=', $from))
            ->when($request->query('to'), fn ($query, $to) => $query->whereDate('issued_at', '<=', $to))
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->when($request->query('type'), fn ($query, $type) => $query->where('document_type', $type))
            ->latest('issued_at')
            ->latest('id')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'invoices' => $invoices->through(fn (BillingInvoice $invoice) => [
                'id' => $invoice->id,
                'date' => optional($invoice->issued_at)->format('Y-m-d'),
                'numberControl' => $invoice->number_control,
                'generationCode' => $invoice->generation_code,
                'documentType' => $invoice->document_type,
                'customerName' => $invoice->customer_name,
                'total' => (float) $invoice->total,
                'status' => $invoice->status,
                'hasError' => $invoice->has_error,
                'accepted' => $invoice->accepted,
                'emailSent' => $invoice->email_sent,
                'receptionStamp' => $invoice->reception_stamp,
                'voidStamp' => $invoice->void_stamp,
                'voidedAt' => optional($invoice->voided_at)->format('Y-m-d H:i:s'),
                'voidReason' => $invoice->void_reason,
                'observations' => $invoice->observations,
            ]),
        ]);
    }

    public function verFactura(BillingInvoice $invoice)
    {
        return response()->json([
            'success' => true,
            'invoice' => $this->invoiceDetail($invoice),
        ]);
    }

    public function enviarCorreoFacturaGuardada(BillingInvoice $invoice)
    {
        if (! $invoice->accepted || ! $invoice->reception_stamp) {
            return response()->json(['success' => false, 'message' => 'Solo se puede enviar por correo un documento aprobado por Hacienda.'], 422);
        }

        $settings = BillingSetting::allAsArray();
        $dte = $invoice->signed_dte ?: $invoice->json_dte;
        $cliente = $invoice->customer?->toDteReceptor() ?: [
            'nombre' => $invoice->customer_name,
            'email' => data_get($invoice->json_dte, 'receptor.correo') ?: data_get($invoice->json_dte, 'sujetoExcluido.correo'),
        ];
        $response = json_decode((string) $invoice->observations, true) ?: [
            'estado' => $invoice->status,
            'selloRecibido' => $invoice->reception_stamp,
        ];

        $step = $this->enviarCorreoDte(
            $invoice,
            $dte,
            $settings['emisor'] ?? [],
            $this->normalizarCorreo($settings['correo'] ?? []),
            $cliente,
            $response,
        );

        return response()->json([
            'success' => $step['status'] === 'done',
            'message' => $step['message'],
            'step' => $step,
            'invoice' => $this->invoiceDetail($invoice->fresh()),
        ], $step['status'] === 'done' ? 200 : 422);
    }

    public function anularFacturaGuardada(Request $request, BillingInvoice $invoice)
    {
        $data = $request->validate([
            'tipoAnulacion' => 'required|integer|in:1,2,3',
            'motivo' => 'required|string|min:5|max:250',
            'codigoGeneracionR' => 'nullable|string|max:36',
        ]);

        try {
            $result = $this->voids->process($invoice, BillingSetting::allAsArray(), $data);
        } catch (\Throwable $e) {
            $invoice->update([
                'has_error' => true,
                'observations' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        if (! ($result['success'] ?? false)) {
            $invoice->update([
                'has_error' => true,
                'observations' => json_encode($result['response'] ?? $result, JSON_UNESCAPED_UNICODE),
            ]);

            return response()->json($result, 422);
        }

        return response()->json($result);
    }

    public function guardarProducto(Request $request)
    {
        $hasInventory = $request->user()?->hasModuleAccess('inventory') ?? false;

        $data = $request->validate([
            'id'            => 'nullable|integer|exists:billing_products,id',
            'code'          => 'required|string|max:100',
            'description'   => 'required|string|max:255',
            'type'          => ['required', 'string', $hasInventory ? 'in:1,2,3,4' : 'in:1,2'],
            'price'         => 'required|numeric|min:0',
            'unit'          => 'required|string|in:59,11,14,22,23,26,29,58,99',
            'isExempt'       => 'boolean',
            'notes'          => 'nullable|string|max:1000',
            'stockQuantity'  => 'nullable|integer|min:0',
            'minStock'       => 'nullable|integer|min:0',
            'productTypeId'  => 'nullable|exists:product_types,id',
        ]);

        // Usuarios sin inventario: auto-asignar tipo según DTE type
        $productTypeId = $data['productTypeId'] ?? null;
        if (!$hasInventory && !$productTypeId) {
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

        return response()->json([
            'success' => true,
            'product' => [
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
                'invoiceItem'      => $product->toInvoiceItem(),
            ],
        ]);
    }

    public function guardarConfiguracion(Request $request)
    {
        $data = $request->validate([
            'emisor' => 'required|array',
            'hacienda' => 'required|array',
            'firma' => 'required|array',
            'correo' => 'required|array',
            'backup' => 'nullable|array',
            'documentos' => 'array',
            'correlativos' => 'array',
        ]);

        foreach (['emisor', 'hacienda', 'firma', 'correo', 'backup', 'documentos'] as $key) {
            BillingSetting::put($key, $data[$key] ?? []);
        }

        foreach (($data['correlativos'] ?? []) as $documentType => $nextNumber) {
            BillingDteCorrelative::updateOrCreate(
                [
                    'document_type' => $documentType,
                    'year' => now()->year,
                    'establishment' => $data['emisor']['codigo_establecimiento'] ?? 'M001',
                    'point_of_sale' => $data['emisor']['punto_venta'] ?? 'P001',
                ],
                ['next_number' => max(1, (int) $nextNumber)]
            );
        }

        return response()->json(['success' => true, 'message' => 'Configuración guardada.']);
    }

    public function procesarFactura(Request $request)
    {
        $data = $request->validate([
            'tipo' => 'nullable|string|in:01,03,05,06,07,11,14',
            'config' => 'required|array',
            'cliente' => 'required|array',
            'items' => 'required|array',
            'resumen' => 'required|array',
            'opciones' => 'array',
            'customerId' => 'nullable|integer|exists:customers,id',
            'firma' => 'nullable|array',
            'hacienda' => 'nullable|array',
            'correo' => 'nullable|array',
        ]);

        $steps = [];
        $tipo = $data['tipo'] ?? '01';

        // Validar período contable antes de iniciar el proceso DTE
        try {
            app(AccountingPeriodService::class)->validateDateOrFail(now());
        } catch (AccountingPeriodException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        [$invoice, $dte, $generated] = DB::transaction(function () use ($data, $tipo) {
            $correlative = $this->correlatives->reserve($tipo, $data['config']);
            $options = $data['opciones'] ?? [];
            $options['correlativo'] = (int) $correlative->next_number;

            $generated = $this->engine->generate(
                $tipo,
                $data['config'],
                $data['cliente'],
                $data['items'],
                $data['resumen'],
                $options,
            );

            $dte = $generated['dte'] ?? [];
            $numberControl = data_get($dte, 'identificacion.numeroControl');
            $generationCode = data_get($dte, 'identificacion.codigoGeneracion');

            if ($conflict = BillingInvoice::where('number_control', $numberControl)->where('generation_code', '!=', $generationCode)->first()) {
                throw new \RuntimeException("El número de control {$numberControl} ya existe en la factura {$conflict->id}. Revise correlativos.");
            }

            $invoice = BillingInvoice::updateOrCreate(
                ['generation_code' => $generationCode],
                [
                    'number_control' => $numberControl,
                    'document_type' => $tipo,
                    'issued_at' => data_get($dte, 'identificacion.fecEmi', now()->toDateString()),
                    'customer_id' => $data['customerId'] ?? null,
                    'customer_name' => $data['cliente']['nombre'] ?? null,
                    'subtotal' => (float) ($data['resumen']['subtotal'] ?? $data['resumen']['subTotal'] ?? 0),
                    'iva' => (float) ($data['resumen']['iva'] ?? 0),
                    'total' => (float) ($data['resumen']['total'] ?? $data['resumen']['totalPagar'] ?? 0),
                    'status' => 'PENDIENTE',
                    'has_error' => ! ($generated['success'] ?? false),
                    'accepted' => false,
                    'observations' => json_encode($generated['validation'] ?? [], JSON_UNESCAPED_UNICODE),
                    'json_dte' => $dte,
                ],
            );

            $usedNumber = $this->correlatives->extractUsedNumber($dte);
            if ($usedNumber !== null) {
                $this->correlatives->advance($correlative, $usedNumber);
            }

            return [$invoice, $dte, $generated];
        });

        $steps[] = [
            'key' => 'generate',
            'status' => ($generated['success'] ?? false) ? 'done' : 'warning',
            'message' => ($generated['success'] ?? false) ? 'DTE generado y validado.' : 'DTE generado con observaciones.',
        ];
        $steps[] = ['key' => 'save', 'status' => 'done', 'message' => 'Factura guardada localmente.'];

        // Registrar salidas de inventario si el usuario tiene acceso al módulo
        $this->registrarSalidasInventario($request, $data['items'], $invoice);

        // Partida contable de ingreso (FA)
        try {
            app(AccountingEntryService::class)->createFromBilling($invoice->fresh(), $request->user()?->id);
        } catch (\Throwable) {
            // No interrumpir el flujo de facturación si falla la contabilidad
        }

        $firma = $this->normalizarFirma($data['firma'] ?? []);
        $signedDte = null;
        if (! empty($firma['certificado_path']) && ! empty($firma['password'])) {
            try {
                $signed = $this->engine->signInternal(
                    $dte,
                    $this->nitCertificado($firma, $data['config']),
                    $firma['password'],
                    $firma['certificado_path'],
                );
                $signedDte = $this->normalizarDocumentoFirmado($signed);

                if (! $signedDte) {
                    throw new \RuntimeException($signed['error'] ?? 'El firmador no devolvió un documento con firmaMh.');
                }

                $invoice->update([
                    'status' => 'FIRMADO',
                    'signed_dte' => $signedDte,
                    'has_error' => false,
                ]);
                $steps[] = ['key' => 'sign', 'status' => 'done', 'message' => 'Documento firmado.'];
            } catch (\Throwable $e) {
                $invoice->update([
                    'has_error' => true,
                    'observations' => $e->getMessage(),
                ]);
                $steps[] = ['key' => 'sign', 'status' => 'error', 'message' => 'No se pudo firmar: '.$e->getMessage()];
            }
        } else {
            $steps[] = ['key' => 'sign', 'status' => 'warning', 'message' => 'Factura pendiente de firma. Configure certificado y contraseña para firmar automáticamente.'];
        }

        $hacienda = $this->normalizarHacienda($data['hacienda'] ?? []);
        if ($signedDte && ! empty($hacienda['usuario']) && ! empty($hacienda['password'])) {
            try {
                $sent = $this->engine->send(
                    $signedDte,
                    $data['config']['nit'] ?? '',
                    [
                        'ambiente' => $data['config']['hacienda_ambiente'] ?? '00',
                        'usuario' => $hacienda['usuario'],
                        'password' => $hacienda['password'],
                    ],
                    $firma['password'] ?? null,
                );

                $accepted = (bool) ($sent['success'] ?? false) && ! empty($sent['selloRecibido']);
                $invoice->update([
                    'status' => $accepted ? 'ENVIADO' : 'RECHAZADO',
                    'accepted' => $accepted,
                    'has_error' => ! $accepted,
                    'reception_stamp' => $sent['selloRecibido'] ?? null,
                    'observations' => json_encode($sent, JSON_UNESCAPED_UNICODE),
                ]);
                $steps[] = ['key' => 'send', 'status' => $accepted ? 'done' : 'error', 'message' => $accepted ? 'Documento enviado a Hacienda.' : 'Hacienda no aceptó el documento.'];
                $steps[] = ['key' => 'response', 'status' => $accepted ? 'done' : 'error', 'message' => $accepted ? 'Sello de recepción recibido.' : 'Respuesta de Hacienda sin sello de aprobación.'];

                if ($accepted) {
                    $steps[] = ['key' => 'attachments', 'status' => 'done', 'message' => 'Creando PDF y JSON con respuesta de Hacienda.'];
                    $steps[] = $this->enviarCorreoDte(
                        $invoice->fresh(),
                        $signedDte,
                        $data['config'],
                        $this->normalizarCorreo($data['correo'] ?? []),
                        $data['cliente'],
                        $sent,
                    );
                } else {
                    $steps[] = ['key' => 'attachments', 'status' => 'warning', 'message' => 'No se crearon adjuntos de correo porque el documento no fue aprobado.'];
                    $steps[] = ['key' => 'email', 'status' => 'warning', 'message' => 'No se envió correo porque Hacienda no devolvió sello de aprobación.'];
                }
            } catch (\Throwable $e) {
                $invoice->update([
                    'status' => 'RECHAZADO',
                    'has_error' => true,
                    'observations' => $e->getMessage(),
                ]);
                $steps[] = ['key' => 'send', 'status' => 'error', 'message' => 'No se pudo enviar a Hacienda: '.$e->getMessage()];
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
            'dte' => $dte,
            'steps' => $steps,
        ]);
    }

    public function enviarFacturaGuardada(BillingInvoice $invoice)
    {
        $settings = BillingSetting::allAsArray();
        $hacienda = $this->normalizarHacienda($settings['hacienda'] ?? []);
        $correo = $this->normalizarCorreo($settings['correo'] ?? []);
        $firma = $this->normalizarFirma($settings['firma'] ?? []);
        $emisor = $settings['emisor'] ?? [];
        $dte = $invoice->signed_dte ?: $invoice->json_dte;
        $steps = [];

        if (! $dte) {
            return response()->json(['success' => false, 'message' => 'La factura no tiene JSON DTE guardado.'], 422);
        }

        if (! $invoice->signed_dte) {
            if (empty($firma['certificado_path']) || empty($firma['password'])) {
                return response()->json(['success' => false, 'message' => 'La factura no está firmada y falta certificado/contraseña para firmar.'], 422);
            }

            $signed = $this->engine->signInternal(
                $invoice->json_dte,
                $this->nitCertificado($firma, $emisor),
                $firma['password'],
                $firma['certificado_path'],
            );
            $dte = $this->normalizarDocumentoFirmado($signed);

            if (! $dte) {
                return response()->json([
                    'success' => false,
                    'message' => $signed['error'] ?? 'El firmador no devolvió un documento con firmaMh.',
                ], 422);
            }

            $invoice->update(['status' => 'FIRMADO', 'signed_dte' => $dte, 'has_error' => false]);
            $steps[] = ['key' => 'sign', 'status' => 'done', 'message' => 'Documento firmado.'];
        }

        if (empty($hacienda['usuario']) || empty($hacienda['password'])) {
            return response()->json(['success' => false, 'message' => 'Faltan credenciales de Hacienda para enviar.'], 422);
        }

        try {
            $sent = $this->engine->send(
                $dte,
                $emisor['nit'] ?? data_get($invoice->json_dte, 'emisor.nit', ''),
                $hacienda,
                $firma['password'] ?? null,
            );
        } catch (\Throwable $e) {
            $invoice->update([
                'status' => 'RECHAZADO',
                'has_error' => true,
                'observations' => $e->getMessage(),
            ]);
            $steps[] = ['key' => 'send', 'status' => 'error', 'message' => 'No se pudo enviar a Hacienda: '.$e->getMessage()];

            return response()->json([
                'success' => false,
                'message' => 'No se pudo enviar a Hacienda: '.$e->getMessage(),
                'invoice' => $invoice->fresh(),
                'steps' => $steps,
            ], 422);
        }

        $accepted = (bool) ($sent['success'] ?? false) && ! empty($sent['selloRecibido']);
        $invoice->update([
            'status' => $accepted ? 'ENVIADO' : 'RECHAZADO',
            'accepted' => $accepted,
            'has_error' => ! $accepted,
            'reception_stamp' => $sent['selloRecibido'] ?? null,
            'observations' => json_encode($sent, JSON_UNESCAPED_UNICODE),
        ]);
        $steps[] = ['key' => 'send', 'status' => $accepted ? 'done' : 'error', 'message' => $accepted ? 'Documento enviado a Hacienda.' : 'Hacienda no aceptó el documento.'];

        if ($accepted) {
            $cliente = $invoice->customer?->toDteReceptor() ?: [
                'nombre' => $invoice->customer_name,
                'email' => data_get($invoice->json_dte, 'receptor.correo'),
            ];
            $steps[] = ['key' => 'attachments', 'status' => 'done', 'message' => 'Creando PDF y JSON con respuesta de Hacienda.'];
            $steps[] = $this->enviarCorreoDte($invoice->fresh(), $dte, $emisor, $correo, $cliente, $sent);
        } else {
            $steps[] = ['key' => 'email', 'status' => 'warning', 'message' => 'No se envió correo porque Hacienda no devolvió sello de aprobación.'];
        }

        return response()->json([
            'success' => $accepted,
            'message' => $accepted ? 'Factura enviada y correo procesado.' : 'Hacienda no aceptó el documento.',
            'invoice' => $invoice->fresh(),
            'steps' => $steps,
        ]);
    }

    private function invoiceDetail(BillingInvoice $invoice): array
    {
        $dte = $invoice->signed_dte ?: $invoice->json_dte ?: [];
        $receptor = $dte['receptor'] ?? $dte['sujetoExcluido'] ?? [];
        $resumen = $dte['resumen'] ?? [];

        return [
            'id' => $invoice->id,
            'date' => optional($invoice->issued_at)->format('Y-m-d'),
            'numberControl' => $invoice->number_control,
            'generationCode' => $invoice->generation_code,
            'documentType' => $invoice->document_type,
            'customerName' => $invoice->customer_name,
            'status' => $invoice->status,
            'hasError' => $invoice->has_error,
            'accepted' => $invoice->accepted,
            'emailSent' => $invoice->email_sent,
            'receptionStamp' => $invoice->reception_stamp,
            'voidStamp' => $invoice->void_stamp,
            'voidedAt' => optional($invoice->voided_at)->format('Y-m-d H:i:s'),
            'voidReason' => $invoice->void_reason,
            'voidJson' => $invoice->void_json,
            'observations' => $invoice->observations,
            'subtotal' => (float) ($resumen['subTotal'] ?? $resumen['subTotalVentas'] ?? $invoice->subtotal),
            'iva' => (float) ($resumen['totalIva'] ?? $invoice->iva),
            'ivaRetenido' => (float) ($resumen['ivaRete1'] ?? 0),
            'ivaPercibido' => (float) ($resumen['ivaPerci1'] ?? 0),
            'rentaRetenida' => (float) ($resumen['reteRenta'] ?? 0),
            'total' => (float) ($resumen['totalPagar'] ?? $resumen['montoTotalOperacion'] ?? $invoice->total),
            'customer' => [
                'name' => $receptor['nombre'] ?? $invoice->customer_name,
                'document' => $receptor['numDocumento'] ?? null,
                'address' => data_get($receptor, 'direccion.complemento'),
                'email' => $receptor['correo'] ?? null,
                'phone' => $receptor['telefono'] ?? null,
            ],
            'items' => collect($dte['cuerpoDocumento'] ?? [])->map(fn ($item) => [
                'description' => $item['descripcion'] ?? '',
                'quantity' => (float) ($item['cantidad'] ?? 0),
                'unitPrice' => (float) ($item['precioUni'] ?? 0),
                'iva' => (float) ($item['ivaItem'] ?? 0),
                'subtotal' => (float) ($item['ventaGravada'] ?? $item['ventaExenta'] ?? $item['compra'] ?? 0),
            ])->values(),
            'dte' => $dte,
        ];
    }

    private function normalizarDocumentoFirmado(array $signed): ?array
    {
        $documento = $signed['documentoFirmado'] ?? null;

        if (! is_array($documento)) {
            return null;
        }

        if (! empty($documento['firmaMh'])) {
            return $documento;
        }

        if (! empty($signed['firmaMh'])) {
            $documento['firmaMh'] = $signed['firmaMh'];

            return $documento;
        }

        return null;
    }

    private function normalizarHacienda(array $input): array
    {
        $settings = BillingSetting::allAsArray();
        $saved = $settings['hacienda'] ?? [];
        $raw = $settings['electron_raw_config'] ?? [];
        $ambiente = $input['ambiente'] ?? $saved['ambiente'] ?? $raw['hacienda_ambiente'] ?? '00';

        return [
            'ambiente' => $ambiente === 'produccion' ? '01' : ($ambiente === 'pruebas' ? '00' : $ambiente),
            'usuario' => $input['usuario'] ?? $saved['usuario'] ?? $raw['hacienda_usuario'] ?? '',
            'password' => $input['password'] ?? $saved['password'] ?? $raw['hacienda_password'] ?? '',
        ];
    }

    private function registrarSalidasInventario($request, array $items, BillingInvoice $invoice): void
    {
        if (!$request->user()?->hasModuleAccess('inventory')) {
            return;
        }

        $warehouseId = (int) BillingSetting::get('inventory_sales_warehouse_id');
        if (!$warehouseId) {
            return;
        }

        $docNumber = $invoice->number_control;

        $itemsByCode = collect($items)
            ->filter(fn ($item) => !blank($item['codigo'] ?? null))
            ->groupBy('codigo');

        foreach ($itemsByCode as $code => $codeItems) {
            $product = BillingProduct::with('productType')
                ->where('code', $code)
                ->first();

            if (!$product || !($product->productType?->controls_inventory ?? false)) {
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

    private function normalizarFirma(array $input): array
    {
        $settings = BillingSetting::allAsArray();
        $saved = $settings['firma'] ?? [];
        $raw = $settings['electron_raw_config'] ?? [];

        return [
            'nit' => $input['nit'] ?? $input['firmador_usuario'] ?? $input['firmadorUsuario'] ?? $saved['nit'] ?? $saved['firmador_usuario'] ?? $saved['firmadorUsuario'] ?? $raw['firmador_usuario'] ?? '',
            'certificado_path' => $input['certificado_path'] ?? $saved['certificado_path'] ?? $raw['certificado_path'] ?? '',
            'password' => $input['password'] ?? $saved['certificado_password'] ?? $saved['firmador_pin'] ?? $raw['certificado_password'] ?? $raw['firmador_pin'] ?? '',
        ];
    }

    private function nitCertificado(array $firma, array $emisor): string
    {
        return preg_replace('/\D+/', '', (string) ($firma['nit'] ?? $emisor['nit'] ?? ''));
    }

    private function normalizarCorreo(array $input): array
    {
        $settings = BillingSetting::allAsArray();
        $saved = $settings['correo'] ?? [];
        $raw = $settings['electron_raw_config'] ?? [];

        return [
            'smtpHost' => $input['smtpHost'] ?? $saved['smtpHost'] ?? $raw['correo_smtp_host'] ?? 'smtp.gmail.com',
            'smtpPort' => $input['smtpPort'] ?? $saved['smtpPort'] ?? $raw['correo_smtp_port'] ?? 465,
            'smtpSecure' => $input['smtpSecure'] ?? $saved['smtpSecure'] ?? $raw['correo_smtp_secure'] ?? true,
            'username' => $input['username'] ?? $saved['username'] ?? $raw['correo_usuario'] ?? '',
            'password' => $input['password'] ?? $saved['password'] ?? $raw['correo_password'] ?? '',
            'from' => $input['from'] ?? $saved['from'] ?? $raw['correo_remitente'] ?? '',
            'fromName' => $input['fromName'] ?? $saved['fromName'] ?? $raw['correo_nombre'] ?? '',
        ];
    }

    private function enviarCorreoDte(BillingInvoice $invoice, array $dte, array $config, array $correo, array $cliente, array $respuestaHacienda): array
    {
        $destinatario = trim((string) ($cliente['email'] ?? ''));
        $usuario = trim((string) ($correo['username'] ?? $correo['from'] ?? ''));
        $password = (string) ($correo['password'] ?? '');

        if ($destinatario === '') {
            return ['key' => 'email', 'status' => 'warning', 'message' => 'Documento aprobado, pero el cliente no tiene correo registrado.'];
        }

        if ($usuario === '' || $password === '') {
            return ['key' => 'email', 'status' => 'warning', 'message' => 'Documento aprobado, pero falta configurar usuario SMTP y contraseña de aplicación.'];
        }

        $codigo = $invoice->generation_code ?: $invoice->number_control;
        $safeCode = preg_replace('/[^A-Za-z0-9_-]/', '_', $codigo ?: 'DTE');
        $jsonAdjunto = [
            'dte' => $dte,
            'respuestaHacienda' => [
                'estado' => $invoice->status,
                'selloRecibido' => $invoice->reception_stamp,
                'observaciones' => $respuestaHacienda['observaciones'] ?? null,
                'raw' => $respuestaHacienda,
            ],
        ];

        try {
            $pdf = $this->engine->pdf(
                [
                    'numero_control' => $invoice->number_control,
                    'codigo_generacion' => $invoice->generation_code,
                    'fecha_emision' => optional($invoice->issued_at)->format('Y-m-d'),
                    'cliente' => $invoice->customer_name,
                    'subtotal' => (float) $invoice->subtotal,
                    'iva' => (float) $invoice->iva,
                    'total' => (float) $invoice->total,
                    'estado' => $invoice->status,
                    'sello_recepcion' => $invoice->reception_stamp,
                ],
                $dte,
                $config,
                "DTE_{$safeCode}.pdf",
            );

            $host = $correo['smtpHost'] ?? 'smtp.gmail.com';
            $port = (int) ($correo['smtpPort'] ?? 465);
            $secure = filter_var($correo['smtpSecure'] ?? true, FILTER_VALIDATE_BOOL);
            $from = trim((string) ($correo['from'] ?? $usuario));
            $fromName = trim((string) ($correo['fromName'] ?? $config['nombre_empresa'] ?? $from));

            config([
                'mail.mailers.billing_smtp' => [
                    'transport' => 'smtp',
                    'scheme' => $secure || $port === 465 ? 'smtps' : null,
                    'host' => $host,
                    'port' => $port,
                    'username' => $usuario,
                    'password' => $password,
                    'timeout' => null,
                    'local_domain' => parse_url((string) config('app.url'), PHP_URL_HOST),
                ],
                'mail.from.address' => $from,
                'mail.from.name' => $fromName,
            ]);

            Mail::mailer('billing_smtp')->raw(
                "Estimado cliente,\n\nAdjunto encontrara el PDF y JSON del Documento Tributario Electronico {$codigo}.\n\nSaludos.",
                function ($message) use ($destinatario, $from, $fromName, $codigo, $pdf, $jsonAdjunto, $safeCode) {
                    $message
                        ->from($from, $fromName)
                        ->to($destinatario)
                        ->subject("Documento Tributario Electronico {$codigo}");

                    $message->attachData(base64_decode($pdf['base64'] ?? ''), "DTE_{$safeCode}.pdf", ['mime' => 'application/pdf']);
                    $message->attachData(json_encode($jsonAdjunto, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), "DTE_{$safeCode}.json", ['mime' => 'application/json']);
                }
            );

            $invoice->update(['email_sent' => true]);

            return ['key' => 'email', 'status' => 'done', 'message' => 'PDF y JSON enviados al correo del cliente.'];
        } catch (\Throwable $e) {
            return ['key' => 'email', 'status' => 'warning', 'message' => 'Documento aprobado por Hacienda, pero no se pudo enviar el correo: '.$e->getMessage()];
        }
    }

    public function estadoFirmador(Request $request)
    {
        $data = $request->validate([
            'certificadoPath' => 'required|string',
            'nit' => 'required|string',
            'passwordPri' => 'required|string',
        ]);

        return response()->json($this->engine->validateCertificate(
            $data['nit'],
            $data['passwordPri'],
            $data['certificadoPath'],
        ));
    }

    public function autenticar(Request $request)
    {
        $data = $request->validate([
            'config' => 'required|array',
        ]);

        return response()->json($this->engine->authenticate($data['config']));
    }

    public function subirLogo(Request $request)
    {
        $request->validate(['logo' => 'required|file|mimes:png,jpg,jpeg|max:2048']);
        $path = $request->file('logo')->store('logos', 'public');

        return response()->json(['path' => '/storage/'.$path]);
    }

    public function subirCertificado(Request $request)
    {
        $request->validate(['certificado' => 'required|file|extensions:p12,pfx|max:4096']);
        $path = $request->file('certificado')->storeAs('certificados', $request->file('certificado')->getClientOriginalName());

        return response()->json(['path' => storage_path('app/'.$path)]);
    }

    public function pdf(Request $request)
    {
        $data = $request->validate([
            'factura' => 'array',
            'dte' => 'required',
            'config' => 'required|array',
            'filename' => 'nullable|string',
        ]);

        return response()->json($this->engine->pdf(
            $data['factura'] ?? [],
            $data['dte'],
            $data['config'],
            $data['filename'] ?? 'dte.pdf',
        ));
    }
}
