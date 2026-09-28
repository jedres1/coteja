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

                                                    $detailItems = [];
                                                    $identification = $decodedDocumentBody['identificacion'] ?? [];
                                                    $issuer = $decodedDocumentBody['emisor'] ?? [];
                                                    $summary = $decodedDocumentBody['resumen'] ?? [];

                                                    $detailItems[] = ['label' => 'Proveedor', 'value' => data_get($issuer, 'nombre') ?: $invoice->supplier?->name];
                                                    $detailItems[] = ['label' => 'Número', 'value' => $invoice->invoice_number ?: (data_get($identification, 'numeroControl') ?: data_get($identification, 'codigoGeneracion'))];
                                                    $detailItems[] = ['label' => 'Fecha', 'value' => $invoice->purchase_date?->format('d/m/Y') ?: data_get($identification, 'fecEmi')];
                                                    $detailItems[] = ['label' => 'Tipo', 'value' => $documentTypes[$invoice->document_type] ?? $invoice->document_type ?: data_get($identification, 'tipoDte')];
                                                    $detailItems[] = ['label' => 'Subtotal', 'value' => $money($invoice->subtotal ?: data_get($summary, 'subTotal'))];
                                                    $detailItems[] = ['label' => 'IVA', 'value' => $money($invoice->iva ?: data_get($summary, 'totalIva'))];
                                                    $detailItems[] = ['label' => 'Total', 'value' => $money($invoice->total ?: data_get($summary, 'montoTotalOperacion'))];
                                                    $detailItems[] = ['label' => 'Estado', 'value' => $paymentStatuses[$invoice->payment_status] ?? $invoice->payment_status];
                                                @endphp
                                                <div class="document-detail-stack">
                                                    @if($detailItems)
                                                        <div class="document-summary-card">
                                                            @foreach($detailItems as $detailItem)
                                                                <div class="detail-item">
                                                                    <span>{{ $detailItem['label'] }}</span>
                                                                    <strong>{{ $detailItem['value'] ?: '—' }}</strong>
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    @endif

                                                    @if($invoice->extracted_document_body)
                                                        <div class="document-body-preview">
                                                            <h4>Contenido extraído</h4>
                                                            @if($decodedDocumentBody)
                                                                @foreach($decodedDocumentBody as $sectionKey => $sectionValue)
                                                                    @php
                                                                        $sectionTitle = match ($sectionKey) {
                                                                            'identificacion' => 'Identificación',
                                                                            'emisor' => 'Emisor',
                                                                            'receptor' => 'Receptor',
                                                                            'resumen' => 'Resumen',
                                                                            'cuerpoDocumento' => 'Cuerpo del documento',
                                                                            'otrosDocumentos' => 'Otros documentos',
                                                                            default => ucfirst(str_replace('_', ' ', $sectionKey)),
                                                                        };
                                                                    @endphp
                                                                    <div class="json-section">
                                                                        <h5>{{ $sectionTitle }}</h5>
                                                                        @if(is_array($sectionValue))
                                                                            @if(empty($sectionValue))
                                                                                <p class="muted">Sin datos</p>
                                                                            @else
                                                                                <div class="json-section-content">
                                                                                    @foreach($sectionValue as $key => $value)
                                                                                        <div class="json-field">
                                                                                            <span>{{ is_string($key) ? ucfirst(str_replace('_', ' ', $key)) : $key }}</span>
                                                                                            <div class="json-value">{{ is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) }}</div>
                                                                                        </div>
                                                                                    @endforeach
                                                                                </div>
                                                                            @endif
                                                                        @else
                                                                            <div class="json-value">{{ (string) $sectionValue }}</div>
                                                                        @endif
                                                                    </div>
                                                                @endforeach
                                                            @else
                                                                <pre>{{ $invoice->extracted_document_body }}</pre>
                                                            @endif
                                                        </div>
                                                    @else
                                                        <p class="muted">No hay contenido extraído disponible para esta factura; se muestran los datos registrados en el sistema.</p>
                                                    @endif
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
        .document-detail-stack { display: grid; gap: 12px; }
        .document-summary-card { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; }
        .detail-item { padding: 12px; border: 1px solid var(--line); border-radius: 10px; background: var(--panel); display: flex; flex-direction: column; gap: 4px; }
        .detail-item span { font-size: 12px; color: var(--muted); text-transform: uppercase; letter-spacing: 0.04em; }
        .document-body-preview { max-height: 70vh; overflow: auto; padding: 16px; border: 1px solid var(--line); border-radius: 10px; background: var(--panel); }
        .document-body-preview h4 { margin: 0 0 8px; font-size: 14px; }
        .document-body-preview h5 { margin: 10px 0 6px; font-size: 13px; }
        .json-section { border: 1px solid var(--line); border-radius: 8px; padding: 10px; margin-bottom: 10px; background: rgba(255,255,255,0.03); }
        .json-section-content { display: grid; gap: 8px; }
        .json-field { display: grid; gap: 4px; padding: 8px; border-radius: 6px; background: rgba(0,0,0,0.03); }
        .json-field span { font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--muted); }
        .json-value { white-space: pre-wrap; word-break: break-word; font-family: ui-monospace, SFMono-Regular, SFMono-Regular, Menlo, monospace; font-size: 12px; }
        .document-body-preview pre { margin: 0; white-space: pre-wrap; word-break: break-word; font-family: ui-monospace, SFMono-Regular, SFMono-Regular, Menlo, monospace; font-size: 12px; }
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
