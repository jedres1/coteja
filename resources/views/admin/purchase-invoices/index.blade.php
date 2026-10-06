<x-layouts.app title="Compras | Coteja">
    @php
        $documentTypes = [
            '01' => 'Factura',
            '03' => 'CCF',
            '05' => 'Nota crédito',
            '06' => 'Nota débito',
            '11' => 'Exportación',
            '14' => 'Sujeto excluido',
            '99' => 'Otro',
        ];
        $paymentStatuses = [
            'pending' => 'Pendiente',
            'partial' => 'Parcial',
            'paid' => 'Pagada',
            'void' => 'Anulada',
        ];
        $statuses = [
            'registered' => 'Registrada',
            'reviewed' => 'Revisada',
            'accounted' => 'Contabilizada',
            'void' => 'Anulada',
        ];
        $money = fn ($value) => '$'.number_format((float) $value, 2);
    @endphp

    <div class="top purchases-top">
        <div>
            <h1>Compras</h1>
            <span class="muted">Registro y control de facturas de compras realizadas</span>
        </div>
    </div>

    <div class="card">
        <div class="list-header">
            <div>
                <h3>Listado</h3>
                <span class="muted">
                    Mostrando {{ $invoices->count() }} de {{ $invoices->total() }} facturas
                    @if($search)
                        para "{{ $search }}"
                    @endif
                </span>
            </div>
            <form method="get" action="{{ route('admin.purchase-invoices.index') }}" class="purchase-search">
                <label class="sr-only" for="purchase-search">Buscar facturas</label>
                <input id="purchase-search" name="search" type="search" value="{{ $search }}" placeholder="Buscar factura, proveedor, documento...">
                <select name="supplier_id" aria-label="Filtrar por proveedor">
                    <option value="">Todos los proveedores</option>
                    @foreach($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" @selected((string) $supplierId === (string) $supplier->id)>{{ $supplier->name }}</option>
                    @endforeach
                </select>
                <button class="btn" type="submit">Buscar</button>
                @if($search || $supplierId)
                    <a class="btn secondary" href="{{ route('admin.purchase-invoices.index') }}">Limpiar</a>
                @endif
            </form>
            <span class="badge">{{ $invoices->perPage() }} por página</span>
        </div>

        <div class="table-scroll">
            <table class="purchases-table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Proveedor</th>
                        <th>Documento</th>
                        <th>Subtotal</th>
                        <th>IVA</th>
                        <th>Total</th>
                        <th>Pago</th>
                        <th>Estado</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($invoices as $invoice)
                        <tr>
                            <td>
                                {{ $invoice->purchase_date?->format('d/m/Y') }}
                                @if($invoice->due_date)
                                    <br><span class="muted">Vence {{ $invoice->due_date->format('d/m/Y') }}</span>
                                @endif
                            </td>
                            <td>
                                <strong>{{ $invoice->supplier?->name }}</strong>
                                @if($invoice->supplier?->document_number)
                                    <br><span class="muted">{{ $invoice->supplier->document_number }}</span>
                                @endif
                            </td>
                            <td>
                                {{ $documentTypes[$invoice->document_type] ?? $invoice->document_type }}
                                <br><span class="muted">{{ $invoice->invoice_number }}</span>
                            </td>
                            <td>{{ $money($invoice->subtotal) }}</td>
                            <td>{{ $money($invoice->iva) }}</td>
                            <td><strong>{{ $money($invoice->total) }}</strong></td>
                            <td>
                                <span class="badge {{ $invoice->payment_status }}">{{ $paymentStatuses[$invoice->payment_status] ?? $invoice->payment_status }}</span>
                                @if($invoice->payment_method)
                                    <br><span class="muted">{{ $invoice->payment_method }}</span>
                                @endif
                            </td>
                            <td><span class="badge {{ $invoice->status }}">{{ $statuses[$invoice->status] ?? $invoice->status }}</span></td>
                            <td>
                                <div class="row-actions">
                                    <details class="overlay-modal">
                                        <summary class="btn secondary">Ver</summary>
                                        <div class="overlay-layer">
                                            <button class="overlay-backdrop" type="button" data-overlay-close aria-label="Cerrar"></button>
                                            <div class="card overlay-panel wide">
                                                <div class="overlay-header">
                                                    <div>
                                                        <h3>Cuerpo del documento extraído</h3>
                                                        <span class="muted">{{ $invoice->invoice_number }}</span>
                                                    </div>
                                                    <button class="btn secondary overlay-close" type="button" data-overlay-close>Cerrar</button>
                                                </div>
                                                @php
                                                    $decodedDocumentBody = null;
                                                    if (! blank($invoice->extracted_document_body)) {
                                                        try {
                                                            $decodedDocumentBody = json_decode($invoice->extracted_document_body, true, 512, JSON_THROW_ON_ERROR);
                                                        } catch (\Throwable) {
                                                            $decodedDocumentBody = null;
                                                        }
                                                    }

                                                    $identification = data_get($decodedDocumentBody, 'identificacion', []);
                                                    $issuer = data_get($decodedDocumentBody, 'emisor', []);
                                                    $receiver = data_get($decodedDocumentBody, 'receptor', []);
                                                    $summary = data_get($decodedDocumentBody, 'resumen', []);
                                                    $invoiceItems = data_get($decodedDocumentBody, 'cuerpoDocumento', []);
                                                    $invoiceItems = is_array($invoiceItems) ? array_values($invoiceItems) : [];
                                                    $paymentCodes = [
                                                        '01' => 'Efectivo', '02' => 'Tarjeta de débito', '03' => 'Tarjeta de crédito',
                                                        '04' => 'Cheque', '05' => 'Transferencia', '08' => 'Dinero electrónico',
                                                        '09' => 'Monedero electrónico', '11' => 'Bitcoin', '12' => 'Otro', '13' => 'Crédito',
                                                    ];
                                                    $payment = data_get($summary, 'pagos.0', []);
                                                    $paymentCode = (string) data_get($payment, 'codigo', '');
                                                    $paymentMethod = $invoice->payment_method ?: ($paymentCodes[$paymentCode] ?? $paymentCode);
                                                    $paymentCondition = match ((string) data_get($summary, 'condicionOperacion', '')) {
                                                        '1' => 'Contado',
                                                        '2' => 'Crédito',
                                                        '3' => 'Otro',
                                                        default => 'No especificada',
                                                    };
                                                    $invoiceDate = $invoice->purchase_date?->format('d/m/Y') ?: data_get($identification, 'fecEmi', '—');
                                                    $invoiceNumber = $invoice->invoice_number ?: data_get($identification, 'numeroControl', '—');
                                                    $invoiceType = $documentTypes[$invoice->document_type] ?? $invoice->document_type;
                                                    $subtotal = data_get($summary, 'subTotal', $invoice->subtotal);
                                                    $taxTotal = data_get($summary, 'totalIva', data_get($summary, 'ivaPerci1', $invoice->iva));
                                                    $grandTotal = data_get($summary, 'montoTotalOperacion', data_get($summary, 'totalPagar', $invoice->total));
                                                    $taxableBase = collect($invoiceItems)->sum(fn ($item) => (float) data_get($item, 'ventaGravada', 0));
                                                    $documentStamp = data_get($decodedDocumentBody, 'selloRecibido')
                                                        ?: data_get($decodedDocumentBody, 'respuestaMH.selloRecibido')
                                                        ?: data_get($decodedDocumentBody, 'sello_recepcion');
                                                @endphp
                                                <div class="invoice-paper">
                                                    <div class="invoice-paper-header">
                                                        <div>
                                                            <span class="invoice-kicker">Documento de compra</span>
                                                            <h2>{{ $invoiceType }}</h2>
                                                            <span class="invoice-number">{{ $invoiceNumber }}</span>
                                                        </div>
                                                        <dl class="invoice-meta">
                                                            <div><dt>Fecha de emisión</dt><dd>{{ $invoiceDate }}</dd></div>
                                                            <div><dt>Estado de pago</dt><dd>{{ $paymentStatuses[$invoice->payment_status] ?? $invoice->payment_status }}</dd></div>
                                                        </dl>
                                                    </div>

                                                    <div class="invoice-parties">
                                                        <section>
                                                            <h4>Emisor</h4>
                                                            <strong>{{ data_get($issuer, 'nombre') ?: $invoice->supplier?->name ?: '—' }}</strong>
                                                            <span>{{ data_get($issuer, 'nombreComercial') ?: $invoice->supplier?->trade_name ?: '' }}</span>
                                                            <span>NIT: {{ data_get($issuer, 'nit') ?: $invoice->supplier?->document_number ?: '—' }}</span>
                                                            <span>NRC: {{ data_get($issuer, 'nrc') ?: $invoice->supplier?->nrc ?: '—' }}</span>
                                                            <span>{{ data_get($issuer, 'direccion.complemento') ?: $invoice->supplier?->address ?: '—' }}</span>
                                                        </section>
                                                        <section>
                                                            <h4>Receptor</h4>
                                                            <strong>{{ data_get($receiver, 'nombre') ?: $invoice->customer?->name ?: '—' }}</strong>
                                                            <span>{{ data_get($receiver, 'nombreComercial') ?: $invoice->customer?->trade_name ?: '' }}</span>
                                                            <span>Documento: {{ data_get($receiver, 'numDocumento') ?: $invoice->customer?->document_number ?: '—' }}</span>
                                                            <span>NRC: {{ data_get($receiver, 'nrc') ?: $invoice->customer?->nrc ?: '—' }}</span>
                                                            <span>{{ data_get($receiver, 'direccion.complemento') ?: $invoice->customer?->address ?: '—' }}</span>
                                                        </section>
                                                    </div>

                                                    <div class="invoice-items-wrap">
                                                        <table class="invoice-items">
                                                            <thead>
                                                                <tr><th>#</th><th>Descripción</th><th>Cantidad</th><th>Precio</th><th>Impuesto</th><th>Total</th></tr>
                                                            </thead>
                                                            <tbody>
                                                                @forelse($invoiceItems as $itemIndex => $item)
                                                                    @php
                                                                        $quantity = (float) data_get($item, 'cantidad', 0);
                                                                        $unitPrice = (float) data_get($item, 'precioUni', 0);
                                                                        $itemTaxable = (float) data_get($item, 'ventaGravada', 0);
                                                                        $itemExempt = (float) data_get($item, 'ventaExenta', 0);
                                                                        $itemNonTaxable = (float) data_get($item, 'ventaNoSuj', 0);
                                                                        $discount = (float) data_get($item, 'montoDescu', 0);
                                                                        $lineTotal = $itemTaxable + $itemExempt + $itemNonTaxable;
                                                                        if ($lineTotal == 0) $lineTotal = max(0, $quantity * $unitPrice - $discount);
                                                                        $explicitTax = data_get($item, 'ivaItem', data_get($item, 'impuesto'));
                                                                        $lineTax = $explicitTax !== null
                                                                            ? (float) $explicitTax
                                                                            : ($taxableBase > 0 ? (float) $taxTotal * $itemTaxable / $taxableBase : 0);
                                                                    @endphp
                                                                    <tr>
                                                                        <td>{{ data_get($item, 'numItem', $itemIndex + 1) }}</td>
                                                                        <td>
                                                                            <strong>{{ data_get($item, 'descripcion', 'Artículo sin descripción') }}</strong>
                                                                            @if(data_get($item, 'codigo'))<small>Código: {{ data_get($item, 'codigo') }}</small>@endif
                                                                        </td>
                                                                        <td>{{ rtrim(rtrim(number_format($quantity, 4, '.', ','), '0'), '.') }} {{ data_get($item, 'uniMedida', '') }}</td>
                                                                        <td>{{ $money($unitPrice) }}</td>
                                                                        <td>{{ $money($lineTax) }}</td>
                                                                        <td><strong>{{ $money($lineTotal + $lineTax) }}</strong></td>
                                                                    </tr>
                                                                @empty
                                                                    <tr><td colspan="6" class="invoice-empty">El documento no incluye el detalle de productos.</td></tr>
                                                                @endforelse
                                                            </tbody>
                                                        </table>
                                                    </div>

                                                    <div class="invoice-bottom">
                                                        <div class="invoice-payment">
                                                            <h4>Pago y condiciones</h4>
                                                            <dl>
                                                                <div><dt>Forma de pago</dt><dd>{{ $paymentMethod ?: 'No especificada' }}</dd></div>
                                                                <div><dt>Condición</dt><dd>{{ $paymentCondition }}</dd></div>
                                                                @if(data_get($payment, 'montoPago') !== null)
                                                                    <div><dt>Monto pagado</dt><dd>{{ $money(data_get($payment, 'montoPago')) }}</dd></div>
                                                                @endif
                                                            </dl>
                                                        </div>
                                                        <div class="invoice-totals">
                                                            <div><span>Subtotal</span><strong>{{ $money($subtotal) }}</strong></div>
                                                            @if((float) data_get($summary, 'totalDescu', 0) > 0)
                                                                <div><span>Descuento</span><strong>{{ $money(data_get($summary, 'totalDescu')) }}</strong></div>
                                                            @endif
                                                            <div><span>Impuestos</span><strong>{{ $money($taxTotal) }}</strong></div>
                                                            <div class="invoice-grand-total"><span>Total</span><strong>{{ $money($grandTotal) }}</strong></div>
                                                        </div>
                                                    </div>

                                                    <div class="invoice-stamp">
                                                        <strong>Sello de recepción de Hacienda</strong>
                                                        <span>{{ $documentStamp ?: 'No disponible en el documento recibido' }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </details>
                                    <form method="post" action="{{ route('admin.purchase-invoices.destroy', $invoice) }}" class="inline" data-confirm-delete="Eliminar factura de compra">
                                        @csrf
                                        @method('delete')
                                        <button class="btn danger" type="submit">Eliminar</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="empty">No hay facturas de compra registradas</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="pagination-wrap">
            {{ $invoices->links() }}
        </div>
    </div>

    <style>
        .purchases-top { align-items: flex-start; }
        .purchase-form-grid { grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); }
        .purchase-search { display: flex; align-items: center; gap: 8px; flex: 1 1 620px; justify-content: flex-end; }
        .purchase-search input { max-width: 300px; }
        .purchase-search select { max-width: 260px; }
        .purchases-table td { vertical-align: middle; }
        .danger { background: var(--bad); }
        .row-actions { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
        .invoice-paper { max-height: 72vh; overflow: auto; padding: 28px; border: 1px solid #d8dde2; background: #fff; color: #202a33; box-shadow: 0 8px 24px rgba(20, 32, 44, .08); }
        .invoice-paper-header { display: flex; justify-content: space-between; gap: 24px; padding-bottom: 20px; border-bottom: 2px solid #283b4a; }
        .invoice-kicker { color: #647582; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .invoice-paper-header h2 { margin: 5px 0; color: #1c303e; font-size: 24px; }
        .invoice-number { color: #53636e; font-size: 13px; overflow-wrap: anywhere; }
        .invoice-meta, .invoice-payment dl { display: grid; gap: 9px; margin: 0; }
        .invoice-meta div, .invoice-payment dl div { display: grid; gap: 3px; }
        .invoice-meta dt, .invoice-payment dt { color: #647582; font-size: 10px; font-weight: 700; text-transform: uppercase; }
        .invoice-meta dd, .invoice-payment dd { margin: 0; font-size: 13px; font-weight: 600; }
        .invoice-parties { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; padding: 20px 0; }
        .invoice-parties section { display: grid; align-content: start; gap: 5px; min-width: 0; padding: 14px; border: 1px solid #dfe4e8; }
        .invoice-parties h4, .invoice-payment h4 { margin: 0 0 5px; color: #53636e; font-size: 11px; text-transform: uppercase; }
        .invoice-parties strong { color: #1c303e; font-size: 14px; }
        .invoice-parties span { color: #4a5963; font-size: 12px; overflow-wrap: anywhere; }
        .invoice-items-wrap { overflow-x: auto; }
        .invoice-items { width: 100%; min-width: 640px; border-collapse: collapse; color: #202a33; }
        .invoice-items th { padding: 10px 8px; border-top: 1px solid #283b4a; border-bottom: 1px solid #283b4a; color: #53636e; font-size: 10px; text-align: left; text-transform: uppercase; }
        .invoice-items td { padding: 11px 8px; border-bottom: 1px solid #e4e8eb; font-size: 12px; vertical-align: top; }
        .invoice-items th:first-child, .invoice-items td:first-child { width: 38px; }
        .invoice-items td:nth-child(n+3) { white-space: nowrap; }
        .invoice-items small { display: block; margin-top: 3px; color: #647582; }
        .invoice-items .invoice-empty { padding: 22px 8px; color: #647582; text-align: center; }
        .invoice-bottom { display: grid; grid-template-columns: minmax(0, 1fr) minmax(220px, .65fr); gap: 28px; padding: 20px 0; }
        .invoice-totals { display: grid; align-content: start; gap: 9px; }
        .invoice-totals div { display: flex; justify-content: space-between; gap: 16px; color: #53636e; font-size: 12px; }
        .invoice-totals strong { color: #202a33; }
        .invoice-totals .invoice-grand-total { margin-top: 4px; padding-top: 12px; border-top: 2px solid #283b4a; color: #1c303e; font-size: 16px; font-weight: 700; }
        .invoice-totals .invoice-grand-total strong { color: #1c303e; font-size: 18px; }
        .invoice-stamp { display: grid; gap: 6px; padding-top: 14px; border-top: 1px solid #dfe4e8; }
        .invoice-stamp strong { color: #53636e; font-size: 10px; text-transform: uppercase; }
        .invoice-stamp span { color: #202a33; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 11px; overflow-wrap: anywhere; }
        .pending, .registered { background: #fef3c7; color: #92400e; }
        .partial, .reviewed { background: #dbeafe; color: #1e40af; }
        .paid, .accounted { background: #dcfce7; color: #166534; }
        .void { background: #fee2e2; color: #991b1b; }
        .pagination-wrap nav > div:first-child { display: none; }
        .pagination-wrap nav > div:last-child,
        .pagination-wrap nav .relative { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
        .pagination-wrap a,
        .pagination-wrap span[aria-current] span,
        .pagination-wrap span[aria-disabled] span {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 36px;
            min-height: 36px;
            padding: 8px 10px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: var(--panel);
        }
        .pagination-wrap span[aria-current] span { background: var(--brand); color: #fff; border-color: var(--brand); }
        @media (max-width: 800px) {
            .purchases-top { display: grid; }
            .purchase-search { flex: 1 1 100%; justify-content: stretch; }
            .purchase-search input,
            .purchase-search select { max-width: none; }
        }
        @media (max-width: 600px) {
            .invoice-paper { padding: 16px; }
            .invoice-paper-header { display: grid; }
            .invoice-meta { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .invoice-parties, .invoice-bottom { grid-template-columns: 1fr; }
        }
    </style>
    <script>
        document.querySelectorAll('.purchase-form-grid').forEach((form) => {
            const subtotal = form.querySelector('[name="subtotal"]');
            const iva = form.querySelector('[name="iva"]');
            const total = form.querySelector('[name="total"]');
            if (!subtotal || !iva || !total) return;

            const syncTotal = () => {
                const subtotalValue = Number.parseFloat(subtotal.value || '0') || 0;
                const ivaValue = Number.parseFloat(iva.value || '0') || 0;
                total.value = (subtotalValue + ivaValue).toFixed(2);
            };

            subtotal.addEventListener('input', syncTotal);
            iva.addEventListener('input', syncTotal);
        });

        document.querySelectorAll('[data-confirm-delete]').forEach((form) => {
            form.addEventListener('submit', (event) => {
                if (!confirm(`${form.dataset.confirmDelete}. ¿Desea continuar?`)) {
                    event.preventDefault();
                }
            });
        });
    </script>
</x-layouts.app>
